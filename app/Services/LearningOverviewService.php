<?php

namespace App\Services;

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for "Heute" and the deck page: what is due, how far each deck
 * is, which cards sit in which box. Read-only.
 *
 * Progress belongs to deck runs. "Due" counts each card and direction once,
 * even when it is due in two runs (docs/specs/lernplaene-und-karten.md, A5).
 */
class LearningOverviewService
{
    /** One card in one direction, for counting it once across runs. */
    private const PAIR = "card_progress.card_id::text || '|' || card_progress.direction";

    public function __construct(
        private DeckService $decks,
        private LeitnerService $leitner,
        private DeckRunService $runs,
        private CardSelectionService $selection,
        private DeckPreferenceService $preferences,
        private StudyRoundService $rounds,
    ) {}

    /**
     * Due today and tomorrow across "Meine Stapel", plus the streak. `new`:
     * new cards "Los geht's" adds today by itself; `more_new`: further new
     * cards for "Noch 20 neue Karten".
     *
     * @return array{due: int, tomorrow: int, streak: int, new: int, more_new: int}
     */
    public function today(User $user): array
    {
        $today = $this->leitner->today();

        $row = $this->learningRows($user)
            ->whereIn('cards.deck_id', $this->decks->mineQuery($user)->select('id'))
            ->selectRaw('count(distinct '.self::PAIR.') filter (where card_progress.due_on <= ?) as due', [$today->toDateString()])
            ->selectRaw('count(distinct '.self::PAIR.') filter (where card_progress.due_on = ?) as tomorrow', [$today->addDay()->toDateString()])
            ->first();

        // Enough to show today's share and "Noch 20 neue Karten" — never more.
        $available = $this->rounds->newCardCount($user, $this->decks->mineQuery($user)->pluck('id')->all(),
            (int) config('learning.new_per_day') + (int) config('learning.round_size'));
        $new = min($available, $this->rounds->newAllowedToday($user));

        return [
            'due' => (int) ($row->due ?? 0),
            'tomorrow' => (int) ($row->tomorrow ?? 0),
            'streak' => $this->leitner->streak($user),
            'new' => $new,
            'more_new' => $available - $new,
        ];
    }

    /**
     * "Meine Stapel" for "Heute": decks with due cards first, then own decks,
     * then by name — together with their stats.
     *
     * @return array{0: Collection<int, Deck>, 1: array<string, array{due: int, mastered_percent: int}>}
     */
    public function myDecksWithStats(User $user): array
    {
        $decks = $this->decks->mine($user);
        $stats = $this->deckStats($user, $decks);

        $sorted = $decks->sortBy(fn (Deck $deck) => [
            $stats[$deck->id]['due'] > 0 ? 0 : 1,
            $deck->user_id === $user->id ? 0 : 1,
            mb_strtolower($deck->name),
        ])->values();

        return [$sorted, $stats];
    }

    /**
     * Per deck: cards due today (each card and direction once) and the share
     * of mastered cards over the directions already started — the best of
     * the deck's runs. Two queries for all decks.
     *
     * @param  Collection<int, Deck>  $decks
     * @return array<string, array{due: int, mastered_percent: int}>
     */
    public function deckStats(User $user, Collection $decks): array
    {
        if ($decks->isEmpty()) {
            return [];
        }

        $today = $this->leitner->today()->toDateString();

        $due = $this->learningRows($user)
            ->whereIn('cards.deck_id', $decks->modelKeys())
            ->where('card_progress.due_on', '<=', $today)
            ->groupBy('cards.deck_id')
            ->select('cards.deck_id')
            ->selectRaw('count(distinct '.self::PAIR.') as due')
            ->pluck('due', 'deck_id');

        // Grouped by deck_runs.id (its primary key), so box_count may be used.
        [$active, $bindings] = $this->selection->activeCondition($user);
        $runs = DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->join('deck_runs', 'deck_runs.id', '=', 'card_progress.deck_run_id')
            ->where('card_progress.user_id', $user->id)
            ->whereRaw($active, $bindings)
            ->whereIn('cards.deck_id', $decks->modelKeys())
            ->groupBy('cards.deck_id', 'deck_runs.id')
            ->select('cards.deck_id')
            ->selectRaw('count(*) filter (where card_progress.box >= deck_runs.box_count) as mastered')
            ->selectRaw('count(distinct card_progress.direction) as directions')
            ->get()
            ->groupBy('deck_id');

        // Decks learned only in part count their active cards (issue #10).
        $activeCounts = $this->selection->activeCounts($user, $decks->modelKeys());

        $stats = [];
        foreach ($decks as $deck) {
            $cards = $activeCounts[$deck->id] ?? (int) ($deck->cards_count ?? 0);
            $percent = 0;
            foreach ($runs->get($deck->id, []) as $row) {
                $possible = $cards * max(1, (int) $row->directions);
                if ($possible > 0) {
                    $percent = max($percent, (int) floor(100 * (int) $row->mastered / $possible));
                }
            }

            $stats[$deck->id] = [
                'due' => (int) ($due[$deck->id] ?? 0),
                'mastered_percent' => min(100, $percent),
            ];
        }

        return $stats;
    }

    /**
     * Box counts of one run in one direction for the box bar:
     * new, box 1 … N−1, mastered; plus what a round would take now.
     * Without a run (deck not started yet) every card is new and the boxes
     * are those of the standard plan.
     *
     * @return array{boxes: array<int, int>, box_count: int, due: int, new: int, started: bool}
     */
    public function boxes(User $user, Deck $deck, ?DeckRun $run, Direction $direction, ?int $cardCount = null): array
    {
        $boxCount = $run->box_count ?? $this->leitner->plan($user)['box_count'];
        $today = $this->leitner->today()->toDateString();

        [$active, $bindings] = $this->selection->activeCondition($user);
        $rows = $run === null ? collect() : DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->where('card_progress.deck_run_id', $run->id)
            ->whereRaw($active, $bindings)
            ->where('card_progress.direction', $direction->value)
            ->select('card_progress.box')
            ->selectRaw('count(*) as cards')
            ->selectRaw('count(*) filter (where card_progress.box between 1 and ? and card_progress.due_on <= ?) as due', [$boxCount - 1, $today])
            ->groupBy('card_progress.box')
            ->get();

        // Boxes above N cannot happen (plans are fixed); count them as mastered anyway.
        $boxes = array_fill(0, $boxCount + 1, 0);
        $due = 0;
        foreach ($rows as $row) {
            $boxes[min((int) $row->box, $boxCount)] += (int) $row->cards;
            $due += (int) $row->due;
        }

        $total = $cardCount ?? $deck->cards()->count();
        $boxes[0] = max(0, $total - array_sum($boxes));

        return [
            'boxes' => $boxes,
            'box_count' => $boxCount,
            'due' => $due,
            'new' => $boxes[0],
            'started' => $total > $boxes[0],
        ];
    }

    /**
     * Cards in one box of a run, for the sheet behind the box bar. Box 0
     * means new (never answered in this direction), box N "Kann ich".
     * `days` counts calendar days until due (0 = today), comparing plain
     * dates so time zones cannot shift it. Without a run id the deck's
     * oldest run is meant.
     *
     * @return array{run: DeckRun, entries: list<array{card: Card, days: int|null}>}
     *
     * @throws ModelNotFoundException<Deck|DeckRun>
     */
    public function cardsInBox(User $user, string $deckId, ?string $runId, Direction $direction, int $box): array
    {
        $deck = $this->decks->getVisible($user, $deckId);
        $run = $runId !== null && $runId !== ''
            ? $this->runs->getOwned($user, $deck->id, $runId)
            : $this->runs->listOwned($user, $deck)->first();
        if ($run === null || $box < 0 || $box > $run->box_count) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$deckId]);
        }
        $boxCount = $run->box_count;
        $today = CarbonImmutable::parse($this->leitner->today()->toDateString(), 'UTC');

        $progress = CardProgress::query()
            ->where('deck_run_id', $run->id)
            ->where('direction', $direction->value)
            ->get()
            ->keyBy('card_id');

        $activeIds = $this->selection->activeIds($user, $deck);
        $result = [];
        foreach ($deck->cards()->orderBy('created_at')->get() as $card) {
            if ($activeIds !== null && ! in_array($card->id, $activeIds, true)) {
                continue;
            }
            $p = $progress->get($card->id);
            $cardBox = $p === null ? 0 : min($p->box, $boxCount);
            if ($cardBox === $box) {
                $card->setRelation('deck', $deck);
                $days = $p?->due_on === null
                    ? null
                    : (int) $today->diffInDays(CarbonImmutable::parse($p->due_on->toDateString(), 'UTC'), false);
                $result[] = ['card' => $card, 'days' => $days];
            }
        }

        // Soonest due first; new and mastered cards have no date.
        usort($result, fn (array $a, array $b) => ($a['days'] ?? 0) <=> ($b['days'] ?? 0));

        return ['run' => $run, 'entries' => $result];
    }

    /**
     * The person's progress rows that are in learning (box 1 … N−1 of their
     * run), joined with cards and runs.
     */
    private function learningRows(User $user): Builder
    {
        [$active, $bindings] = $this->selection->activeCondition($user);
        [$learned, $learnedBindings] = $this->preferences->directionCondition($user);

        return DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->join('deck_runs', 'deck_runs.id', '=', 'card_progress.deck_run_id')
            ->where('card_progress.user_id', $user->id)
            ->where('card_progress.box', '>=', 1)
            ->whereColumn('card_progress.box', '<', 'deck_runs.box_count')
            ->whereRaw($active, $bindings)
            ->whereRaw($learned, $learnedBindings);
    }
}
