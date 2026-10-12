<?php

namespace App\Services;

use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use App\Models\Card;
use App\Models\CardSuggestion;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Fehler melden": suggestions to the owner of a shared deck. See
 * docs/specs/lernplaene-und-karten.md, B5.
 *
 * * Only cards in other people's shared decks can get a suggestion; own
 *   cards and private decks answer 404.
 * * At most one open suggestion per person and card.
 * * Only the owner decides, and only the owner changes the card.
 */
class CardSuggestionService
{
    private const TOO_MANY = 'Du hast schon einen Vorschlag zu dieser Karte.';

    public function __construct(private DeckService $decks, private CardService $cards) {}

    /**
     * A card the user may make a suggestion for.
     *
     * @throws ModelNotFoundException<Deck|Card>
     */
    public function getSuggestable(User $user, string $deckId, string $cardId): Card
    {
        $deck = $this->decks->getVisible($user, $deckId);
        if ($deck->isOwnedBy($user) || ! Str::isUuid($cardId)) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$cardId]);
        }

        $card = $deck->cards()->findOrFail($cardId);
        $card->setRelation('deck', $deck);

        return $card;
    }

    /** The user's open suggestion for a card, if any. */
    public function openFor(User $user, Card $card): ?CardSuggestion
    {
        return CardSuggestion::where('card_id', $card->id)
            ->where('user_id', $user->id)
            ->where('status', SuggestionStatus::Open)
            ->first();
    }

    /**
     * Sends a suggestion. New text for a side counts only when it differs
     * from the card; at least new text or a comment is needed.
     *
     * @param  array<string, mixed>  $input  type, front[], back[], comment
     *
     * @throws ModelNotFoundException<Deck|Card>
     * @throws ValidationException
     */
    public function createFor(User $user, string $deckId, string $cardId, array $input): CardSuggestion
    {
        $card = $this->getSuggestable($user, $deckId, $cardId);

        $front = CardService::normalizeSide($input['front'] ?? []);
        $back = CardService::normalizeSide($input['back'] ?? []);
        $comment = $input['comment'] ?? null;
        $data = [
            'type' => $input['type'] ?? null,
            // Unchanged or emptied sides propose nothing.
            'proposed_front' => $front === [] || $front === $card->front ? null : $front,
            'proposed_back' => $back === [] || $back === $card->back ? null : $back,
            'comment' => is_string($comment) ? (trim($comment) === '' ? null : trim($comment)) : $comment,
        ];

        $max = (int) config('learning.max_meanings');
        $length = (int) config('learning.max_meaning_length');
        $commentLength = (int) config('learning.max_suggestion_comment');

        $validator = Validator::make($data, [
            'type' => ['required', Rule::enum(SuggestionType::class)],
            'proposed_front' => ['nullable', 'array', "max:{$max}"],
            'proposed_front.*' => ['string', "max:{$length}"],
            'proposed_back' => ['nullable', 'array', "max:{$max}"],
            'proposed_back.*' => ['string', "max:{$length}"],
            'comment' => ['nullable', 'string', "max:{$commentLength}"],
        ], [
            'type.required' => 'Bitte wählen, worum es geht.',
            'type.enum' => 'Bitte wählen, worum es geht.',
            'proposed_front.max' => "Höchstens {$max} Bedeutungen je Seite.",
            'proposed_back.max' => "Höchstens {$max} Bedeutungen je Seite.",
            'proposed_front.*.max' => "Eine Bedeutung darf höchstens {$length} Zeichen haben.",
            'proposed_back.*.max' => "Eine Bedeutung darf höchstens {$length} Zeichen haben.",
            'comment.string' => 'Der Kommentar muss ein Text sein.',
            'comment.max' => "Der Kommentar darf höchstens {$commentLength} Zeichen haben.",
        ]);
        $validator->after(function ($validator) use ($data) {
            if ($data['proposed_front'] === null && $data['proposed_back'] === null && $data['comment'] === null) {
                $validator->errors()->add('comment', 'Bitte einen neuen Text oder einen Kommentar angeben.');
            }
        });
        $validator->validate();

        if ($this->openFor($user, $card) !== null) {
            throw ValidationException::withMessages(['type' => self::TOO_MANY]);
        }

        $suggestion = new CardSuggestion([
            'type' => $data['type'],
            'proposed_front' => $data['proposed_front'],
            'proposed_back' => $data['proposed_back'],
            'comment' => $data['comment'],
        ]);
        $suggestion->forceFill([
            'card_id' => $card->id,
            'user_id' => $user->id,
            'status' => SuggestionStatus::Open,
            'card_updated_at' => $card->updated_at,
        ]);

        try {
            // Savepoint: a lost race on the unique index must not abort a
            // surrounding transaction (PostgreSQL).
            DB::transaction(fn () => $suggestion->save());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['type' => self::TOO_MANY]);
        }

        return $suggestion;
    }

    /**
     * The user's latest suggestion per card, for showing its status.
     *
     * @param  Collection<int, Card>  $cards
     * @return array<string, CardSuggestion> keyed by card id
     */
    public function latestByUser(User $user, Collection $cards): array
    {
        if ($cards->isEmpty()) {
            return [];
        }

        return CardSuggestion::query()
            ->where('user_id', $user->id)
            ->whereIn('card_id', $cards->modelKeys())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->keyBy('card_id')
            ->all();
    }

    /**
     * Open suggestions for an own deck, oldest first, with card and sender.
     *
     * @return Collection<int, CardSuggestion>
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function listForOwnedDeck(User $user, string $deckId): Collection
    {
        $deck = $this->decks->getOwned($user, $deckId);

        return CardSuggestion::query()
            ->with(['card', 'user'])
            ->whereIn('card_id', $deck->cards()->select('id'))
            ->where('status', SuggestionStatus::Open)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** Number of open suggestions for an own deck; 0 for other decks. */
    public function openCountForDeck(User $user, Deck $deck): int
    {
        if (! $deck->isOwnedBy($user)) {
            return 0;
        }

        return CardSuggestion::query()
            ->whereIn('card_id', $deck->cards()->select('id'))
            ->where('status', SuggestionStatus::Open)
            ->count();
    }

    /**
     * "Übernehmen": writes the proposed sides into the card, checked like an
     * edit. A suggestion without text is just marked as done.
     *
     * @throws ModelNotFoundException<Deck|Card|CardSuggestion>
     * @throws ValidationException
     */
    public function accept(User $user, string $deckId, string $suggestionId): CardSuggestion
    {
        $suggestion = $this->getOpenOwned($user, $deckId, $suggestionId);

        return DB::transaction(function () use ($user, $deckId, $suggestion) {
            if ($suggestion->proposesText()) {
                $card = $suggestion->card;
                $this->cards->updateOwned($user, $deckId, $card->id, [
                    'front' => $suggestion->proposed_front ?? $card->front,
                    'back' => $suggestion->proposed_back ?? $card->back,
                    'hint' => $card->hint,
                ]);
            }

            return $this->decide($suggestion, SuggestionStatus::Accepted);
        });
    }

    /**
     * "Ablehnen": the card stays as it is.
     *
     * @throws ModelNotFoundException<Deck|CardSuggestion>
     */
    public function decline(User $user, string $deckId, string $suggestionId): CardSuggestion
    {
        return $this->decide($this->getOpenOwned($user, $deckId, $suggestionId), SuggestionStatus::Declined);
    }

    /**
     * An open suggestion for a card in one of the user's own decks.
     *
     * @throws ModelNotFoundException<Deck|CardSuggestion>
     */
    private function getOpenOwned(User $user, string $deckId, string $suggestionId): CardSuggestion
    {
        $deck = $this->decks->getOwned($user, $deckId);
        if (! Str::isUuid($suggestionId)) {
            throw (new ModelNotFoundException)->setModel(CardSuggestion::class, [$suggestionId]);
        }

        $suggestion = CardSuggestion::with('card')
            ->whereIn('card_id', $deck->cards()->select('id'))
            ->where('status', SuggestionStatus::Open)
            ->findOrFail($suggestionId);
        $suggestion->card->setRelation('deck', $deck);

        return $suggestion;
    }

    private function decide(CardSuggestion $suggestion, SuggestionStatus $status): CardSuggestion
    {
        $suggestion->forceFill(['status' => $status, 'decided_at' => now()])->save();

        return $suggestion;
    }
}
