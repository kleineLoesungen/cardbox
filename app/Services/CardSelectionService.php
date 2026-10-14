<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Learning only part of a deck (issue #10). A person may pause cards of any
 * deck they can learn, own or followed; paused cards stay in the deck and
 * keep their progress, they just do not come up and do not count.
 *
 * * No selection (the default): every card is active.
 * * With a selection only its cards are active — cards the owner adds later
 *   stay paused until switched on (the next chapter of a book).
 * * Ticking every card ends the selection, so new cards are active again.
 *
 * The rule "this card is active for this person" lives here once, as an SQL
 * condition (activeCondition) for the learning queries.
 */
class CardSelectionService
{
    public function __construct(private DeckService $decks) {}

    /**
     * SQL condition "the card is active for the person", for queries that
     * have the card's id and deck id at hand.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function activeCondition(User $user, string $cardId = 'cards.id', string $deckId = 'cards.deck_id'): array
    {
        return [
            "(not exists (select 1 from deck_selections sel where sel.user_id = ? and sel.deck_id = {$deckId})
              or exists (select 1 from deck_selection_cards act where act.user_id = ? and act.card_id = {$cardId}))",
            [$user->id, $user->id],
        ];
    }

    /** Whether the person learns only a selection of the deck. */
    public function isSelective(User $user, Deck $deck): bool
    {
        return DB::table('deck_selections')->where('user_id', $user->id)->where('deck_id', $deck->id)->exists();
    }

    /**
     * Ids of the active cards, or null when every card is active.
     *
     * @return list<string>|null
     */
    public function activeIds(User $user, Deck $deck): ?array
    {
        if (! $this->isSelective($user, $deck)) {
            return null;
        }

        return DB::table('deck_selection_cards')
            ->where('user_id', $user->id)
            ->where('deck_id', $deck->id)
            ->pluck('card_id')
            ->all();
    }

    /**
     * Number of active cards per deck, for decks with a selection only.
     *
     * @param  list<string>  $deckIds
     * @return array<string, int>
     */
    public function activeCounts(User $user, array $deckIds): array
    {
        if ($deckIds === []) {
            return [];
        }

        $selective = DB::table('deck_selections')->where('user_id', $user->id)->whereIn('deck_id', $deckIds)->pluck('deck_id')->all();
        $counts = DB::table('deck_selection_cards')
            ->where('user_id', $user->id)
            ->whereIn('deck_id', $selective)
            ->groupBy('deck_id')
            ->selectRaw('deck_id, count(*) as n')
            ->pluck('n', 'deck_id');

        return collect($selective)->mapWithKeys(fn (string $id) => [$id => (int) ($counts[$id] ?? 0)])->all();
    }

    /**
     * The deck's cards in book order (oldest first), for the selection page.
     *
     * @return Collection<int, Card>
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function cardsInOrder(User $user, string $deckId): Collection
    {
        return $this->decks->getVisible($user, $deckId)->cards()->orderBy('created_at')->orderBy('id')->get();
    }

    /**
     * Saves which cards are active. All of them → no selection any more (new
     * cards become active again); none → refused, the deck would be empty.
     *
     * @param  array<mixed>  $cardIds  straight from the form; anything else is ignored
     *
     * @throws ModelNotFoundException<Deck>
     * @throws ValidationException
     */
    public function select(User $user, string $deckId, array $cardIds): void
    {
        $deck = $this->decks->getVisible($user, $deckId);
        $all = $deck->cards()->pluck('id')->all();
        $chosen = array_values(array_intersect($all, array_filter($cardIds, fn ($id) => is_string($id) && Str::isUuid($id))));

        if ($chosen === []) {
            throw ValidationException::withMessages(['cards' => 'Bitte mindestens eine Karte wählen.']);
        }

        DB::transaction(function () use ($user, $deck, $all, $chosen) {
            $this->clear($user, $deck);
            if (count($chosen) === count($all)) {
                return;
            }

            DB::table('deck_selections')->insert(['user_id' => $user->id, 'deck_id' => $deck->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_chunk($chosen, 500) as $chunk) {
                DB::table('deck_selection_cards')->insert(array_map(
                    fn (string $card) => ['user_id' => $user->id, 'deck_id' => $deck->id, 'card_id' => $card],
                    $chunk,
                ));
            }
        });
    }

    /**
     * "Alle Karten lernen": ends the selection.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function selectAll(User $user, string $deckId): void
    {
        $this->clear($user, $this->decks->getVisible($user, $deckId));
    }

    /**
     * "Folgen und diese Karte lernen" (dictionary): follows a shared deck of
     * somebody else and makes sure this card is active. Following a deck for
     * the first time this way starts a selection with just this card; a deck
     * already learned keeps what it had and gets the card added.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function learnCard(User $user, string $cardId): Card
    {
        if (! Str::isUuid($cardId)) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$cardId]);
        }
        $card = Card::with('deck')->whereIn('deck_id', $this->decks->visibleTo($user)->select('id'))->findOrFail($cardId);
        $deck = $card->deck;

        DB::transaction(function () use ($user, $card, $deck) {
            $learnedBefore = $deck->isOwnedBy($user) || $this->decks->isFollowing($user, $deck);
            if (! $learnedBefore) {
                $this->decks->follow($user, $deck->id);
                DB::table('deck_selections')->insertOrIgnore(['user_id' => $user->id, 'deck_id' => $deck->id, 'created_at' => now(), 'updated_at' => now()]);
            }
            if ($this->isSelective($user, $deck)) {
                DB::table('deck_selection_cards')->insertOrIgnore(['user_id' => $user->id, 'deck_id' => $deck->id, 'card_id' => $card->id]);
            }
        });

        return $card;
    }

    /** Whether the card is active for the person. */
    public function isActive(User $user, Card $card): bool
    {
        $ids = $this->activeIds($user, $card->deck);

        return $ids === null || in_array($card->id, $ids, true);
    }

    private function clear(User $user, Deck $deck): void
    {
        DB::table('deck_selection_cards')->where('user_id', $user->id)->where('deck_id', $deck->id)->delete();
        DB::table('deck_selections')->where('user_id', $user->id)->where('deck_id', $deck->id)->delete();
    }
}
