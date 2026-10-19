<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Per person and deck: "Auch rückwärts lernen" (docs/specs/einfacher-lernen.md),
 * "Antworten eintippen" (docs/specs/schreiben-und-erinnern.md) and "Lerne
 * ich" / "pausiert" (docs/specs/aktive-stapel-und-freie-karten.md).
 * Only for decks the person learns: own ones and followed shared ones.
 */
class DeckPreferenceService
{
    public function __construct(private DeckService $decks) {}

    public function withReverse(User $user, Deck $deck): bool
    {
        return DB::table('deck_preferences')
            ->where('user_id', $user->id)
            ->where('deck_id', $deck->id)
            ->where('with_reverse', true)
            ->exists();
    }

    /** "Antworten eintippen" (docs/specs/schreiben-und-erinnern.md, A). */
    public function typeAnswers(User $user, Deck $deck): bool
    {
        return DB::table('deck_preferences')
            ->where('user_id', $user->id)
            ->where('deck_id', $deck->id)
            ->where('type_answers', true)
            ->exists();
    }

    /**
     * "Lerne ich" (active), "pausiert" (paused) or nothing (idle: an own deck
     * not learned right now), docs/specs/aktive-stapel-und-freie-karten.md, A.
     *
     * @return 'active'|'paused'|'idle'
     */
    public function learningState(User $user, Deck $deck): string
    {
        $pref = DB::table('deck_preferences')->where('user_id', $user->id)->where('deck_id', $deck->id)->first(['paused', 'learning']);

        return match (true) {
            $pref !== null && $pref->paused => 'paused',
            $pref !== null && ! $pref->learning => 'idle',
            default => 'active',
        };
    }

    /**
     * "Lernen" / "Wieder lernen" (true) or "Pausieren" (false). Own and
     * followed decks only; a shared deck of somebody else is started with
     * "Lernen" (DeckService::follow).
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function setLearning(User $user, string $deckId, bool $learning): Deck
    {
        $deck = $this->decks->getVisible($user, $deckId);
        if (! $deck->isOwnedBy($user) && ! $this->decks->isFollowing($user, $deck)) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$deckId]);
        }
        $this->decks->setLearningState($user, $deck, $learning);

        return $deck;
    }

    /**
     * @throws ModelNotFoundException<Deck>
     */
    public function setReverse(User $user, string $deckId, bool $on): Deck
    {
        return $this->set($user, $deckId, 'with_reverse', $on);
    }

    /**
     * @throws ModelNotFoundException<Deck>
     */
    public function setTypeAnswers(User $user, string $deckId, bool $on): Deck
    {
        return $this->set($user, $deckId, 'type_answers', $on);
    }

    /**
     * One switch of a deck the person learns: own or followed; others are 404.
     *
     * @param  'with_reverse'|'type_answers'  $column
     *
     * @throws ModelNotFoundException<Deck>
     */
    private function set(User $user, string $deckId, string $column, bool $on): Deck
    {
        $deck = $this->decks->getVisible($user, $deckId);
        if (! $deck->isOwnedBy($user) && ! $this->decks->isFollowing($user, $deck)) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$deckId]);
        }

        DB::table('deck_preferences')->upsert(
            [['user_id' => $user->id, 'deck_id' => $deck->id, $column => $on, 'created_at' => now(), 'updated_at' => now()]],
            ['user_id', 'deck_id'],
            [$column, 'updated_at'],
        );

        return $deck;
    }

    /**
     * SQL condition "this direction is learned", for queries with the
     * direction and the card's deck id at hand.
     *
     * @return array{0: string, 1: list<string>}
     */
    public function directionCondition(User $user, string $direction = 'card_progress.direction', string $deckId = 'cards.deck_id'): array
    {
        return [
            "({$direction} = 'forward'
              or exists (select 1 from deck_preferences pref where pref.user_id = ? and pref.deck_id = {$deckId} and pref.with_reverse))",
            [$user->id],
        ];
    }
}
