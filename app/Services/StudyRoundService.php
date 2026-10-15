<?php

namespace App\Services;

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A learning round: which cards, in which order, and what happens on an
 * answer. See docs/specs/vokabelkarten.md, "Lernrunde" and "Freies Üben".
 *
 * The round state is a plain array. This service never touches the session;
 * the controller stores the array and hands it back on the next request.
 *
 * Every learn item carries the runs its answer counts for: one run when
 * learning on the deck page, all runs in which the card is due for "Los
 * geht's" (docs/specs/lernplaene-und-karten.md, A5). Practice items have
 * none; nothing is saved there.
 *
 * @phpstan-type RoundItem array{card: string, direction: string, repeat: bool, runs?: list<string>}
 * Modes: `learn` (counts for the boxes), `practice` (one box, nothing
 * saved) and `test` ("Für den Test üben": every card until known once,
 * nothing saved; docs/specs/einfacher-lernen.md, F).
 * @phpstan-type Round array{mode: string, deck: string|null, queue: list<RoundItem>, step: int, total: int, answered: int, known: int, first: int, promoted: int, repeats: array<string, int>}
 */
class StudyRoundService
{
    public function __construct(
        private DeckService $decks,
        private LeitnerService $leitner,
        private DeckRunService $runs,
        private CardSelectionService $selection,
        private DeckPreferenceService $preferences,
    ) {}

    /**
     * "Nur diesen Stapel lernen": due cards of one deck first, then new
     * ones — front → back, and back → front too when the person switched
     * that on. Without a run id the deck's oldest run is used — the one the
     * deck page shows first — and only a deck without any run starts one
     * with the standard plan. Learning somebody else's shared deck follows
     * it, so it shows up under "Meine Stapel". Not limited by "new_per_day".
     *
     * @return Round
     *
     * @throws ModelNotFoundException<Deck|DeckRun>
     */
    public function startForDeck(User $user, string $deckId, ?string $runId = null): array
    {
        $deck = $this->decks->getVisible($user, $deckId);
        $run = $runId !== null && $runId !== ''
            ? $this->runs->getOwned($user, $deck->id, $runId)
            : ($this->runs->listOwned($user, $deck)->first() ?? $this->runs->forStandard($user, $deck));
        if (! $deck->isOwnedBy($user)) {
            $user->followedDecks()->syncWithoutDetaching([$deck->id]);
        }

        $limit = (int) config('learning.round_size');
        $due = $this->dueQuery($user)
            ->where('card_progress.deck_run_id', $run->id)
            ->orderBy('card_progress.due_on')
            ->orderByRaw("card_progress.direction = 'backward'")
            ->limit($limit)
            ->toBase()
            ->get(['card_progress.card_id', 'card_progress.direction'])
            ->map(fn (object $row) => ['card' => (string) $row->card_id, 'direction' => (string) $row->direction, 'repeat' => false, 'runs' => [$run->id]])
            ->all();

        $new = $this->newCards($user, [$deck->id], $limit - count($due), $run->id)
            ->map(fn (object $row) => ['card' => (string) $row->id, 'direction' => (string) $row->direction, 'repeat' => false, 'runs' => [$run->id]])
            ->all();

        return $this->newRound('learn', $deck->id, [...$due, ...$new]);
    }

    /**
     * "Los geht's": due cards from all of the person's decks (own and
     * followed shared ones) and all their runs, oldest due date first. A
     * card due in two runs comes up once; the answer counts for both.
     *
     * Free places are filled with new cards, taken in turns from all decks,
     * at most "new_per_day" a day — `$more` ("Noch 20 neue Karten") goes
     * beyond that. A new card is learned in the deck's oldest run, started
     * with the standard plan if there is none yet.
     *
     * @return Round
     */
    public function startToday(User $user, bool $more = false): array
    {
        $limit = (int) config('learning.round_size');
        $due = $this->dueQuery($user)
            ->toBase()
            ->whereIn('cards.deck_id', $this->decks->mineQuery($user)->select('id'))
            ->groupBy('card_progress.card_id', 'card_progress.direction')
            ->select('card_progress.card_id', 'card_progress.direction')
            ->selectRaw("string_agg(card_progress.deck_run_id::text, ',' ORDER BY deck_runs.created_at, deck_runs.id) AS runs")
            ->orderByRaw('min(card_progress.due_on)')
            ->orderBy('card_progress.card_id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row) => [
                'card' => (string) $row->card_id,
                'direction' => (string) $row->direction,
                'repeat' => false,
                'runs' => explode(',', (string) $row->runs),
            ])
            ->values()
            ->all();

        $room = $limit - count($due);
        if (! $more) {
            $room = min($room, $this->newAllowedToday($user));
        }
        $rows = $this->newCards($user, $this->decks->mineQuery($user)->pluck('id')->all(), $room);

        $runs = [];
        $new = [];
        foreach ($rows as $row) {
            $deckId = (string) $row->deck_id;
            $runs[$deckId] ??= $this->runFor($user, $deckId)->id;
            $new[] = ['card' => (string) $row->id, 'direction' => (string) $row->direction, 'repeat' => false, 'runs' => [$runs[$deckId]]];
        }

        return $this->newRound('learn', null, [...$due, ...$new]);
    }

    /**
     * How many new cards "Heute" may still start today by itself.
     */
    public function newAllowedToday(User $user): int
    {
        $startOfDay = $this->leitner->today()->utc();
        $started = CardProgress::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $startOfDay)
            ->distinct()
            ->count(DB::raw('card_id::text || direction'));

        return max(0, (int) config('learning.new_per_day') - $started);
    }

    /**
     * New cards of the given decks, as rows {id, deck_id, direction}: active
     * (issue #10), in a direction the person learns, never answered in it —
     * in the given run, or else in any of the person's runs.
     * Front → back comes first; within that, the decks take turns, each in
     * the order of its cards.
     *
     * @param  list<string>  $deckIds
     * @return Collection<int, \stdClass>
     */
    public function newCards(User $user, array $deckIds, int $limit, ?string $runId = null): Collection
    {
        if ($limit <= 0 || $deckIds === []) {
            return collect();
        }

        return DB::query()
            ->fromSub($this->newCardsQuery($user, $deckIds, $runId), 'ranked')
            ->orderByRaw("direction = 'backward'")
            ->orderBy('turn')
            ->orderBy('deck_id')
            ->limit($limit)
            ->get();
    }

    /**
     * How many new cards the given decks hold for the person — counted up
     * to `$max` at most, as "Heute" never shows more.
     *
     * @param  list<string>  $deckIds
     */
    public function newCardCount(User $user, array $deckIds, int $max): int
    {
        return $deckIds === [] ? 0 : DB::query()->fromSub($this->newCardsQuery($user, $deckIds)->limit($max), 'capped')->count();
    }

    /**
     * @param  list<string>  $deckIds
     */
    private function newCardsQuery(User $user, array $deckIds, ?string $runId = null): QueryBuilder
    {
        [$active, $activeBindings] = $this->selection->activeCondition($user);
        [$learned, $learnedBindings] = $this->preferences->directionCondition($user, 'dirs.direction');

        return DB::table('cards')
            ->crossJoin(DB::raw("(values ('forward'), ('backward')) as dirs(direction)"))
            ->whereIn('cards.deck_id', $deckIds)
            ->whereRaw($active, $activeBindings)
            ->whereRaw($learned, $learnedBindings)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('card_progress')
                ->where('card_progress.user_id', $user->id)
                ->when($runId !== null, fn ($q) => $q->where('card_progress.deck_run_id', $runId))
                ->whereColumn('card_progress.card_id', 'cards.id')
                ->whereColumn('card_progress.direction', 'dirs.direction'))
            ->select('cards.id', 'cards.deck_id', 'dirs.direction')
            ->selectRaw('row_number() over (partition by cards.deck_id, dirs.direction order by cards.created_at, cards.id) as turn');
    }

    /**
     * "Für den Test üben": every active card of the deck in random order,
     * both ways when back → front is switched on. Nothing is saved, and a
     * card not known comes back until it is known.
     *
     * @return Round
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function startTest(User $user, string $deckId): array
    {
        $deck = $this->decks->getVisible($user, $deckId);
        [$active, $bindings] = $this->selection->activeCondition($user);
        $directions = [Direction::Forward->value];
        if ($this->preferences->withReverse($user, $deck)) {
            $directions[] = Direction::Backward->value;
        }

        // Cut in the database, so a huge deck never loads every id.
        $perDirection = intdiv((int) config('learning.test_max'), count($directions));
        $items = [];
        foreach ($deck->cards()->whereRaw($active, $bindings)->inRandomOrder()->limit($perDirection)->pluck('id') as $id) {
            foreach ($directions as $direction) {
                $items[] = ['card' => $id, 'direction' => $direction, 'repeat' => false];
            }
        }
        shuffle($items);

        return $this->newRound('test', $deck->id, $items);
    }

    /** The deck's oldest run of the person, or a new one with the standard plan. */
    private function runFor(User $user, string $deckId): DeckRun
    {
        return DeckRun::query()
            ->where('user_id', $user->id)
            ->where('deck_id', $deckId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first()
            ?? $this->runs->forStandard($user, Deck::findOrFail($deckId));
    }

    /**
     * Free practice: random cards of a deck, nothing is saved.
     *
     * With `$box`, only the cards in that box of the chosen direction:
     * 0 = new (never answered), N = mastered ("Kann ich"). A box belongs to
     * one direction, so "mixed" does not combine with it.
     *
     * A box belongs to a run: the given one, or else the deck's oldest.
     *
     * @param  string  $mode  forward, backward or mixed
     * @return Round
     *
     * @throws ModelNotFoundException<Deck|DeckRun>
     * @throws ValidationException
     */
    public function startPractice(User $user, string $deckId, string $mode, ?int $box = null, ?string $runId = null): array
    {
        if (! in_array($mode, ['forward', 'backward', 'mixed'], true)) {
            throw ValidationException::withMessages(['direction' => 'Bitte eine Richtung wählen.']);
        }

        $deck = $this->decks->getVisible($user, $deckId);
        [$active, $bindings] = $this->selection->activeCondition($user);
        $cards = $deck->cards()->whereRaw($active, $bindings);

        if ($box !== null) {
            $run = $runId !== null && $runId !== ''
                ? $this->runs->getOwned($user, $deck->id, $runId)
                : $this->runs->listOwned($user, $deck)->first();
            $boxCount = $run->box_count ?? 0;
            if ($run === null || $mode === 'mixed' || $box < 0 || $box > $boxCount) {
                throw ValidationException::withMessages(['box' => 'Dieses Fach gibt es nicht.']);
            }

            $progress = fn ($q) => $q->select(DB::raw(1))
                ->from('card_progress')
                ->whereColumn('card_progress.card_id', 'cards.id')
                ->where('card_progress.deck_run_id', $run->id)
                ->where('card_progress.direction', $mode);

            match (true) {
                $box === 0 => $cards->whereNotExists($progress),
                // Boxes above N cannot happen (plans are fixed); count them as mastered anyway.
                $box === $boxCount => $cards->whereExists(fn ($q) => $progress($q)->where('card_progress.box', '>=', $boxCount)),
                default => $cards->whereExists(fn ($q) => $progress($q)->where('card_progress.box', $box)),
            };
        }

        $items = $cards
            ->inRandomOrder()
            ->limit((int) config('learning.round_size'))
            ->pluck('id')
            ->map(fn (string $id) => [
                'card' => $id,
                'direction' => $mode === 'mixed'
                    ? (random_int(0, 1) === 0 ? Direction::Forward->value : Direction::Backward->value)
                    : $mode,
                'repeat' => false,
            ])
            ->all();

        return $this->newRound('practice', $deck->id, $items);
    }

    /**
     * The card that is up, or null when the round is over. Cards that
     * vanished meanwhile (deleted, deck no longer shared, run reset) are
     * skipped silently.
     *
     * `changed`: the card was edited after the person last answered it in
     * these runs (docs/specs/lernplaene-und-karten.md, B4). New cards and
     * free practice never count as changed.
     *
     * @param  Round  $round
     * @return array{0: Round, 1: array{card: Card, direction: Direction, repeat: bool, runs: list<DeckRun>, changed: bool}|null}
     */
    public function current(User $user, array $round): array
    {
        while ($round['queue'] !== []) {
            $item = $round['queue'][0];
            $card = Card::with('deck')
                ->whereIn('deck_id', $this->decks->visibleTo($user)->select('id'))
                ->find($item['card']);

            $runs = [];
            if ($card !== null && $round['mode'] === 'learn') {
                $runs = DeckRun::query()
                    ->whereIn('id', array_filter($item['runs'] ?? [], fn (string $id) => Str::isUuid($id)))
                    ->where('user_id', $user->id)
                    ->where('deck_id', $card->deck_id)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get()
                    ->all();
            }

            if ($card !== null && ($round['mode'] !== 'learn' || $runs !== [])) {
                return [$round, [
                    'card' => $card,
                    'direction' => Direction::from($item['direction']),
                    'repeat' => $item['repeat'],
                    'runs' => $runs,
                    'changed' => $this->changedSinceReview($card, $item['direction'], $runs),
                ]];
            }

            array_shift($round['queue']);
            $round['total'] = max(0, $round['total'] - ($item['repeat'] ? 0 : 1));
        }

        return [$round, null];
    }

    /**
     * Records the answer for the card that is up.
     *
     * The form sends the card and the step it was shown at. An answer for
     * any other step (a double tap, an old tab, a held arrow key) changes
     * nothing — the caller simply shows the current card again. The step
     * also tells apart the same card coming up twice in a row (both
     * directions, or a repeat).
     *
     * @param  Round  $round
     * @return Round
     */
    public function answer(User $user, array $round, string $cardId, int $step, bool $known): array
    {
        [$round, $current] = $this->current($user, $round);
        if ($current === null || $current['card']->id !== $cardId || $step !== $round['step']) {
            return $round;
        }

        $item = array_shift($round['queue']);
        $round['step']++;
        $key = $cardId.'|'.$current['direction']->value;

        if ($round['mode'] === 'test') {
            // Every card until known once: "known" counts cards done, "first" those known at once.
            $round['answered'] += $current['repeat'] ? 0 : 1;
            $round['known'] += $known ? 1 : 0;
            $round['first'] += $known && ! $current['repeat'] ? 1 : 0;
            if (! $known) {
                $round['queue'][] = ['card' => $cardId, 'direction' => $current['direction']->value, 'repeat' => true];
            }

            return $round;
        }

        if (! $current['repeat']) {
            $round['answered']++;
            $round['known'] += $known ? 1 : 0;

            if ($round['mode'] === 'learn') {
                $move = $this->leitner->review($user, $current['runs'], $current['card'], $current['direction'], $known);
                // A new card that was not known also lands in box 1 — that is
                // not moving up.
                $round['promoted'] += $known && $move['to'] > $move['from'] ? 1 : 0;
            }
        }

        // Not known: once more at the end of the round, at most N times.
        // Repeats only help memorising; they never change the box.
        if (! $known && ($round['repeats'][$key] ?? 0) < (int) config('learning.round_repeats')) {
            $round['repeats'][$key] = ($round['repeats'][$key] ?? 0) + 1;
            $round['queue'][] = ['card' => $cardId, 'direction' => $current['direction']->value, 'repeat' => true, 'runs' => $item['runs'] ?? []];
        }

        return $round;
    }

    /**
     * Number of the card that is up, for "3 / 12" (repeats do not count).
     *
     * @param  Round  $round
     */
    public function position(array $round): int
    {
        return min($round['answered'] + 1, max(1, $round['total']));
    }

    // -------------------------------------------------------------------------

    /**
     * Whether the card was edited after its last answer in any of the runs.
     *
     * @param  list<DeckRun>  $runs
     */
    private function changedSinceReview(Card $card, string $direction, array $runs): bool
    {
        if ($runs === []) {
            return false;
        }

        $last = CardProgress::query()
            ->whereIn('deck_run_id', array_map(fn (DeckRun $run) => $run->id, $runs))
            ->where('card_id', $card->id)
            ->where('direction', $direction)
            ->max('last_reviewed_at');

        return $last !== null && $card->updated_at->greaterThan(Carbon::parse((string) $last));
    }

    /**
     * Progress rows that are due today (box 1 … N−1, due date reached).
     *
     * @return Builder<CardProgress>
     */
    private function dueQuery(User $user): Builder
    {
        [$active, $bindings] = $this->selection->activeCondition($user);
        [$learned, $learnedBindings] = $this->preferences->directionCondition($user);

        return CardProgress::query()
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->join('deck_runs', 'deck_runs.id', '=', 'card_progress.deck_run_id')
            ->where('card_progress.user_id', $user->id)
            ->where('card_progress.box', '>=', 1)
            ->whereColumn('card_progress.box', '<', 'deck_runs.box_count')
            ->where('card_progress.due_on', '<=', $this->leitner->today()->toDateString())
            // Paused cards keep their progress but are not due (issue #10).
            ->whereRaw($active, $bindings)
            // Back → front switched off: kept, but not due (einfacher-lernen.md, C).
            ->whereRaw($learned, $learnedBindings);
    }

    /**
     * @param  list<RoundItem>  $items
     * @return Round
     */
    private function newRound(string $mode, ?string $deckId, array $items): array
    {
        return [
            'mode' => $mode,
            'deck' => $deckId,
            'queue' => $items,
            'step' => 0,
            'total' => count($items),
            'answered' => 0,
            'known' => 0,
            'first' => 0,
            'promoted' => 0,
            'repeats' => [],
        ];
    }
}
