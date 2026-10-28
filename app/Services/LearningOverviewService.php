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
 *
 * @phpstan-type Progress array{new: int, learning: int, almost: int, mastered: int, total: int, mastered_percent: float, almost_percent: float, learning_percent: float}
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
     * Due today and tomorrow across the active decks (paused ones left out,
     * issue #15), plus the streak. `new`:
     * new cards "Los geht's" adds today by itself; `more_new`: further new
     * cards for "Noch 20 neue Karten".
     *
     * `answers`, `goal`, `goal_met`: today's daily goal (motivierender-start.md, B).
     * `rescue`: yesterday was missed, twice the goal today keeps the streak
     * (LeitnerService::streakStatus, issue #27).
     *
     * @return array{due: int, tomorrow: int, streak: int, rescue: array{days: int, target: int, answers: int}|null, new: int, more_new: int, answers: int, goal: int, goal_met: bool, round_size: int}
     */
    public function today(User $user): array
    {
        $today = $this->leitner->today();

        $row = $this->learningRows($user)
            ->whereIn('cards.deck_id', $this->decks->activeQuery($user)->select('id'))
            ->selectRaw('count(distinct '.self::PAIR.') filter (where card_progress.due_on <= ?) as due', [$today->toDateString()])
            ->selectRaw('count(distinct '.self::PAIR.') filter (where card_progress.due_on = ?) as tomorrow', [$today->addDay()->toDateString()])
            ->first();

        // Enough to show today's share and "Noch 20 neue Karten" — never more.
        $available = $this->rounds->newCardCount($user, $this->decks->activeQuery($user)->pluck('id')->all(),
            (int) config('learning.new_per_day') + $this->leitner->roundSize($user));
        $new = min($available, $this->rounds->newAllowedToday($user));
        $streak = $this->leitner->streakStatus($user);

        return [
            'due' => (int) ($row->due ?? 0),
            'tomorrow' => (int) ($row->tomorrow ?? 0),
            'streak' => $streak['days'],
            'rescue' => $streak['rescue'],
            'new' => $new,
            'more_new' => $available - $new,
            ...$this->leitner->dayStatus($user),
            'round_size' => $this->leitner->roundSize($user),
        ];
    }

    /**
     * "Knifflige Wörter" of a deck (runden-und-knifflige-woerter.md, B):
     * active cards the person got wrong at least "hard_wrong" times in a
     * learned direction and run, and not yet in "Kann ich" there.
     *
     * @return list<string>
     */
    public function hardCardIds(User $user, Deck $deck): array
    {
        return $this->hardRows($user)
            ->where('cards.deck_id', $deck->id)
            ->distinct()
            ->pluck('cards.id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    /**
     * "Knifflige Wörter" across the active decks, most often wrong first, for
     * the parents' view (eltern-ansicht.md, B5).
     *
     * @return list<array{word: string, deck: string}>
     */
    public function hardWords(User $user, int $limit): array
    {
        $ids = $this->hardRows($user)
            ->whereIn('cards.deck_id', $this->decks->activeQuery($user)->select('id'))
            ->groupBy('cards.id')
            ->select('cards.id')
            ->orderByRaw('max(card_progress.review_count - card_progress.correct_count) desc')
            ->orderBy('cards.id')
            ->limit($limit)
            ->pluck('cards.id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $cards = Card::with('deck')->whereKey($ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(fn (string $id) => ($card = $cards->get($id)) === null ? null : [
            'word' => $card->frontText().' – '.$card->backText(),
            'deck' => $card->deck->name,
        ], $ids)));
    }

    /**
     * The cards that got to "Kann ich" last, as "front – back", for the
     * parents' view (eltern-ansicht.md, B3). Like vocabulary(), but only the
     * active decks.
     *
     * @return list<string>
     */
    public function recentlyMastered(User $user, int $limit): array
    {
        [$active, $bindings] = $this->selection->activeCondition($user);
        [$learned, $learnedBindings] = $this->preferences->directionCondition($user);

        $ids = DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->join('deck_runs', 'deck_runs.id', '=', 'card_progress.deck_run_id')
            ->where('card_progress.user_id', $user->id)
            ->whereColumn('card_progress.box', '>=', 'deck_runs.box_count')
            ->whereIn('cards.deck_id', $this->decks->activeQuery($user)->select('id'))
            ->whereRaw($active, $bindings)
            ->whereRaw($learned, $learnedBindings)
            ->groupBy('card_progress.card_id')
            ->select('card_progress.card_id')
            ->orderByRaw('max(card_progress.last_reviewed_at) desc nulls last')
            ->orderBy('card_progress.card_id')
            ->limit($limit)
            ->pluck('card_progress.card_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $cards = Card::whereKey($ids)->get()->keyBy('id');

        return array_values(array_filter(array_map(
            fn (string $id) => ($card = $cards->get($id)) === null ? null : $card->frontText().' – '.$card->backText(),
            $ids,
        )));
    }

    /**
     * The growing vocabulary on "Heute" (motivierender-start.md, C): cards
     * in "Kann ich" in at least one learned direction and run, those of
     * them that got there in the last 7 days, and cards with any progress.
     * Only own and followed decks, only active cards.
     *
     * @return array{mastered: int, mastered_week: int, in_box: int}
     */
    public function vocabulary(User $user): array
    {
        [$active, $bindings] = $this->selection->activeCondition($user);
        [$learned, $learnedBindings] = $this->preferences->directionCondition($user);

        $row = DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->join('deck_runs', 'deck_runs.id', '=', 'card_progress.deck_run_id')
            ->where('card_progress.user_id', $user->id)
            ->where('card_progress.box', '>=', 1)
            ->whereIn('cards.deck_id', $this->decks->mineQuery($user)->select('id'))
            ->whereRaw($active, $bindings)
            ->whereRaw($learned, $learnedBindings)
            ->selectRaw('count(distinct card_progress.card_id) filter (where card_progress.box >= deck_runs.box_count) as mastered')
            ->selectRaw('count(distinct card_progress.card_id) filter (where card_progress.box >= deck_runs.box_count and card_progress.last_reviewed_at >= ?) as mastered_week', [now()->subDays(7)])
            ->selectRaw('count(distinct card_progress.card_id) as in_box')
            ->first();

        return [
            'mastered' => (int) ($row->mastered ?? 0),
            'mastered_week' => (int) ($row->mastered_week ?? 0),
            'in_box' => (int) ($row->in_box ?? 0),
        ];
    }

    /**
     * "Meine Stapel" for "Heute": the active decks (paused ones are left out,
     * issue #15), due cards first, then own decks, then by name — together
     * with their stats.
     *
     * @return array{0: Collection<int, Deck>, 1: array<string, array{due: int, mastered_percent: int}>}
     */
    public function myDecksWithStats(User $user): array
    {
        $decks = $this->decks->active($user);
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
     * Per deck how many active cards are new, in the box, in the box right
     * before "Kann ich" (`almost`, issue #26) and in "Kann ich" — for the bar
     * on "Heute" and in the parents' view (issue #18). A card counts by its
     * best state over the learned directions and runs, like vocabulary().
     *
     * @param  Collection<int, Deck>  $decks
     * @return array<string, Progress>
     */
    public function deckProgress(User $user, Collection $decks): array
    {
        if ($decks->isEmpty()) {
            return [];
        }

        [$active, $bindings] = $this->selection->activeCondition($user);
        [$learned, $learnedBindings] = $this->preferences->directionCondition($user);
        // Per card its best level: 2 "Kann ich", 1 the box before, 0 in the box.
        $levels = DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->join('deck_runs', 'deck_runs.id', '=', 'card_progress.deck_run_id')
            ->where('card_progress.user_id', $user->id)
            ->where('card_progress.box', '>=', 1)
            ->whereIn('cards.deck_id', $decks->modelKeys())
            ->whereRaw($active, $bindings)
            ->whereRaw($learned, $learnedBindings)
            ->groupBy('cards.deck_id', 'card_progress.card_id')
            ->select('cards.deck_id')
            // "Almost" needs a box before it: with two boxes, box 1 is just "in the box".
            ->selectRaw('max(case when card_progress.box >= deck_runs.box_count then 2 when deck_runs.box_count > 2 and card_progress.box = deck_runs.box_count - 1 then 1 else 0 end) as level');
        $rows = DB::query()->fromSub($levels, 'levels')
            ->groupBy('deck_id')
            ->select('deck_id')
            ->selectRaw('count(*) filter (where level = 2) as mastered')
            ->selectRaw('count(*) filter (where level = 1) as almost')
            ->selectRaw('count(*) as touched')
            ->get()
            ->keyBy('deck_id');
        $activeCounts = $this->selection->activeCounts($user, $decks->modelKeys());

        $result = [];
        foreach ($decks as $deck) {
            $total = $activeCounts[$deck->id] ?? (int) ($deck->cards_count ?? 0);
            $row = $rows->get($deck->id);
            $mastered = min($total, (int) ($row->mastered ?? 0));
            $almost = min($total - $mastered, (int) ($row->almost ?? 0));
            $learning = max(0, min($total - $mastered - $almost, (int) ($row->touched ?? 0) - (int) ($row->mastered ?? 0) - (int) ($row->almost ?? 0)));
            $percent = fn (int $n) => $total > 0 ? round(100 * $n / $total, 1) : 0.0;
            $result[$deck->id] = [
                'new' => $total - $mastered - $almost - $learning,
                'learning' => $learning,
                'almost' => $almost,
                'mastered' => $mastered,
                'total' => $total,
                'mastered_percent' => $percent($mastered),
                'almost_percent' => $percent($almost),
                'learning_percent' => $percent($learning),
            ];
        }

        return $result;
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

    /** Progress rows that make a card "knifflig", joined with cards and runs. */
    private function hardRows(User $user): Builder
    {
        [$active, $bindings] = $this->selection->activeCondition($user);
        [$learned, $learnedBindings] = $this->preferences->directionCondition($user);

        return DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->join('deck_runs', 'deck_runs.id', '=', 'card_progress.deck_run_id')
            ->where('card_progress.user_id', $user->id)
            ->whereRaw('card_progress.review_count - card_progress.correct_count >= ?', [(int) config('learning.hard_wrong')])
            ->whereColumn('card_progress.box', '<', 'deck_runs.box_count')
            ->whereRaw($active, $bindings)
            ->whereRaw($learned, $learnedBindings);
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
