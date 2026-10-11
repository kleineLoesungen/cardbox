<?php

namespace App\Services;

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for "Heute" and the deck page: what is due, how far each deck
 * is, which cards sit in which box. Read-only, except resetDeck().
 */
class LearningOverviewService
{
    public function __construct(private DeckService $decks, private LeitnerService $leitner) {}

    /**
     * Due today and tomorrow across "Meine Stapel", plus the streak.
     *
     * @return array{due: int, tomorrow: int, streak: int}
     */
    public function today(User $user): array
    {
        $boxCount = $this->leitner->plan($user)['box_count'];
        $today = $this->leitner->today();

        $row = DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->where('card_progress.user_id', $user->id)
            ->whereBetween('card_progress.box', [1, $boxCount - 1])
            ->whereIn('cards.deck_id', $this->decks->mineQuery($user)->select('id'))
            ->selectRaw('count(*) filter (where card_progress.due_on <= ?) as due', [$today->toDateString()])
            ->selectRaw('count(*) filter (where card_progress.due_on = ?) as tomorrow', [$today->addDay()->toDateString()])
            ->first();

        return [
            'due' => (int) ($row->due ?? 0),
            'tomorrow' => (int) ($row->tomorrow ?? 0),
            'streak' => $this->leitner->streak($user),
        ];
    }

    /** Number of the "Kann ich" box in the person's plan. */
    public function boxCount(User $user): int
    {
        return $this->leitner->plan($user)['box_count'];
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
     * Per deck: cards due today and the share of mastered cards over the
     * directions already started. One query for all decks.
     *
     * @param  Collection<int, Deck>  $decks
     * @return array<string, array{due: int, mastered_percent: int}>
     */
    public function deckStats(User $user, Collection $decks): array
    {
        if ($decks->isEmpty()) {
            return [];
        }

        $boxCount = $this->leitner->plan($user)['box_count'];
        $today = $this->leitner->today()->toDateString();

        $rows = DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->where('card_progress.user_id', $user->id)
            ->whereIn('cards.deck_id', $decks->modelKeys())
            ->groupBy('cards.deck_id')
            ->select('cards.deck_id')
            ->selectRaw('count(*) filter (where card_progress.box between 1 and ? and card_progress.due_on <= ?) as due', [$boxCount - 1, $today])
            ->selectRaw('count(*) filter (where card_progress.box >= ?) as mastered', [$boxCount])
            ->selectRaw('count(distinct card_progress.direction) as directions')
            ->get()
            ->keyBy('deck_id');

        $stats = [];
        foreach ($decks as $deck) {
            $row = $rows->get($deck->id);
            $cards = (int) ($deck->cards_count ?? 0);
            $possible = $cards * max(1, (int) ($row->directions ?? 0));

            $stats[$deck->id] = [
                'due' => (int) ($row->due ?? 0),
                'mastered_percent' => $possible > 0 ? (int) floor(100 * (int) ($row->mastered ?? 0) / $possible) : 0,
            ];
        }

        return $stats;
    }

    /**
     * Box counts of one deck in one direction for the box bar:
     * new, box 1 … N−1, mastered; plus what a round would take now.
     *
     * @return array{boxes: array<int, int>, box_count: int, due: int, new: int, started: bool}
     */
    public function boxes(User $user, Deck $deck, Direction $direction, ?int $cardCount = null): array
    {
        $boxCount = $this->leitner->plan($user)['box_count'];
        $today = $this->leitner->today()->toDateString();

        $rows = DB::table('card_progress')
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->where('card_progress.user_id', $user->id)
            ->where('cards.deck_id', $deck->id)
            ->where('card_progress.direction', $direction->value)
            ->select('card_progress.box')
            ->selectRaw('count(*) as cards')
            ->selectRaw('count(*) filter (where card_progress.box between 1 and ? and card_progress.due_on <= ?) as due', [$boxCount - 1, $today])
            ->groupBy('card_progress.box')
            ->get();

        // Boxes above N (after the plan was shortened) count as mastered.
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
     * Cards in one box of a deck, for the sheet behind the box bar. Box 0
     * means new (never answered in this direction), box N "Kann ich".
     * `days` counts calendar days until due (0 = today), comparing plain
     * dates so time zones cannot shift it.
     *
     * @return list<array{card: Card, days: int|null}>
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function cardsInBox(User $user, string $deckId, Direction $direction, int $box): array
    {
        $deck = $this->decks->getVisible($user, $deckId);
        $boxCount = $this->leitner->plan($user)['box_count'];
        if ($box < 0 || $box > $boxCount) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$deckId]);
        }
        $today = CarbonImmutable::parse($this->leitner->today()->toDateString(), 'UTC');

        $progress = CardProgress::query()
            ->where('user_id', $user->id)
            ->where('direction', $direction->value)
            ->whereIn('card_id', $deck->cards()->select('id'))
            ->get()
            ->keyBy('card_id');

        $result = [];
        foreach ($deck->cards()->orderBy('created_at')->get() as $card) {
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

        return $result;
    }

    /**
     * "Fortschritt zurücksetzen": deletes the user's own progress on the
     * deck's cards, both directions. Other people's progress is untouched.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function resetDeck(User $user, string $deckId): int
    {
        $deck = $this->decks->getVisible($user, $deckId);

        return CardProgress::query()
            ->where('user_id', $user->id)
            ->whereIn('card_id', $deck->cards()->select('id'))
            ->delete();
    }
}
