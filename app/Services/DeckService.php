<?php

namespace App\Services;

use App\Enums\Language;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Decks: visibility, ownership, following, copying, moderation.
 *
 * The rules (docs/specs/vokabelkarten.md, "Privat und geteilt"):
 *
 * * Visibility belongs to the deck. Own decks, decks shared with everybody
 *   and decks shared with the person by name (deck_shares) are visible;
 *   other people's private decks are not — they answer 404, for moderators
 *   and admins too. Moderation only ever sees decks shared with everybody.
 * * Only the owner changes a deck or its cards.
 * * Other people's shared decks can be followed (read and learn) or copied.
 *
 * Two flavours, as everywhere in this project:
 *
 * * `…Owned` / `getVisible` — limited to what a user may see or change. For
 *   the web UI.
 * * `…Shared` — system-wide, but only ever on shared decks. For the
 *   moderation routes (group `role:moderator`).
 */
class DeckService
{
    /** Most people one deck is shared with by name; beyond that, "Mit allen". */
    public const MAX_SHARES = 50;

    // -------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------

    /**
     * "Meine Stapel": own decks plus shared decks of others the user follows.
     * A followed deck that is no longer shared drops out silently. With a
     * search term only decks whose name or description match (see search()).
     *
     * @return Collection<int, Deck>
     */
    public function mine(User $user, ?string $search = null): Collection
    {
        return $this->withListData($this->search($this->mineQuery($user), $search), $user)->get();
    }

    /**
     * Counts and order shared by mine() and active(), so "Stapel" and
     * "Heute" never count differently.
     *
     * @param  Builder<Deck>  $query
     * @return Builder<Deck>
     */
    private function withListData(Builder $query, User $user): Builder
    {
        return $query
            ->with('user')
            ->withCount(['cards', 'followers' => self::seeingFollowers(), 'sharedWith as shares_count' => fn (Builder $q) => $q->whereNull('deck_shares.declined_at'), 'suggestions as open_suggestions_count' => fn (Builder $q) => $q->where('card_suggestions.status', 'open')])
            ->orderByRaw('user_id = ? DESC', [$user->id])
            ->orderBy('name');
    }

    /**
     * Query behind "Meine Stapel" (the deck list), paused decks included.
     * "Heute" and "Los geht's" learn from activeQuery().
     *
     * @return Builder<Deck>
     */
    public function mineQuery(User $user): Builder
    {
        return Deck::query()->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhere(fn (Builder $q) => $q
                ->where(fn (Builder $q) => $q->where('is_shared', true)->orWhereIn('id', $this->sharedWithIds($user)))
                ->whereHas('followers', fn (Builder $f) => $f->whereKey($user->id))));
    }

    /**
     * The decks "Heute" learns from ("Lerne ich"): "Meine Stapel" without the
     * paused ones and without own decks the person does not learn right now
     * (docs/specs/aktive-stapel-und-freie-karten.md, A). No preference row
     * means "lerne ich" — new and followed decks start there.
     *
     * @return Builder<Deck>
     */
    public function activeQuery(User $user): Builder
    {
        return $this->mineQuery($user)->whereNotExists(fn ($q) => $q
            ->from('deck_preferences')
            ->whereColumn('deck_preferences.deck_id', 'decks.id')
            ->where('deck_preferences.user_id', $user->id)
            ->where(fn ($q) => $q->where('deck_preferences.paused', true)->orWhere('deck_preferences.learning', false)));
    }

    /**
     * Active decks with counts, like mine().
     *
     * @return Collection<int, Deck>
     */
    public function active(User $user): Collection
    {
        return $this->withListData($this->activeQuery($user), $user)->get();
    }

    /**
     * "Lerne ich", "pausiert" or nothing (an own deck not learned right now)
     * for each of "Meine Stapel", by deck id.
     *
     * @return array<string, 'active'|'paused'|'idle'>
     */
    public function learningStates(User $user): array
    {
        $prefs = DB::table('deck_preferences')->where('user_id', $user->id)->get(['deck_id', 'paused', 'learning'])->keyBy('deck_id');

        $states = [];
        foreach ($this->mineQuery($user)->pluck('id') as $id) {
            $pref = $prefs->get($id);
            $states[(string) $id] = match (true) {
                $pref !== null && $pref->paused => 'paused',
                $pref !== null && ! $pref->learning => 'idle',
                default => 'active',
            };
        }

        return $states;
    }

    /**
     * "Lernen": the deck goes on "Heute". A shared deck of somebody else is
     * followed first (there is no following without learning).
     */
    public function startLearning(User $user, Deck $deck): void
    {
        if (! $deck->isOwnedBy($user)) {
            $user->followedDecks()->syncWithoutDetaching([$deck->id]);
        }
        $this->setLearningState($user, $deck, true);
    }

    /** "Pausieren" (false) or back to "Lerne ich" (true). */
    public function setLearningState(User $user, Deck $deck, bool $learning): void
    {
        DB::table('deck_preferences')->upsert(
            [['user_id' => $user->id, 'deck_id' => $deck->id, 'learning' => true, 'paused' => ! $learning, 'created_at' => now(), 'updated_at' => now()]],
            ['user_id', 'deck_id'],
            ['learning', 'paused', 'updated_at'],
        );
    }

    /**
     * "Entdecken": shared decks of other people.
     *
     * @return Collection<int, Deck>
     */
    public function discover(User $user, ?string $search = null, ?string $language = null): Collection
    {
        $search = trim((string) $search);
        $language = Language::tryFrom((string) $language)?->value;

        $query = Deck::query()
            ->with('user')
            ->withCount(['cards', 'followers'])
            ->withExists(['followers as followed_by_me' => fn (Builder $f) => $f->whereKey($user->id)])
            ->where('is_shared', true)
            ->where('user_id', '!=', $user->id);

        return $this->search($query, $search)
            ->when($language !== null, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('front_language', $language)
                ->orWhere('back_language', $language)))
            ->orderBy('name')
            ->limit(100)
            ->get();
    }

    /**
     * Name or description contain the term — literally (wildcards taken as
     * text), or — for terms with at least four letters or digits — ignoring
     * spaces, hyphens and other punctuation, so an ISBN like
     * 978-3-12-345678-9 is also found as 9783123456789 and vice versa.
     *
     * @param  Builder<Deck>  $query
     * @return Builder<Deck>
     */
    private function search(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $pattern = '%'.addcslashes($term, '\\%_').'%';
        $compact = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $term));
        $stripped = fn (string $column) => "regexp_replace(lower(coalesce({$column}, '')), '[^[:alnum:]]+', '', 'g')";

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'ilike', $pattern)
            ->orWhere('description', 'ilike', $pattern)
            // Only for longer terms: "0 %" would shrink to "0" and match almost anything.
            ->when(mb_strlen($compact) >= 4, fn (Builder $q) => $q
                ->orWhereRaw($stripped('name').' like ?', ['%'.$compact.'%'])
                ->orWhereRaw($stripped('description').' like ?', ['%'.$compact.'%'])));
    }

    /**
     * A deck the user may see: their own, or any shared one.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function getVisible(User $user, string $id): Deck
    {
        $this->ensureUuid($id);

        return $this->visibleTo($user)->with('user')->findOrFail($id);
    }

    /**
     * A deck the user owns. Other people's decks — shared or not — are 404.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function getOwned(User $user, string $id): Deck
    {
        $this->ensureUuid($id);

        return $user->decks()->with('user')->findOrFail($id);
    }

    /**
     * Decks whose cards the user may see: own decks and shared ones.
     *
     * @return Builder<Deck>
     */
    public function visibleTo(User $user): Builder
    {
        return Deck::query()->where(fn (Builder $q) => $q
            ->where('user_id', $user->id)
            ->orWhere('is_shared', true)
            ->orWhereIn('id', $this->sharedWithIds($user)));
    }

    /**
     * Ids of the decks shared with the person by name
     * (docs/specs/stapel-mit-einzelnen-teilen.md).
     */
    private function sharedWithIds(User $user): QueryBuilder
    {
        return DB::table('deck_shares')->where('user_id', $user->id)->whereNull('declined_at')->select('deck_id');
    }

    /** Shares of one deck that count: not declined. */
    private function activeShares(Deck $deck): QueryBuilder
    {
        return DB::table('deck_shares')->where('deck_id', $deck->id)->whereNull('declined_at');
    }

    /**
     * What moderation may touch: own decks and decks shared with everybody —
     * never decks shared only by name, even when the moderator is on the list.
     *
     * @return Builder<Deck>
     */
    public function ownOrPublic(User $user): Builder
    {
        return Deck::query()->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('is_shared', true));
    }

    public function isFollowing(User $user, Deck $deck): bool
    {
        return DB::table('deck_follows')
            ->where('user_id', $user->id)
            ->where('deck_id', $deck->id)
            ->exists();
    }

    /**
     * People learning along who still see the deck: shared with everybody,
     * or still on the list. Old follows of a deck no longer shared do not count.
     */
    public function followerCount(Deck $deck): int
    {
        return Deck::whereKey($deck->id)->withCount(['followers' => self::seeingFollowers()])->value('followers_count') ?? 0;
    }

    /**
     * For withCount('followers'): only followers who still see the deck.
     *
     * @return \Closure(Builder<User>): Builder<User>
     */
    public static function seeingFollowers(): \Closure
    {
        return fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->whereExists(fn ($e) => $e->selectRaw('1')->from('decks as d')->whereColumn('d.id', 'deck_follows.deck_id')->where('d.is_shared', true))
            ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('deck_shares as s')
                ->whereColumn('s.deck_id', 'deck_follows.deck_id')->whereColumn('s.user_id', 'deck_follows.user_id')->whereNull('s.declined_at')));
    }

    /**
     * What deleting this person's account takes with it — for the admin's
     * confirmation.
     *
     * @return array{decks: int, shared: int, followers: int}
     */
    public function ownerSummary(User $user): array
    {
        return [
            'decks' => $user->decks()->count(),
            'shared' => $user->decks()->where('is_shared', true)->count(),
            'followers' => DB::table('deck_follows')
                ->whereIn('deck_id', $user->decks()->where('is_shared', true)->select('id'))
                ->count(),
        ];
    }

    // -------------------------------------------------------------------------
    // Own decks
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input  name, description, front_language, back_language
     *
     * @throws ValidationException
     */
    public function create(User $user, array $input): Deck
    {
        $deck = new Deck($this->validate($input));
        $deck->user_id = $user->id;
        $deck->save();

        return $deck;
    }

    /**
     * Changing the languages leaves cards and progress untouched — cards hold
     * plain text, the language only drives labels and read-aloud.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function updateOwned(User $user, string $id, array $input): Deck
    {
        $deck = $this->getOwned($user, $id);
        $deck->update($this->validate($input));

        return $deck;
    }

    /**
     * "Teilen": private, with single persons (the list stays) or with
     * everybody. Private ends the shares with single persons; their progress
     * stays, as when they stop learning, for a later share.
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function setVisibility(User $user, string $id, mixed $visibility): Deck
    {
        $deck = $this->getOwned($user, $id);
        if (! in_array($visibility, ['private', 'people', 'all'], true)) {
            throw ValidationException::withMessages(['visibility' => 'Bitte „Privat“, „Mit bestimmten Personen“ oder „Mit allen“ wählen.']);
        }

        DB::transaction(function () use ($deck, $visibility) {
            $deck->forceFill(['is_shared' => $visibility === 'all'])->save();
            if ($visibility === 'private') {
                $this->endShares($deck);
            }
        });

        return $deck;
    }

    /**
     * The people an own deck is shared with by name, oldest first; each with
     * `learns` (they accepted and follow it).
     *
     * @return Collection<int, User>
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function sharesOwned(User $user, string $id): Collection
    {
        $deck = $this->getOwned($user, $id);

        return User::query()
            ->join('deck_shares', 'deck_shares.user_id', '=', 'users.id')
            ->where('deck_shares.deck_id', $deck->id)
            ->whereNull('deck_shares.declined_at')
            ->select('users.*')
            ->selectRaw('exists (select 1 from deck_follows f where f.deck_id = deck_shares.deck_id and f.user_id = users.id) as learns')
            ->orderBy('deck_shares.created_at')
            ->orderBy('users.name')
            ->get();
    }

    /**
     * Shares an own deck with one person, by username. Unknown, blocked and
     * not approved accounts get the same answer, so the field reveals as
     * little as possible.
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function shareWith(User $user, string $id, mixed $username): User
    {
        $deck = $this->getOwned($user, $id);
        $name = UserService::normalizeUsername(is_string($username) ? $username : '');
        if ($name === '') {
            throw ValidationException::withMessages(['username' => 'Bitte einen Benutzernamen eingeben.']);
        }
        $person = User::where('username', $name)->first();
        if ($person === null || ! $person->isApproved() || ! $person->is_active) {
            throw ValidationException::withMessages(['username' => 'Diesen Benutzernamen gibt es nicht.']);
        }
        if ($person->is($user)) {
            throw ValidationException::withMessages(['username' => 'Das bist du selbst.']);
        }

        return DB::transaction(function () use ($deck, $person) {
            // Locked: two quick taps must not pass the limit together.
            Deck::whereKey($deck->id)->lockForUpdate()->first();
            $existing = DB::table('deck_shares')->where('deck_id', $deck->id)->where('user_id', $person->id)->first();
            if ($existing !== null && $existing->declined_at !== null) {
                // Declining blocks: nobody pushes a deck on a person again.
                throw ValidationException::withMessages(['username' => "{$person->name} hat den Stapel abgelehnt."]);
            }
            if ($existing !== null) {
                throw ValidationException::withMessages(['username' => "Mit {$person->name} ist der Stapel schon geteilt."]);
            }
            if ($this->activeShares($deck)->count() >= self::MAX_SHARES) {
                throw ValidationException::withMessages(['username' => 'Mehr als '.self::MAX_SHARES.' Personen geht nicht – teil den Stapel lieber mit allen.']);
            }
            DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $person->id, 'created_at' => now()]);

            return $person;
        });
    }

    /**
     * Takes a person off the list. Unless the deck is shared with everybody,
     * it leaves their decks; their progress stays for a later share.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function unshareWith(User $user, string $id, string $personId): User
    {
        $deck = $this->getOwned($user, $id);
        $person = Str::isUuid($personId)
            ? User::whereKey($personId)->whereIn('id', $this->activeShares($deck)->select('user_id'))->first()
            : null;
        if ($person === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$personId]);
        }

        DB::transaction(function () use ($deck, $person) {
            DB::table('deck_shares')->where('deck_id', $deck->id)->where('user_id', $person->id)->delete();
            if (! $deck->is_shared) {
                $person->followedDecks()->detach($deck->id);
            }
        });

        return $person;
    }

    /**
     * Decks shared with the person by name that wait for "Lernen" or
     * "Ablehnen": not learned yet, newest first.
     *
     * @return Collection<int, Deck>
     */
    public function pendingShares(User $user): Collection
    {
        return Deck::query()
            ->with('user')
            ->withCount('cards')
            ->whereIn('id', $this->sharedWithIds($user))
            ->whereDoesntHave('followers', fn (Builder $f) => $f->whereKey($user->id))
            ->orderByDesc(DB::table('deck_shares')->select('created_at')->whereColumn('deck_shares.deck_id', 'decks.id')->where('deck_shares.user_id', $user->id)->limit(1))
            ->get();
    }

    /** How many decks wait for "Lernen" or "Ablehnen" (the badge on "Stapel"). */
    public function pendingShareCount(User $user): int
    {
        return Deck::query()
            ->whereIn('id', $this->sharedWithIds($user))
            ->whereDoesntHave('followers', fn (Builder $f) => $f->whereKey($user->id))
            ->count();
    }

    public function isSharedWith(User $user, Deck $deck): bool
    {
        return $this->activeShares($deck)->where('user_id', $user->id)->exists();
    }

    /**
     * Ends every share by name: the people lose the deck (progress stays),
     * declines stay as blocks.
     */
    private function endShares(Deck $deck): void
    {
        $people = $this->activeShares($deck)->pluck('user_id')->all();
        DB::table('deck_follows')->where('deck_id', $deck->id)->whereIn('user_id', $people)->delete();
        $this->activeShares($deck)->delete();
    }

    /**
     * "Ablehnen" or "Stapel entfernen" for a deck shared by name: out of the
     * own decks, and the owner cannot add the person to this deck again (the
     * row stays as a block). Progress stays, as when one stops learning.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function decline(User $user, string $id): Deck
    {
        $deck = Str::isUuid($id) ? Deck::whereIn('id', $this->sharedWithIds($user))->find($id) : null;
        if ($deck === null) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$id]);
        }

        DB::transaction(function () use ($user, $deck) {
            DB::table('deck_shares')->where('deck_id', $deck->id)->where('user_id', $user->id)->update(['declined_at' => now()]);
            $user->followedDecks()->detach($deck->id);
        });

        return $deck;
    }

    /**
     * Deletes the deck with all its cards, follows and everybody's progress.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function deleteOwned(User $user, string $id): void
    {
        $this->getOwned($user, $id)->delete();
    }

    // -------------------------------------------------------------------------
    // Following and copying
    // -------------------------------------------------------------------------

    /**
     * "Lernen" on a shared deck of somebody else: follow it and make it
     * "Lerne ich" (a pause ends).
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function follow(User $user, string $id): Deck
    {
        $deck = $this->getVisible($user, $id);

        if ($deck->isOwnedBy($user)) {
            throw ValidationException::withMessages(['deck' => 'Deinen eigenen Stapel lernst du schon.']);
        }

        $this->startLearning($user, $deck);

        return $deck;
    }

    /**
     * Stop following. Works by id even if the deck is no longer shared, so a
     * hidden deck can be dropped too. Progress is kept for a later re-follow.
     */
    public function unfollow(User $user, string $id): void
    {
        if (Str::isUuid($id)) {
            $user->followedDecks()->detach($id);
        }
    }

    /**
     * Copy a visible deck into a new private deck of the user, with copies of
     * all cards. The copy is independent: later changes to the original do
     * not reach it by themselves, but each card remembers its original, so
     * the owner is offered them (CardService::pendingOriginalUpdates). The user's own runs and their progress are copied along,
     * so following first and copying later does not start from scratch.
     *
     * @throws ModelNotFoundException<Deck>
     */
    public function copy(User $user, string $id): Deck
    {
        $original = $this->getVisible($user, $id);

        return DB::transaction(function () use ($user, $original) {
            $copy = new Deck([
                'name' => $original->name,
                'description' => $original->description,
                'front_language' => $original->front_language,
                'back_language' => $original->back_language,
            ]);
            $copy->user_id = $user->id;
            $copy->copied_from_name = $original->name;
            // Lets a later ZIP import recognise this copy (DeckBundleService).
            $copy->source_deck_id = $original->id;
            $copy->save();

            $now = now();
            $newIds = [];
            $rows = $original->cards()->orderBy('created_at')->get()
                ->map(function (Card $card) use ($copy, $now, &$newIds) {
                    $newIds[$card->id] = (string) Str::uuid();

                    return [
                        'id' => $newIds[$card->id],
                        'deck_id' => $copy->id,
                        'front' => json_encode($card->front, JSON_UNESCAPED_UNICODE),
                        'back' => json_encode($card->back, JSON_UNESCAPED_UNICODE),
                        'hint' => $card->hint,
                        'source_card_id' => $card->id,
                        'source_updated_at' => $card->updated_at,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })
                ->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                Card::insert($chunk);
            }

            // Only the copying person's runs and progress — never anybody else's.
            $newRuns = [];
            foreach (DeckRun::where('user_id', $user->id)->where('deck_id', $original->id)->get() as $run) {
                $newRuns[$run->id] = DeckRun::create([
                    'user_id' => $user->id,
                    'deck_id' => $copy->id,
                    'plan' => $run->plan,
                    'box_count' => $run->box_count,
                    'day_gaps' => $run->day_gaps,
                ])->id;
            }

            $progress = CardProgress::query()
                ->whereIn('deck_run_id', array_keys($newRuns))
                ->get()
                ->map(fn (CardProgress $p) => [
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'deck_run_id' => $newRuns[$p->deck_run_id],
                    'card_id' => $newIds[$p->card_id],
                    'direction' => $p->direction->value,
                    'box' => $p->box,
                    'due_on' => $p->due_on?->toDateString(),
                    'last_reviewed_at' => $p->last_reviewed_at,
                    'review_count' => $p->review_count,
                    'correct_count' => $p->correct_count,
                    // Kept: created_at says when a card was started, for the daily new cards on "Heute".
                    'created_at' => $p->created_at,
                    'updated_at' => $now,
                ])
                ->all();

            foreach (array_chunk($progress, 500) as $chunk) {
                CardProgress::insert($chunk);
            }

            // The person's switches go along ("Auch rückwärts lernen", "Antworten eintippen"),
            // or backward progress would be hidden. Not "pausiert": a copy starts as "Lerne ich".
            DB::table('deck_preferences')->insertUsing(
                ['user_id', 'deck_id', 'with_reverse', 'type_answers', 'created_at', 'updated_at'],
                DB::table('deck_preferences')
                    ->where('user_id', $user->id)
                    ->where('deck_id', $original->id)
                    ->selectRaw('user_id, ?, with_reverse, type_answers, ?, ?', [$copy->id, $now, $now]),
            );

            return $copy;
        });
    }

    // -------------------------------------------------------------------------
    // Moderation — system-wide, but only on SHARED decks.
    // Private decks of others stay invisible to moderators as well.
    // -------------------------------------------------------------------------

    /** @throws ModelNotFoundException<Deck> */
    public function getShared(string $id): Deck
    {
        $this->ensureUuid($id);

        return Deck::where('is_shared', true)->findOrFail($id);
    }

    /** @throws ModelNotFoundException<Deck> */
    public function unshareShared(string $id): Deck
    {
        $deck = $this->getShared($id);
        // Moderation takes a deck out of circulation: shares by name end too.
        DB::transaction(function () use ($deck) {
            $deck->forceFill(['is_shared' => false])->save();
            $this->endShares($deck);
        });

        return $deck;
    }

    /** @throws ModelNotFoundException<Deck> */
    public function deleteShared(string $id): void
    {
        $this->getShared($id)->delete();
    }

    // -------------------------------------------------------------------------
    // Administration — any deck of any account, private ones included.
    // Used only behind the role:admin routes (issue #12).
    // -------------------------------------------------------------------------

    /**
     * All decks of an account for the admin's list: names and numbers only,
     * never the cards.
     *
     * @return Collection<int, Deck>
     */
    public function listForAdmin(User $owner): Collection
    {
        return $owner->decks()->withCount(['cards', 'followers'])->orderBy('name')->get();
    }

    /**
     * Hands decks of `$from` to the account with the given username. The new
     * owner stops following them (owners learn their decks anyway); the old
     * owner follows the shared ones and keeps learning with their progress.
     * Progress, selections and runs belong to people, not to the owner, so
     * they stay as they are.
     *
     * @return array{to: User, decks: Collection<int, Deck>}
     *
     * @throws ValidationException
     */
    public function transfer(User $from, mixed $deckIds, mixed $username): array
    {
        Validator::make(['decks' => $deckIds, 'to_username' => $username], [
            'decks' => ['required', 'array'],
            'decks.*' => ['uuid'],
            'to_username' => ['required', 'string'],
        ], [
            'decks.required' => 'Wähle mindestens einen Stapel.',
            'decks.*.uuid' => 'Die Liste hat sich geändert. Bitte neu laden.',
        ])->validate();

        /** @var list<string> $deckIds */
        $deckIds = array_values(array_unique($deckIds));
        $to = User::where('username', UserService::normalizeUsername($username))->first();
        if ($to === null || ! $to->isApproved() || ! $to->is_active) {
            throw ValidationException::withMessages(['to_username' => 'Kein freigegebenes, aktives Konto mit diesem Benutzernamen.']);
        }
        if ($to->is($from)) {
            throw ValidationException::withMessages(['to_username' => "Die Stapel gehören schon {$to->username}."]);
        }

        return DB::transaction(function () use ($from, $to, $deckIds) {
            $decks = $from->decks()->whereKey($deckIds)->orderBy('id')->lockForUpdate()->get();
            if ($decks->count() !== count($deckIds)) {
                throw ValidationException::withMessages(['decks' => 'Die Liste hat sich geändert. Bitte neu laden.']);
            }

            foreach ($decks as $deck) {
                // Not fillable: nobody hands over a deck through a form field.
                $deck->forceFill(['user_id' => $to->id])->save();
            }
            $to->followedDecks()->detach($deckIds);
            // Owners are never on their own share list.
            DB::table('deck_shares')->whereIn('deck_id', $deckIds)->where('user_id', $to->id)->delete();
            $shared = $decks->where('is_shared', true)->modelKeys();
            $from->followedDecks()->syncWithoutDetaching($shared);
            // A deck one learns from somebody else is "Lerne ich" or "pausiert", never without either.
            DB::table('deck_preferences')->where('user_id', $from->id)->whereIn('deck_id', $shared)->update(['learning' => true]);

            return ['to' => $to, 'decks' => $decks];
        });
    }

    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, description: string|null, front_language: string, back_language: string}
     *
     * @throws ValidationException
     */
    private function validate(array $input): array
    {
        // Only strings are trimmed; anything else (e.g. name[]=x) is left for
        // the 'string' rule to reject with a proper message.
        if (is_string($input['name'] ?? null)) {
            $input['name'] = trim($input['name']);
        }
        if (is_string($input['description'] ?? null)) {
            $input['description'] = trim($input['description']) === '' ? null : trim($input['description']);
        }

        /** @var array{name: string, description?: string|null, front_language: string, back_language: string} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'front_language' => ['required', Rule::enum(Language::class)],
            'back_language' => ['required', Rule::enum(Language::class), 'different:front_language'],
        ], [
            'back_language.different' => 'Vorne und hinten brauchen verschiedene Sprachen.',
        ])->validate();

        return [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'front_language' => $data['front_language'],
            'back_language' => $data['back_language'],
        ];
    }

    /** Postgres rejects malformed UUIDs with an error — answer 404 instead of 500. */
    private function ensureUuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$id]);
        }
    }
}
