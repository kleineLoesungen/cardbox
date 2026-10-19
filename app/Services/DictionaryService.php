<?php

namespace App\Services;

use App\Enums\Language;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * "Wörterbuch": looking words up in every card the person may see — own
 * decks, followed and all shared decks; never other people's private ones.
 * See docs/specs/woerterbuch-und-bereinigung.md, stage 1.
 *
 * A hit is a word pair, not a card: cards with the same main meanings and
 * the same language pair (also reversed) are shown once, with all decks
 * they appear in.
 *
 * The first offer for a hit is to learn it (issue #10): "learn" says whether
 * the person learns the card already ("active"), has it paused in a deck
 * they learn ("paused"), or would follow its deck for it ("follow").
 * Copying into an own deck stays as a further option.
 *
 * @phpstan-type Hit array{card: Card, decks: list<Deck>, targets: list<Deck>, learn: 'active'|'paused'|'follow'}
 */
class DictionaryService
{
    /** Cards read per search; more than enough for the shown hits. */
    private const SCAN = 200;

    public function __construct(private DeckService $decks, private CardSelectionService $selection) {}

    /**
     * @return array{hits: list<Hit>, more: bool}
     */
    public function search(User $user, string $term, ?string $language = null): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return ['hits' => [], 'more' => false];
        }

        $pattern = '%'.addcslashes($term, '\\%_').'%';
        // Match single meanings, not the JSON text (as in CardService::searchToAdopt).
        $matches = 'exists (select 1 from jsonb_array_elements_text(%s) m where m ilike ?)';

        $visible = $this->decks->visibleTo($user)
            ->when(Language::tryFrom((string) $language), fn (Builder $q, Language $lang) => $q
                ->where(fn (Builder $q) => $q
                    ->where('front_language', $lang->value)
                    ->orWhere('back_language', $lang->value)));

        $cards = Card::query()
            ->with('deck.user')
            ->whereIn('deck_id', $visible->select('id'))
            ->where(fn (Builder $q) => $q
                ->whereRaw(sprintf($matches, 'front'), [$pattern])
                ->orWhereRaw(sprintf($matches, 'back'), [$pattern]))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::SCAN)
            ->get();

        $limit = (int) config('learning.search_limit');
        $own = $this->ownDecks($user);

        /** @var array<string, array{card: Card, decks: array<string, Deck>, targets: list<Deck>}> $groups */
        $groups = [];
        foreach ($cards as $card) {
            $key = self::pairKey($card);
            if (! isset($groups[$key])) {
                $groups[$key] = ['card' => $card, 'decks' => [], 'targets' => $this->targetsFor($card->deck, $own)];
            }
            $groups[$key]['decks'][$card->deck_id] = $card->deck;
        }
        $hits = array_map(fn (array $hit) => [
            'card' => $hit['card'],
            'decks' => array_values($hit['decks']),
            // Not into a deck that holds this word pair already: that would make a duplicate.
            'targets' => array_values(array_filter($hit['targets'], fn (Deck $deck) => ! isset($hit['decks'][$deck->id]))),
        ], array_values($groups));

        $hits = array_slice($hits, 0, $limit);
        // "lerne ich" only where the card really comes up on "Heute": deck "Lerne ich" and card selected.
        $states = $this->decks->learningStates($user);
        $active = [];
        foreach ($hits as $i => $hit) {
            $deck = $hit['card']->deck;
            $learns = isset($states[$deck->id]);
            $active[$deck->id] ??= $learns ? $this->selection->activeIds($user, $deck) : [];
            $hits[$i]['learn'] = match (true) {
                ! $learns => 'follow',
                $states[$deck->id] === 'active' && ($active[$deck->id] === null || in_array($hit['card']->id, $active[$deck->id], true)) => 'active',
                default => 'paused',
            };
        }

        return [
            'hits' => $hits,
            'more' => count($groups) > $limit || $cards->count() === self::SCAN,
        ];
    }

    /**
     * All visible cards of the hit the given card stands for (issue #12):
     * same word pair as in search(). The given card comes first, then cards
     * in own decks, then the oldest. Decks come with owner and follower count.
     *
     * @return Collection<int, Card>
     *
     * @throws ModelNotFoundException<Card>
     */
    public function group(User $user, string $cardId): Collection
    {
        if (! Str::isUuid($cardId)) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$cardId]);
        }

        $visible = $this->decks->visibleTo($user);
        $withDeck = ['deck' => fn ($q) => $q->with('user')->withCount('followers')];
        $base = Card::with($withDeck)->whereIn('deck_id', $visible->select('id'))->findOrFail($cardId);
        $deck = $base->deck;

        // Same normalisation as pairKey() in SQL: the main meaning of the
        // front equals one of the base card's main meanings (reversed decks
        // have them the other way round); pairKey() then decides exactly.
        $main = "lower(regexp_replace(btrim(front->>0), '\\s+', ' ', 'g'))";
        $key = self::pairKey($base);

        $cards = Card::with($withDeck)
            ->whereIn('deck_id', $this->decks->visibleTo($user)
                ->where(fn (Builder $q) => $q
                    ->where(fn (Builder $q) => $q
                        ->where('front_language', $deck->front_language)
                        ->where('back_language', $deck->back_language))
                    ->orWhere(fn (Builder $q) => $q
                        ->where('front_language', $deck->back_language)
                        ->where('back_language', $deck->front_language)))
                ->select('id'))
            ->whereRaw("{$main} in (?, ?)", [self::normalize($base->frontText()), self::normalize($base->backText())])
            ->whereKeyNot($base->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::SCAN)
            ->get()
            ->filter(fn (Card $card) => self::pairKey($card) === $key);

        // The shown card always comes first, then own decks.
        return $cards
            ->sortBy(fn (Card $card) => $card->deck->user_id === $user->id ? 0 : 1)
            ->prepend($base)
            ->values();
    }

    /**
     * Languages that occur in the decks the person can see, for the filter.
     *
     * @return list<Language>
     */
    public function languages(User $user): array
    {
        $codes = $this->decks->visibleTo($user)
            ->toBase()
            ->select('front_language as code')
            ->union($this->decks->visibleTo($user)->toBase()->select('back_language as code'))
            ->pluck('code')
            ->all();

        return array_values(array_filter(
            Language::cases(),
            fn (Language $l) => in_array($l->value, $codes, true),
        ));
    }

    /**
     * Identity of a word pair: language pair and main meanings, in a fixed
     * order of the languages, case and surrounding space ignored. Also used
     * to find duplicate cards (stage 3).
     */
    public static function pairKey(Card $card): string
    {
        $deck = $card->deck;
        $a = [$deck->front_language, self::normalize($card->frontText())];
        $b = [$deck->back_language, self::normalize($card->backText())];
        [$first, $second] = $a[0] <= $b[0] ? [$a, $b] : [$b, $a];

        return implode("\u{0}", [...$first, ...$second]);
    }

    private static function normalize(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    /**
     * The person's own decks, for "In Stapel übernehmen".
     *
     * @return Collection<int, Deck>
     */
    private function ownDecks(User $user): Collection
    {
        return $user->decks()->orderBy('name')->get();
    }

    /**
     * Own decks with the card's language pair (either order), except the
     * card's own deck.
     *
     * @param  Collection<int, Deck>  $own
     * @return list<Deck>
     */
    private function targetsFor(Deck $source, Collection $own): array
    {
        $pair = [$source->front_language, $source->back_language];
        sort($pair);

        return $own
            ->filter(function (Deck $deck) use ($source, $pair) {
                $candidate = [$deck->front_language, $deck->back_language];
                sort($candidate);

                return $deck->id !== $source->id && $candidate === $pair;
            })
            ->values()
            ->all();
    }
}
