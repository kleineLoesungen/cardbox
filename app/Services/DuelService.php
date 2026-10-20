<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Deck;
use App\Models\Duel;
use App\Models\DuelPlayer;
use App\Models\User;
use App\Support\ShareCode;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Duels (docs/specs/duell.md): someone picks a deck and a number of players,
 * everybody gets the same ten words, most right answers win, then the
 * shorter time.
 *
 * * Members join with the code; guests with the code and a name and get a
 *   secret link instead of an account — no user row, no approval touched.
 * * The questions are copied when the duel is made, so editing or deleting
 *   the deck changes nothing, and other players never see the deck itself.
 * * Time is measured here, per card from showing to answering, at most
 *   MAX_CARD_MS — a break does not cost the whole duel.
 * * There is no scheduler: a duel past its 24 hours is closed when somebody
 *   looks at it. Its result stays visible for RESULT_HOURS after the end —
 *   to players and to anybody with the code — then the duel with code,
 *   ranking and results is deleted (prune(), on every way in).
 *
 * @phpstan-type DuelCard array{position: int, question: string, hint: string|null, answers: list<string>, choices: list<string>|null, correct_choice: int|null}
 * @phpstan-type Standing array{player: DuelPlayer, name: string, correct: int, time_ms: int, finished: bool, winner: bool}
 */
class DuelService
{
    public const CARDS = 10;

    public const MIN_CARDS = 4;

    public const MIN_PLAYERS = 2;

    public const MAX_PLAYERS = 10;

    public const MAX_CARD_MS = 60_000;

    public const HOURS = 24;

    /** Hours the result stays visible after the end; then the duel is deleted. */
    public const RESULT_HOURS = 24;

    public const MODES = ['choice', 'typed'];

    private const WRONG_CODE = 'Dieser Code passt nicht.';

    public function __construct(
        private DeckService $decks,
        private CardSelectionService $selection,
        private AnswerCheckService $checker,
        private LeitnerService $leitner,
    ) {}

    // -------------------------------------------------------------------------
    // Making a duel
    // -------------------------------------------------------------------------

    /**
     * "Duell starten" on a deck the person learns: draws the cards, fixes
     * the choices, makes the code. The creator is the first player.
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function create(User $user, string $deckId, mixed $players, mixed $mode): Duel
    {
        $deck = $this->decks->getVisible($user, $deckId);
        if (! $deck->isOwnedBy($user) && ! $this->decks->isFollowing($user, $deck)) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$deckId]);
        }

        $count = filter_var($players, FILTER_VALIDATE_INT);
        if ($count === false || $count < self::MIN_PLAYERS || $count > self::MAX_PLAYERS) {
            throw ValidationException::withMessages(['players' => 'Bitte '.self::MIN_PLAYERS.' bis '.self::MAX_PLAYERS.' Spieler wählen.']);
        }
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Bitte „Auswahl aus 4“ oder „Eintippen“ wählen.']);
        }

        $cards = $this->activeCards($user, $deck);
        if ($cards->count() < self::MIN_CARDS) {
            throw ValidationException::withMessages(['deck' => 'Für ein Duell braucht der Stapel mindestens '.self::MIN_CARDS.' Karten.']);
        }
        $rows = $this->questions($cards, $mode === 'choice');

        return DB::transaction(function () use ($user, $deck, $count, $mode, $rows) {
            $duel = $this->insertDuel([
                'creator_id' => $user->id,
                'deck_id' => $deck->id,
                'deck_name' => $deck->name,
                'front_language' => $deck->front_language,
                'back_language' => $deck->back_language,
                'mode' => $mode,
                'player_count' => $count,
                'expires_at' => now()->addHours(self::HOURS),
                'created_at' => now(),
            ]);
            DB::table('duel_cards')->insert(array_map(fn (array $row) => [
                'duel_id' => $duel->id,
                'position' => $row['position'],
                'question' => $row['question'],
                'hint' => $row['hint'],
                'answers' => json_encode($row['answers'], JSON_UNESCAPED_UNICODE),
                'choices' => $row['choices'] === null ? null : json_encode($row['choices'], JSON_UNESCAPED_UNICODE),
                'correct_choice' => $row['correct_choice'],
            ], $rows));
            $this->addPlayer($duel, ['user_id' => $user->id]);

            return $duel;
        });
    }

    /**
     * "Revanche": a new duel with the same deck, mode and number of players
     * — new cards, new code.
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck|Duel>
     */
    public function rematch(User $user, Duel $duel): Duel
    {
        if (! $this->canRematch($user, $duel)) {
            throw (new ModelNotFoundException)->setModel(Duel::class, [$duel->id]);
        }

        return $this->create($user, (string) $duel->deck_id, $duel->player_count, $duel->mode);
    }

    public function isPlayer(User $user, Duel $duel): bool
    {
        return $duel->players()->where('user_id', $user->id)->exists();
    }

    /** Until when a finished duel's result is shown; null while it runs. */
    public function resultUntil(Duel $duel): ?Carbon
    {
        return $duel->finished_at?->copy()->addHours(self::RESULT_HOURS);
    }

    /**
     * Deletes duels whose result has been shown long enough: RESULT_HOURS
     * after the end, or after the 24 hours to play plus RESULT_HOURS for a
     * duel nobody looked at since. Code, ranking and results go with it.
     */
    public function prune(): void
    {
        Duel::where(fn ($q) => $q
            ->where('finished_at', '<', now()->subHours(self::RESULT_HOURS))
            ->orWhere('expires_at', '<', now()->subHours(self::RESULT_HOURS)))
            ->delete();
    }

    /** The creator may take players out while the duel runs. */
    public function canManage(User $user, Duel $duel): bool
    {
        return $duel->creator_id === $user->id;
    }

    /** Only with a deck that still exists and that the person learns. */
    public function canRematch(User $user, Duel $duel): bool
    {
        if ($duel->deck_id === null) {
            return false;
        }
        $deck = Deck::find($duel->deck_id);

        return $deck !== null
            && $this->decks->visibleTo($user)->whereKey($deck->id)->exists()
            && ($deck->isOwnedBy($user) || $this->decks->isFollowing($user, $deck));
    }

    /** The code as shown ("D7P2-M9QX"), for the duel page; null after a change of APP_KEY. */
    public function code(Duel $duel): ?string
    {
        try {
            return ShareCode::format(Crypt::decryptString($duel->code_encrypted));
        } catch (DecryptException) {
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Joining
    // -------------------------------------------------------------------------

    /**
     * A member enters a code. Already in the duel, or the duel is over: just
     * returns it; `player` null means: not taking part, show the result.
     *
     * @return array{duel: Duel, player: DuelPlayer|null}
     *
     * @throws ValidationException
     */
    public function join(User $user, mixed $input): array
    {
        // Outside the transaction: deleting old duels must not hold locks while joining.
        $this->prune();

        return DB::transaction(function () use ($user, $input) {
            $duel = $this->duelForCode($input);
            $player = $duel?->players()->where('user_id', $user->id)->first();
            // Taking part already, or over: just leads to the duel or its result.
            if ($duel !== null && ($player !== null || $duel->isFinished())) {
                return ['duel' => $duel, 'player' => $player];
            }
            $duel = $this->ensureOpen($duel);
            $this->ensureFreeSlot($duel);

            return ['duel' => $duel, 'player' => $this->addPlayer($duel, ['user_id' => $user->id])];
        });
    }

    /**
     * A guest enters a code and a name; the returned token is the guest's
     * secret link (stored only as keyed hash). `$known` are links this
     * browser already holds: a second "Mitmachen" leads to the same place.
     * A finished duel comes back without token: its result is open to
     * anybody with the code.
     *
     * @param  list<string>  $known
     * @return array{duel: Duel, token: string|null}
     *
     * @throws ValidationException
     */
    public function joinAsGuest(mixed $input, mixed $name, array $known = []): array
    {
        $this->prune();

        return DB::transaction(function () use ($input, $name, $known) {
            $duel = $this->duelForCode($input);
            foreach ($known as $token) {
                if ($duel !== null && $duel->players()->where('guest_token_hash', ShareCode::hash($token))->exists()) {
                    return ['duel' => $duel, 'token' => $token];
                }
            }
            // Over: the result is open to anybody with the code, no name needed.
            if ($duel !== null && $duel->isFinished()) {
                return ['duel' => $duel, 'token' => null];
            }
            $duel = $this->ensureOpen($duel);

            $name = is_string($name) ? trim((string) preg_replace('/\s+/u', ' ', $name)) : '';
            if (mb_strlen($name) < 2 || mb_strlen($name) > 20 || preg_match('/\p{C}/u', $name)) {
                throw ValidationException::withMessages(['name' => 'Bitte einen Namen mit 2 bis 20 Zeichen eingeben.']);
            }
            $this->ensureFreeSlot($duel);
            $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
            $this->addPlayer($duel, ['guest_name' => $name, 'guest_token_hash' => ShareCode::hash($token)]);

            return ['duel' => $duel, 'token' => $token];
        });
    }

    // -------------------------------------------------------------------------
    // Reading
    // -------------------------------------------------------------------------

    /**
     * The duel and the person's place in it; not taking part is "not found",
     * for admins too.
     *
     * @return array{duel: Duel, player: DuelPlayer}
     *
     * @throws ModelNotFoundException<Duel>
     */
    public function getForMember(User $user, string $duelId): array
    {
        if (! Str::isUuid($duelId)) {
            throw (new ModelNotFoundException)->setModel(Duel::class, [$duelId]);
        }
        $this->prune();
        $player = DuelPlayer::with('duel')->where('duel_id', $duelId)->where('user_id', $user->id)->first();
        if ($player === null) {
            throw (new ModelNotFoundException)->setModel(Duel::class, [$duelId]);
        }
        $this->closeIfDue($player->duel);

        return ['duel' => $player->duel, 'player' => $player];
    }

    /**
     * A finished duel whose result is still shown, for the result page after
     * entering its code (the caller checks the code was entered).
     *
     * @throws ModelNotFoundException<Duel>
     */
    public function getResult(string $duelId): Duel
    {
        $this->prune();
        $duel = Str::isUuid($duelId) ? Duel::find($duelId) : null;
        if ($duel !== null) {
            $this->closeIfDue($duel);
        }
        if ($duel === null || ! $duel->isFinished()) {
            throw (new ModelNotFoundException)->setModel(Duel::class, [$duelId]);
        }

        return $duel;
    }

    /**
     * A guest's duel by the secret link.
     *
     * @return array{duel: Duel, player: DuelPlayer}
     *
     * @throws ModelNotFoundException<Duel>
     */
    public function getForGuest(string $token): array
    {
        $this->prune();
        $player = DuelPlayer::with('duel')->where('guest_token_hash', ShareCode::hash($token))->first();
        if ($player === null) {
            throw (new ModelNotFoundException)->setModel(Duel::class, ['guest']);
        }
        $this->closeIfDue($player->duel);

        return ['duel' => $player->duel, 'player' => $player];
    }

    /**
     * "Meine Duelle": running first, then finished ones whose result is still
     * shown.
     *
     * @return Collection<int, Duel>
     */
    public function listFor(User $user): Collection
    {
        $this->prune();

        $duels = Duel::whereIn('id', DuelPlayer::where('user_id', $user->id)->select('duel_id'))
            ->with('players.user')
            ->orderByDesc('created_at')
            ->get();
        $duels->each(fn (Duel $duel) => $this->closeIfDue($duel));

        return $duels->sortBy(fn (Duel $duel) => $duel->isFinished() ? 1 : 0)->values();
    }

    /**
     * Ranking: finished players by right answers, then time; the rest below.
     * Winners only once the duel is over and at least two played.
     *
     * @return list<Standing>
     */
    public function standings(Duel $duel): array
    {
        $players = $duel->relationLoaded('players') ? $duel->players : $duel->players()->with('user')->get();
        $finished = $players->filter(fn (DuelPlayer $p) => $p->finished_at !== null)
            ->sortBy([['correct', 'desc'], ['time_ms', 'asc']])
            ->values();
        $open = $players->filter(fn (DuelPlayer $p) => $p->finished_at === null)->sortBy('joined_at')->values();

        $best = $finished->first();
        $decided = $duel->isFinished() && $finished->count() >= 2;

        $rows = [];
        foreach ($finished->concat($open) as $p) {
            $rows[] = [
                'player' => $p,
                'name' => $p->displayName(),
                'correct' => $p->correct,
                'time_ms' => $p->time_ms,
                'finished' => $p->finished_at !== null,
                'winner' => $decided && $best !== null && $p->finished_at !== null
                    && $p->correct === $best->correct && $p->time_ms === $best->time_ms,
            ];
        }

        return $rows;
    }

    /** Free places while the duel runs. */
    public function freeSlots(Duel $duel): int
    {
        return $duel->isFinished() ? 0 : max(0, $duel->player_count - $duel->players()->count());
    }

    public function cardCount(Duel $duel): int
    {
        return DB::table('duel_cards')->where('duel_id', $duel->id)->count();
    }

    // -------------------------------------------------------------------------
    // Playing
    // -------------------------------------------------------------------------

    /**
     * The card that is up for the player; starts its clock when shown the
     * first time. Null when the player is done or the duel is over.
     *
     * @return DuelCard|null
     */
    public function current(Duel $duel, DuelPlayer $player): ?array
    {
        if ($player->finished_at !== null || $duel->isFinished()) {
            return null;
        }
        $card = $this->card($duel, $player->position + 1);
        if ($card !== null && $player->shown_at === null) {
            $player->forceFill(['shown_at' => now()])->save();
        }

        return $card;
    }

    /**
     * Rates the answer to the card that is up (`step` = its position). An
     * answer to another card or a second one is ignored (null). Members get
     * right answers counted for their daily goal.
     *
     * @return array{right: bool, verdict: 'right'|'close'|'wrong', expected: string, done: bool}|null
     */
    public function answer(Duel $duel, DuelPlayer $player, int $step, mixed $choice, mixed $typed): ?array
    {
        return DB::transaction(function () use ($duel, $player, $step, $choice, $typed) {
            /** @var DuelPlayer $locked */
            $locked = DuelPlayer::whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ($locked->finished_at !== null || $duel->fresh()?->isFinished() || $step !== $locked->position + 1) {
                return null;
            }
            $card = $this->card($duel, $step);
            if ($card === null) {
                return null;
            }

            if ($duel->mode === 'choice') {
                $picked = filter_var($choice, FILTER_VALIDATE_INT);
                $verdict = $picked !== false && $picked === $card['correct_choice'] ? 'right' : 'wrong';
                $expected = $card['answers'][0] ?? '';
            } else {
                $exact = in_array($duel->back_language, ['formula', 'result'], true);
                $check = $this->checker->check($card['answers'], is_string($typed) ? $typed : '', $exact);
                $verdict = $check['verdict'];
                $expected = $check['expected'];
            }
            $right = $verdict !== 'wrong';

            // Shown_at missing (an answer without the card): counts as the full time.
            $elapsed = $locked->shown_at === null
                ? self::MAX_CARD_MS
                : min(self::MAX_CARD_MS, max(0, (int) $locked->shown_at->diffInMilliseconds(now())));
            $done = $step >= $this->cardCount($duel);
            $locked->forceFill([
                'position' => $step,
                'correct' => $locked->correct + ($right ? 1 : 0),
                'time_ms' => $locked->time_ms + $elapsed,
                'shown_at' => null,
                'finished_at' => $done ? now() : null,
            ])->save();

            if ($right && $locked->user_id !== null) {
                $this->leitner->countAnswer(User::findOrFail($locked->user_id));
            }
            if ($done) {
                $this->closeIfDue($duel);
            }

            return ['right' => $right, 'verdict' => $verdict, 'expected' => $expected, 'done' => $done];
        });
    }

    /**
     * The creator takes somebody out of the duel (also a guest whose code got
     * around); the place becomes free again.
     *
     * @throws ModelNotFoundException<DuelPlayer>
     */
    public function removePlayer(User $user, string $duelId, string $playerId): DuelPlayer
    {
        ['duel' => $duel] = $this->getForMember($user, $duelId);
        $player = Str::isUuid($playerId)
            ? $duel->players()->with('user')->whereKey($playerId)->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))->first()
            : null;
        // After the end the result stands: nobody is taken out any more.
        if (! $this->canManage($user, $duel) || $duel->isFinished() || $player === null) {
            throw (new ModelNotFoundException)->setModel(DuelPlayer::class, [$playerId]);
        }
        $player->delete();
        $this->closeIfDue($duel);

        return $player;
    }

    // -------------------------------------------------------------------------

    /**
     * Over when as many as set have finished, or 24 hours after it was made.
     */
    private function closeIfDue(Duel $duel): void
    {
        if ($duel->isFinished()) {
            return;
        }
        $finished = $duel->relationLoaded('players')
            ? $duel->players->whereNotNull('finished_at')->count()
            : $duel->players()->whereNotNull('finished_at')->count();
        if ($finished >= $duel->player_count) {
            $duel->forceFill(['finished_at' => now()])->save();
        } elseif ($duel->expires_at->isPast()) {
            $duel->forceFill(['finished_at' => $duel->expires_at])->save();
        }
    }

    /**
     * The duel behind a code, locked against a second join; null when there
     * is none.
     *
     * @throws ValidationException
     */
    private function duelForCode(mixed $input): ?Duel
    {
        $code = ShareCode::normalize($input);
        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'Bitte den Code eingeben.']);
        }
        $duel = $code === null ? null : Duel::where('code_hash', ShareCode::hash($code))->lockForUpdate()->first();
        if ($duel !== null) {
            $this->closeIfDue($duel);
        }

        return $duel;
    }

    /** @throws ValidationException */
    private function ensureOpen(?Duel $duel): Duel
    {
        if ($duel === null || $duel->isFinished()) {
            throw ValidationException::withMessages(['code' => self::WRONG_CODE]);
        }

        return $duel;
    }

    /**
     * A member (`user_id`) or a guest (`guest_name`, `guest_token_hash`).
     *
     * @param  array<string, string>  $who
     */
    private function addPlayer(Duel $duel, array $who): DuelPlayer
    {
        $player = (new DuelPlayer)->forceFill($who + ['duel_id' => $duel->id, 'joined_at' => now()]);
        $player->save();

        return $player;
    }

    /** @throws ValidationException */
    private function ensureFreeSlot(Duel $duel): void
    {
        if ($duel->players()->count() >= $duel->player_count) {
            throw ValidationException::withMessages(['code' => 'Das Duell ist schon voll.']);
        }
    }

    /**
     * Inserts the duel with a fresh code; another duel holding the same code
     * (about 1 in 10¹²) means drawing again.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function insertDuel(array $attributes): Duel
    {
        for ($attempt = 1; ; $attempt++) {
            $code = ShareCode::generate();
            try {
                return DB::transaction(function () use ($attributes, $code) {
                    $duel = (new Duel)->forceFill($attributes + [
                        'code_hash' => ShareCode::hash($code),
                        'code_encrypted' => Crypt::encryptString($code),
                    ]);
                    $duel->save();

                    return $duel;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Active cards of the deck for the person (issue #10 selection), oldest
     * first.
     *
     * @return Collection<int, Card>
     */
    private function activeCards(User $user, Deck $deck): Collection
    {
        $cards = $deck->cards()->orderBy('created_at')->get();
        $active = $this->selection->activeIds($user, $deck);

        return $active === null ? $cards : $cards->filter(fn (Card $c) => in_array($c->id, $active, true))->values();
    }

    /**
     * Ten random cards front → back; with choices three wrong answers each,
     * drawn from the deck's other main meanings.
     *
     * @param  Collection<int, Card>  $cards
     * @return list<DuelCard>
     *
     * @throws ValidationException
     */
    private function questions(Collection $cards, bool $withChoices): array
    {
        // Main meanings of the deck, once each (a list: "42" must stay a string).
        $pool = [];
        foreach ($cards as $card) {
            $pool[self::key($card->backText())] ??= $card->backText();
        }
        $pool = array_map(fn ($key, string $text) => [(string) $key, $text], array_keys($pool), $pool);
        $tooFew = ValidationException::withMessages(['mode' => 'Für „Auswahl aus 4“ braucht der Stapel mindestens 4 verschiedene Antworten.']);
        if ($withChoices && count($pool) < 4) {
            throw $tooFew;
        }

        $picked = $cards->shuffle()->take(self::CARDS)->values();
        $rows = [];
        foreach ($picked as $i => $card) {
            $choices = null;
            $correct = null;
            if ($withChoices) {
                // No meaning of this card may show up as a "wrong" choice.
                $own = array_map(self::key(...), $card->back);
                $wrong = array_values(array_map(fn (array $entry) => $entry[1], array_filter($pool, fn (array $entry) => ! in_array($entry[0], $own, true))));
                if (count($wrong) < 3) {
                    throw $tooFew;
                }
                shuffle($wrong);
                $choices = array_merge([$card->backText()], array_slice($wrong, 0, 3));
                shuffle($choices);
                $correct = (int) array_search($card->backText(), $choices, true);
            }
            $rows[] = [
                'position' => $i + 1,
                'question' => $card->frontText(),
                'hint' => $card->hint,
                'answers' => $card->back,
                'choices' => $choices,
                'correct_choice' => $correct,
            ];
        }

        return $rows;
    }

    /** Comparable form of an answer: case and outer spaces do not matter. */
    private static function key(string $text): string
    {
        return mb_strtolower(trim($text));
    }

    /** @return DuelCard|null */
    private function card(Duel $duel, int $position): ?array
    {
        $row = DB::table('duel_cards')->where('duel_id', $duel->id)->where('position', $position)->first();
        if ($row === null) {
            return null;
        }

        return [
            'position' => (int) $row->position,
            'question' => (string) $row->question,
            'hint' => $row->hint === null ? null : (string) $row->hint,
            'answers' => array_values(json_decode((string) $row->answers, true) ?: []),
            'choices' => $row->choices === null ? null : array_values(json_decode((string) $row->choices, true) ?: []),
            'correct_choice' => $row->correct_choice === null ? null : (int) $row->correct_choice,
        ];
    }
}
