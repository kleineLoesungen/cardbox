<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cards inside decks.
 *
 * Every card belongs to exactly one deck. Cards are only ever created,
 * changed and deleted inside the user's own decks; cards from other decks
 * are taken over as copies (adopt), never as references.
 */
class CardService
{
    public function __construct(private DeckService $decks) {}

    /**
     * @param  array<string, mixed>  $input  front[], back[], hint
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function createOwned(User $user, string $deckId, array $input): Card
    {
        $deck = $this->decks->getOwned($user, $deckId);

        $card = new Card($this->validate($input));
        $card->deck_id = $deck->id;
        $card->save();
        $card->setRelation('deck', $deck);

        return $card;
    }

    /**
     * A card in one of the user's own decks.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function getOwned(User $user, string $deckId, string $cardId): Card
    {
        $deck = $this->decks->getOwned($user, $deckId);
        $this->ensureUuid($cardId);

        $card = $deck->cards()->findOrFail($cardId);
        $card->setRelation('deck', $deck);

        return $card;
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function updateOwned(User $user, string $deckId, string $cardId, array $input): Card
    {
        $card = $this->getOwned($user, $deckId, $cardId);
        $card->update($this->validate($input));

        return $card;
    }

    // -------------------------------------------------------------------------
    // Moderation — system-wide, but only on SHARED decks (like DeckService).
    // Used only behind the role:moderator routes; never in owner controllers.
    // -------------------------------------------------------------------------

    /**
     * A card in any shared deck.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function getShared(string $deckId, string $cardId): Card
    {
        $deck = $this->decks->getShared($deckId);
        $this->ensureUuid($cardId);

        $card = $deck->cards()->findOrFail($cardId);
        $card->setRelation('deck', $deck);

        return $card;
    }

    /**
     * Moderation corrects a card in somebody else's shared deck, with the
     * same checks as an edit by the owner.
     *
     * @param  array<string, mixed>  $input  front[], back[], hint
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function updateShared(string $deckId, string $cardId, array $input): Card
    {
        $card = $this->getShared($deckId, $cardId);
        $card->update($this->validate($input));

        return $card;
    }

    /**
     * Deletes the card together with everybody's progress on it — followers
     * of a shared deck included.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function deleteOwned(User $user, string $deckId, string $cardId): void
    {
        $this->getOwned($user, $deckId, $cardId)->delete();
    }

    /**
     * Cards of a deck the user may see, newest first.
     *
     * @return Collection<int, Card>
     */
    public function listForDeck(Deck $deck): Collection
    {
        return $deck->cards()->orderByDesc('created_at')->get();
    }

    /** Hint "Gibt es schon in diesem Stapel": same first front meaning, case-insensitive. */
    public function frontExists(Deck $deck, string $front): bool
    {
        $front = trim($front);

        return $front !== '' && $deck->cards()
            ->whereRaw('lower(front->>0) = lower(?)', [$front])
            ->exists();
    }

    /**
     * "Aus anderen Stapeln übernehmen": cards from the user's own decks and
     * from shared decks with the same language pair (either order). Cards
     * that only live in other people's private decks never show up.
     *
     * Results come ready for display: sides already swapped to fit the
     * target deck, and marked when the target already has that front.
     *
     * @return list<array{id: string, front: string, back: string, deck: string, exists: bool}>
     */
    public function searchToAdopt(User $user, string $deckId, string $term): array
    {
        $target = $this->decks->getOwned($user, $deckId);
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $pattern = '%'.addcslashes($term, '\\%_').'%';
        // Match single meanings, not the JSON text: no hits across meaning
        // boundaries or on quotes and commas.
        $matches = 'exists (select 1 from jsonb_array_elements_text(%s) m where m ilike ?)';

        $cards = Card::query()
            ->with('deck')
            ->whereIn('deck_id', $this->adoptableDecks($user, $target)->select('id'))
            ->where(fn (Builder $q) => $q
                ->whereRaw(sprintf($matches, 'front'), [$pattern])
                ->orWhereRaw(sprintf($matches, 'back'), [$pattern]))
            ->orderBy('created_at')
            ->limit((int) config('learning.search_limit'))
            ->get();

        $existing = $target->cards()->get()
            ->map(fn (Card $c) => mb_strtolower($c->frontText()))
            ->flip();

        return $cards->map(function (Card $card) use ($target, $existing) {
            $swap = $card->deck->front_language !== $target->front_language;
            $front = $swap ? $card->backText() : $card->frontText();

            return [
                'id' => $card->id,
                'front' => $front,
                'back' => $swap ? $card->frontText() : $card->backText(),
                'deck' => $card->deck->name,
                'exists' => $existing->has(mb_strtolower($front)),
            ];
        })->values()->all();
    }

    /**
     * Copy a visible card into one of the user's decks. With a reversed
     * language pair the sides are swapped, so the copy fits the target deck.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function adopt(User $user, string $deckId, string $cardId): Card
    {
        $target = $this->decks->getOwned($user, $deckId);
        $this->ensureUuid($cardId);

        $source = Card::with('deck')
            ->whereIn('deck_id', $this->adoptableDecks($user, $target)->select('id'))
            ->findOrFail($cardId);

        $swap = $source->deck->front_language !== $target->front_language;

        $copy = new Card([
            'front' => $swap ? $source->back : $source->front,
            'back' => $swap ? $source->front : $source->back,
            'hint' => $source->hint,
        ]);
        $copy->deck_id = $target->id;
        // Never user input, so not fillable.
        $copy->forceFill(['source_card_id' => $source->id, 'source_updated_at' => $source->updated_at]);
        $copy->save();
        $copy->setRelation('deck', $target);

        return $copy;
    }

    // -------------------------------------------------------------------------
    // Originals of copied cards (docs/specs/lernplaene-und-karten.md, B3).
    // Only for the owner of the copy, and only while the original is still
    // visible to them. A deleted or private original simply offers nothing.
    // -------------------------------------------------------------------------

    /**
     * Cards of an own deck whose original changed since it was copied or
     * last synced, with the original's content in the copy's orientation.
     *
     * @return list<array{card: Card, front: list<string>, back: list<string>, hint: string|null}>
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function pendingOriginalUpdates(User $user, string $deckId): array
    {
        $deck = $this->decks->getOwned($user, $deckId);

        $cards = $this->changedOriginalsQuery($user, $deck)->orderBy('cards.created_at')->get();
        $originals = Card::with('deck')->whereIn('id', $cards->pluck('source_card_id'))->get()->keyBy('id');

        $result = [];
        foreach ($cards as $card) {
            $original = $originals->get($card->source_card_id);
            if ($original !== null) {
                $card->setRelation('deck', $deck);
                $result[] = ['card' => $card, ...$this->contentFor($deck, $original)];
            }
        }

        return $result;
    }

    /** How many cards of an own deck have a changed original. */
    public function pendingOriginalCount(User $user, Deck $deck): int
    {
        return $deck->isOwnedBy($user) ? $this->changedOriginalsQuery($user, $deck)->count() : 0;
    }

    /**
     * "Übernehmen": the copy gets the original's sides and hint. Progress
     * stays — it belongs to the card, not to its text.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function takeOriginal(User $user, string $deckId, string $cardId): Card
    {
        [$card, $original] = $this->withVisibleOriginal($user, $deckId, $cardId);

        $card->fill($this->contentFor($card->deck, $original));
        $card->forceFill(['source_updated_at' => $original->updated_at])->save();

        return $card;
    }

    /**
     * "Behalten": the copy stays as it is; the notice is gone until the
     * original changes again.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function keepMine(User $user, string $deckId, string $cardId): Card
    {
        [$card, $original] = $this->withVisibleOriginal($user, $deckId, $cardId);

        // Only the sync mark; the copy's own updated_at stays untouched.
        $card->timestamps = false;
        $card->forceFill(['source_updated_at' => $original->updated_at])->save();

        return $card;
    }

    /**
     * "Alle übernehmen".
     *
     * @return int number of cards updated
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function takeAllOriginals(User $user, string $deckId): int
    {
        $pending = $this->pendingOriginalUpdates($user, $deckId);

        DB::transaction(function () use ($user, $deckId, $pending) {
            foreach ($pending as $entry) {
                $this->takeOriginal($user, $deckId, $entry['card']->id);
            }
        });

        return count($pending);
    }

    /**
     * Own cards whose visible original is newer than the last sync.
     *
     * @return Builder<Card>
     */
    private function changedOriginalsQuery(User $user, Deck $deck): Builder
    {
        return $deck->cards()->getQuery()
            ->select('cards.*')
            ->join('cards as originals', 'originals.id', '=', 'cards.source_card_id')
            ->whereIn('originals.deck_id', $this->decks->visibleTo($user)->select('id'))
            ->where(fn (Builder $q) => $q
                ->whereNull('cards.source_updated_at')
                ->orWhereColumn('originals.updated_at', '>', 'cards.source_updated_at'));
    }

    /**
     * An own card and its original, which must still be visible.
     *
     * @return array{0: Card, 1: Card}
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    private function withVisibleOriginal(User $user, string $deckId, string $cardId): array
    {
        $card = $this->getOwned($user, $deckId, $cardId);

        $original = $card->source_card_id === null ? null : Card::with('deck')
            ->whereIn('deck_id', $this->decks->visibleTo($user)->select('id'))
            ->find($card->source_card_id);
        if ($original === null) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$cardId]);
        }

        return [$card, $original];
    }

    /**
     * The original's content as it fits the copy's deck: sides swapped when
     * the languages are the other way round (as when adopting).
     *
     * @return array{front: list<string>, back: list<string>, hint: string|null}
     */
    private function contentFor(Deck $target, Card $original): array
    {
        $swap = $original->deck->front_language !== $target->front_language;

        return [
            'front' => $swap ? $original->back : $original->front,
            'back' => $swap ? $original->front : $original->back,
            'hint' => $original->hint,
        ];
    }

    /**
     * Normalises one card side: trimmed, empty entries dropped, exact
     * duplicates removed, order kept.
     *
     * @return list<string>
     */
    public static function normalizeSide(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $clean = [];
        foreach ($items as $item) {
            if (! is_scalar($item)) {
                continue;
            }
            $text = trim((string) $item);
            if ($text !== '' && ! in_array($text, $clean, true)) {
                $clean[] = $text;
            }
        }

        return $clean;
    }

    /**
     * Visible decks other than the target with the same language pair.
     *
     * @return Builder<Deck>
     */
    private function adoptableDecks(User $user, Deck $target): Builder
    {
        return $this->decks->visibleTo($user)
            ->whereKeyNot($target->id)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q
                    ->where('front_language', $target->front_language)
                    ->where('back_language', $target->back_language))
                ->orWhere(fn (Builder $q) => $q
                    ->where('front_language', $target->back_language)
                    ->where('back_language', $target->front_language)));
    }

    /**
     * The order of the meanings is kept: the first one of a side is its main
     * meaning. An empty hint is stored as null.
     *
     * @param  array<string, mixed>  $input
     * @return array{front: list<string>, back: list<string>, hint: string|null}
     *
     * @throws ValidationException
     */
    private function validate(array $input): array
    {
        $hint = $input['hint'] ?? null;
        $data = [
            'front' => self::normalizeSide($input['front'] ?? []),
            'back' => self::normalizeSide($input['back'] ?? []),
            // Anything but text (an array, say) stays as it is and fails "string".
            'hint' => is_string($hint) ? (trim($hint) === '' ? null : trim($hint)) : $hint,
        ];

        $max = (int) config('learning.max_meanings');
        $length = (int) config('learning.max_meaning_length');
        $hintLength = (int) config('learning.max_hint_length');

        Validator::make($data, [
            'front' => ['required', 'array', "max:{$max}"],
            'front.*' => ['string', "max:{$length}"],
            'back' => ['required', 'array', "max:{$max}"],
            'back.*' => ['string', "max:{$length}"],
            'hint' => ['nullable', 'string', "max:{$hintLength}"],
        ], [
            'hint.string' => 'Der Hinweis muss ein Text sein.',
            'hint.max' => "Der Hinweis darf höchstens {$hintLength} Zeichen haben.",
            'front.required' => 'Vorne fehlt noch ein Wort.',
            'back.required' => 'Hinten fehlt noch ein Wort.',
            'front.max' => "Höchstens {$max} Bedeutungen je Seite.",
            'back.max' => "Höchstens {$max} Bedeutungen je Seite.",
            'front.*.max' => "Eine Bedeutung darf höchstens {$length} Zeichen haben.",
            'back.*.max' => "Eine Bedeutung darf höchstens {$length} Zeichen haben.",
        ])->validate();

        return ['front' => $data['front'], 'back' => $data['back'], 'hint' => is_string($data['hint']) ? $data['hint'] : null];
    }

    private function ensureUuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$id]);
        }
    }
}
