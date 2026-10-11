<?php

namespace App\Services;

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A learning round: which cards, in which order, and what happens on an
 * answer. See docs/specs/vokabelkarten.md, "Lernrunde" and "Freies Üben".
 *
 * The round state is a plain array. This service never touches the session;
 * the controller stores the array and hands it back on the next request.
 *
 * @phpstan-type RoundItem array{card: string, direction: string, repeat: bool}
 * @phpstan-type Round array{mode: string, deck: string|null, queue: list<RoundItem>, step: int, total: int, answered: int, known: int, promoted: int, repeats: array<string, int>}
 */
class StudyRoundService
{
    public function __construct(private DeckService $decks, private LeitnerService $leitner) {}

    /**
     * Learning one deck in one direction: due cards first, then new ones.
     * Learning somebody else's shared deck follows it, so it shows up under
     * "Meine Stapel".
     *
     * @return Round
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function startForDeck(User $user, string $deckId, Direction $direction): array
    {
        $deck = $this->decks->getVisible($user, $deckId);
        if (! $deck->isOwnedBy($user)) {
            $user->followedDecks()->syncWithoutDetaching([$deck->id]);
        }

        $limit = (int) config('learning.round_size');
        $due = $this->dueQuery($user)
            ->where('card_progress.direction', $direction->value)
            ->where('cards.deck_id', $deck->id)
            ->orderBy('card_progress.due_on')
            ->limit($limit)
            ->pluck('card_progress.card_id')
            ->all();

        $new = $deck->cards()
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('card_progress')
                ->whereColumn('card_progress.card_id', 'cards.id')
                ->where('card_progress.user_id', $user->id)
                ->where('card_progress.direction', $direction->value))
            ->orderBy('created_at')
            ->limit($limit - count($due))
            ->pluck('id')
            ->all();

        $items = array_map(fn (string $id) => ['card' => $id, 'direction' => $direction->value, 'repeat' => false], [...$due, ...$new]);

        return $this->newRound('learn', $deck->id, $items);
    }

    /**
     * "Los geht's": due cards from all of the person's decks (own and
     * followed shared ones), oldest due date first. New cards are started
     * deliberately inside a deck, not here.
     *
     * @return Round
     */
    public function startToday(User $user): array
    {
        $items = $this->dueQuery($user)
            ->whereIn('cards.deck_id', $this->decks->mineQuery($user)->select('id'))
            ->orderBy('card_progress.due_on')
            ->orderBy('card_progress.card_id')
            ->limit((int) config('learning.round_size'))
            ->get(['card_progress.card_id', 'card_progress.direction'])
            ->map(fn (CardProgress $row) => ['card' => $row->card_id, 'direction' => $row->direction->value, 'repeat' => false])
            ->values()
            ->all();

        return $this->newRound('learn', null, $items);
    }

    /**
     * Free practice: random cards of a deck, nothing is saved.
     *
     * With `$box`, only the cards in that box of the chosen direction:
     * 0 = new (never answered), N = mastered ("Kann ich"). A box belongs to
     * one direction, so "mixed" does not combine with it.
     *
     * @param  string  $mode  forward, backward or mixed
     * @return Round
     *
     * @throws ModelNotFoundException<Deck>
     * @throws ValidationException
     */
    public function startPractice(User $user, string $deckId, string $mode, ?int $box = null): array
    {
        if (! in_array($mode, ['forward', 'backward', 'mixed'], true)) {
            throw ValidationException::withMessages(['direction' => 'Bitte eine Richtung wählen.']);
        }

        $deck = $this->decks->getVisible($user, $deckId);
        $cards = $deck->cards();

        if ($box !== null) {
            $boxCount = $this->leitner->plan($user)['box_count'];
            if ($mode === 'mixed' || $box < 0 || $box > $boxCount) {
                throw ValidationException::withMessages(['box' => 'Dieses Fach gibt es nicht.']);
            }

            $progress = fn ($q) => $q->select(DB::raw(1))
                ->from('card_progress')
                ->whereColumn('card_progress.card_id', 'cards.id')
                ->where('card_progress.user_id', $user->id)
                ->where('card_progress.direction', $mode);

            match (true) {
                $box === 0 => $cards->whereNotExists($progress),
                // Boxes above N (plan shortened later) count as mastered.
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
     * vanished meanwhile (deleted, deck no longer shared) are skipped
     * silently.
     *
     * @param  Round  $round
     * @return array{0: Round, 1: array{card: Card, direction: Direction, repeat: bool}|null}
     */
    public function current(User $user, array $round): array
    {
        while ($round['queue'] !== []) {
            $item = $round['queue'][0];
            $card = Card::with('deck')
                ->whereIn('deck_id', $this->decks->visibleTo($user)->select('id'))
                ->find($item['card']);

            if ($card !== null) {
                return [$round, ['card' => $card, 'direction' => Direction::from($item['direction']), 'repeat' => $item['repeat']]];
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

        array_shift($round['queue']);
        $round['step']++;
        $key = $cardId.'|'.$current['direction']->value;

        if (! $current['repeat']) {
            $round['answered']++;
            $round['known'] += $known ? 1 : 0;

            if ($round['mode'] === 'learn') {
                $move = $this->leitner->review($user, $current['card'], $current['direction'], $known);
                // A new card that was not known also lands in box 1 — that is
                // not moving up.
                $round['promoted'] += $known && $move['to'] > $move['from'] ? 1 : 0;
            }
        }

        // Not known: once more at the end of the round, at most N times.
        // Repeats only help memorising; they never change the box.
        if (! $known && ($round['repeats'][$key] ?? 0) < (int) config('learning.round_repeats')) {
            $round['repeats'][$key] = ($round['repeats'][$key] ?? 0) + 1;
            $round['queue'][] = ['card' => $cardId, 'direction' => $current['direction']->value, 'repeat' => true];
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
     * Progress rows that are due today (box 1 … N−1, due date reached).
     *
     * @return Builder<CardProgress>
     */
    private function dueQuery(User $user): Builder
    {
        $boxCount = $this->leitner->plan($user)['box_count'];

        return CardProgress::query()
            ->join('cards', 'cards.id', '=', 'card_progress.card_id')
            ->where('card_progress.user_id', $user->id)
            ->whereBetween('card_progress.box', [1, $boxCount - 1])
            ->where('card_progress.due_on', '<=', $this->leitner->today()->toDateString());
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
            'promoted' => 0,
            'repeats' => [],
        ];
    }
}
