<?php

namespace App\Services;

use App\Enums\SuggestionStatus;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\CardSuggestion;
use App\Models\Deck;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Moderation: finds duplicate cards in shared decks and merges them.
 * See docs/specs/woerterbuch-und-bereinigung.md, stage 3.
 *
 * * Duplicates: same language pair (either order) and the same main meanings,
 *   case and spaces ignored (DictionaryService::pairKey). Only shared decks.
 * * A group is addressed by any of its cards; it is worked out anew on every
 *   request, nothing is stored.
 * * Merging keeps one card. Others in the same deck are deleted, their
 *   progress moves to the kept card. Others in other decks stay, take the
 *   kept card's content and become its linked copies (origin, spec B3).
 *
 * System-wide, but only on shared decks; used only behind role:moderator.
 *
 * @phpstan-type Group array{cards: Collection<int, Card>, decks: int}
 */
class CardMergeService
{
    /** Candidate pairs looked at per listing; plenty for a moderation queue. */
    private const CANDIDATES = 500;

    public function __construct(private CardService $cards) {}

    /**
     * Groups that still need a decision, most cards first.
     *
     * @return array{groups: list<Group>, more: bool}
     */
    public function groups(int $page = 1, int $perPage = 50): array
    {
        $keys = DB::query()
            ->fromSub($this->keyedSharedCards(), 'k')
            ->groupBy('k.pair')
            ->havingRaw('count(*) > 1')
            ->orderByRaw('count(*) desc')
            ->orderBy('k.pair')
            ->limit(self::CANDIDATES)
            ->pluck('k.pair')
            ->all();

        if ($keys === []) {
            return ['groups' => [], 'more' => false];
        }

        $ids = DB::query()->fromSub($this->keyedSharedCards(), 'k')->whereIn('k.pair', $keys)->pluck('k.id');
        /** @var array<string, Collection<int, Card>> $byPair */
        $byPair = [];
        foreach (Card::with('deck.user')->whereIn('id', $ids)->orderBy('created_at')->orderBy('id')->get() as $card) {
            $byPair[DictionaryService::pairKey($card)] ??= new Collection;
            $byPair[DictionaryService::pairKey($card)]->push($card);
        }

        $groups = [];
        foreach ($byPair as $cards) {
            if ($this->needsDecision($cards)) {
                $groups[] = ['cards' => $cards, 'decks' => $cards->pluck('deck_id')->unique()->count()];
            }
        }
        // Most cards first; stable for equal counts (oldest card first).
        usort($groups, fn (array $a, array $b) => $b['cards']->count() <=> $a['cards']->count());

        return [
            'groups' => array_slice($groups, ($page - 1) * $perPage, $perPage),
            'more' => count($groups) > $page * $perPage,
        ];
    }

    /**
     * All cards of the group the given card belongs to, oldest first.
     *
     * @return Collection<int, Card>
     *
     * @throws ModelNotFoundException<Card>
     */
    public function group(string $cardId): Collection
    {
        if (! Str::isUuid($cardId)) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$cardId]);
        }

        $card = Card::with('deck')
            ->whereHas('deck', fn ($q) => $q->where('is_shared', true))
            ->findOrFail($cardId);
        $key = DictionaryService::pairKey($card);

        $ids = DB::query()->fromSub($this->keyedSharedCards(), 'k')
            ->where('k.pair', $this->sqlKeyOf($card))
            ->pluck('k.id');

        $group = Card::with('deck.user')->whereIn('id', $ids)->orderBy('created_at')->orderBy('id')->get()
            ->filter(fn (Card $c) => DictionaryService::pairKey($c) === $key)
            ->values();

        if ($group->count() < 2) {
            throw (new ModelNotFoundException)->setModel(Card::class, [$cardId]);
        }

        return $group;
    }

    /**
     * How many people have progress on each card of the group.
     *
     * @param  Collection<int, Card>  $group
     * @return array<string, int>
     */
    public function learners(Collection $group): array
    {
        return DB::table('card_progress')
            ->whereIn('card_id', $group->modelKeys())
            ->groupBy('card_id')
            ->selectRaw('card_id, count(distinct user_id) as people')
            ->pluck('people', 'card_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Merges the group into the kept card.
     *
     * @param  list<string>  $seen  ids of the cards the moderator was shown
     * @param  array<string, mixed>  $content  front[], back[], hint of the kept card
     * @return array{deleted: int, linked: int, learners: int}
     *
     * @throws ModelNotFoundException<Card>
     * @throws ValidationException
     */
    public function merge(string $groupCardId, string $keepId, array $seen, array $content): array
    {
        return DB::transaction(function () use ($groupCardId, $keepId, $seen, $content) {
            $group = $this->group($groupCardId);

            // Two moderators at once, or a card changed meanwhile: stop.
            Card::whereIn('id', $group->modelKeys())->lockForUpdate()->get();
            $current = $group->modelKeys();
            sort($current);
            sort($seen);
            if ($current !== $seen || ! in_array($keepId, $current, true)) {
                throw ValidationException::withMessages(['keep' => 'Diese Gruppe hat sich geändert. Bitte neu laden.']);
            }

            $keep = $this->cards->updateShared(
                $group->firstWhere('id', $keepId)->deck_id,
                $keepId,
                $content,
            );

            $result = ['deleted' => 0, 'linked' => 0, 'learners' => 0];
            $learners = [];
            foreach ($group as $other) {
                if ($other->id === $keep->id) {
                    continue;
                }
                if ($other->deck_id === $keep->deck_id) {
                    $learners = [...$learners, ...$this->moveProgress($other, $keep)];
                    $this->moveReferences($other, $keep);
                    $other->delete();
                    $result['deleted']++;
                } else {
                    $this->link($other, $keep);
                    $result['linked']++;
                }
            }

            // The kept card must not point at a card of its own group.
            if ($keep->source_card_id !== null && in_array($keep->source_card_id, $current, true)) {
                $keep->forceFill(['source_card_id' => null, 'source_updated_at' => null])->save();
            }

            $result['learners'] = count(array_unique($learners));

            return $result;
        });
    }

    // -------------------------------------------------------------------------

    /**
     * A group needs a decision unless all cards but one are linked copies
     * of it with the same content (what a merge leaves behind).
     *
     * @param  Collection<int, Card>  $cards
     */
    private function needsDecision(Collection $cards): bool
    {
        $byId = $cards->keyBy('id');
        $open = $cards->reject(function (Card $card) use ($byId) {
            $source = $card->source_card_id === null ? null : $byId->get($card->source_card_id);

            return $source !== null && $this->sameContent($card, $source);
        });

        return $open->count() > 1;
    }

    private function sameContent(Card $a, Card $b): bool
    {
        [$front, $back] = $this->oriented($b, $a->deck);

        return $a->front === $front && $a->back === $back && $a->hint === $b->hint;
    }

    /**
     * A card's sides as they fit the given deck's language order.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function oriented(Card $card, Deck $target): array
    {
        return $card->deck->front_language === $target->front_language
            ? [$card->front, $card->back]
            : [$card->back, $card->front];
    }

    /**
     * Moves the progress of a card that is going away onto the kept card.
     * Per run and direction the further one wins (higher box, then the
     * later due date); review counts add up.
     *
     * @return list<string> ids of the people whose progress moved
     */
    private function moveProgress(Card $from, Card $to): array
    {
        $people = [];
        foreach (CardProgress::where('card_id', $from->id)->get() as $row) {
            $people[] = $row->user_id;
            $target = CardProgress::where('card_id', $to->id)
                ->where('deck_run_id', $row->deck_run_id)
                ->where('direction', $row->direction->value)
                ->first();

            if ($target === null) {
                $row->forceFill(['card_id' => $to->id])->save();

                continue;
            }

            // Same box: the one due later has been learned more recently.
            $further = $row->box > $target->box
                || ($row->box === $target->box && $row->due_on !== null
                    && ($target->due_on === null || $row->due_on->greaterThan($target->due_on)));
            $lastReviewed = match (true) {
                $row->last_reviewed_at === null => $target->last_reviewed_at,
                $target->last_reviewed_at === null => $row->last_reviewed_at,
                default => $row->last_reviewed_at->max($target->last_reviewed_at),
            };
            $target->fill([
                'box' => $further ? $row->box : $target->box,
                'due_on' => $further ? $row->due_on : $target->due_on,
                'last_reviewed_at' => $lastReviewed,
                'review_count' => $target->review_count + $row->review_count,
                'correct_count' => $target->correct_count + $row->correct_count,
            ])->save();
            $row->delete();
        }

        return $people;
    }

    /** Copies and open suggestions of a card that is going away move to the kept card. */
    private function moveReferences(Card $from, Card $to): void
    {
        Card::where('source_card_id', $from->id)->update(['source_card_id' => $to->id]);

        foreach (CardSuggestion::where('card_id', $from->id)->where('status', SuggestionStatus::Open)->get() as $suggestion) {
            $taken = CardSuggestion::where('card_id', $to->id)
                ->where('user_id', $suggestion->user_id)
                ->where('status', SuggestionStatus::Open)
                ->exists();
            $suggestion->forceFill($taken
                ? ['status' => SuggestionStatus::Declined, 'decided_at' => now()]
                : ['card_id' => $to->id])->save();
        }
    }

    /** A duplicate in another deck takes the kept card's content and links to it. */
    private function link(Card $other, Card $keep): void
    {
        [$front, $back] = $this->oriented($keep, $other->deck);
        $other->fill(['front' => $front, 'back' => $back, 'hint' => $keep->hint]);
        $other->forceFill(['source_card_id' => $keep->id, 'source_updated_at' => $keep->updated_at])->save();
    }

    /**
     * Shared cards with an SQL pair key — a pre-filter only; the PHP key
     * (DictionaryService::pairKey) decides.
     */
    private function keyedSharedCards(): Builder
    {
        $main = fn (string $side) => "lower(btrim(regexp_replace(c.{$side}->>0, '\\s+', ' ', 'g')))";

        return DB::table('cards as c')
            ->join('decks as d', 'd.id', '=', 'c.deck_id')
            ->where('d.is_shared', true)
            ->select('c.id')
            ->selectRaw(
                "case when d.front_language <= d.back_language
                    then d.front_language || '|' || d.back_language || '|' || {$main('front')} || '|' || {$main('back')}
                    else d.back_language || '|' || d.front_language || '|' || {$main('back')} || '|' || {$main('front')}
                 end as pair"
            );
    }

    /** The SQL pair key of one card, built the same way as in keyedSharedCards(). */
    private function sqlKeyOf(Card $card): string
    {
        return (string) DB::query()->fromSub($this->keyedSharedCards(), 'k')->where('k.id', $card->id)->value('k.pair');
    }
}
