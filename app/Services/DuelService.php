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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Duels (docs/specs/duell.md): someone picks a deck, a number of players and
 * one to five rounds of ten words; everybody gets the same words, after each
 * round the ranking, then the creator starts the next round. Most right
 * answers over all rounds win, then the shorter time.
 *
 * * All rounds are drawn when the duel is made, without repeating a word
 *   until the deck ran out — the next round only opens the next cards.
 * * Members join with the code; guests with the code and a name and get a
 *   secret link instead of an account — no user row, no approval touched.
 * * The questions are copied when the duel is made, so editing or deleting
 *   the deck changes nothing, and other players never see the deck itself.
 * * Time is measured here, per card from showing to answering, at most
 *   MAX_CARD_MS — a break does not cost the whole duel.
 * * Each round has HOURS to play; then it is over for whoever did not
 *   finish. The creator has HOURS to start the next round, else the duel
 *   ends. There is no scheduler: all this is checked when somebody looks
 *   (closeIfDue). The result stays visible for RESULT_HOURS after the end —
 *   to players and to anybody with the code — then the duel with code,
 *   ranking and results is deleted (prune(), on every way in).
 *
 * @phpstan-type DuelCard array{position: int, question: string, hint: string|null, answers: list<string>, choices: list<string>|null, correct_choice: int|null}
 * @phpstan-type DuelQuestion array{position: int, round: int, question: string, hint: string|null, answers: list<string>, choices: list<string>|null, correct_choice: int|null, card_id: string}
 * @phpstan-type Standing array{player: DuelPlayer, name: string, correct: int, time_ms: int, played: bool, finished: bool, winner: bool}
 * @phpstan-type RoundStanding array{player: DuelPlayer, name: string, correct: int, time_ms: int, finished: bool}
 */
class DuelService
{
    public const CARDS = 10;

    public const MIN_CARDS = 4;

    public const MAX_ROUNDS = 5;

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
     * "Duell starten" on a deck the person learns: draws the cards of all
     * rounds, fixes the choices, makes the code. The creator is the first
     * player.
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function create(User $user, string $deckId, mixed $players, mixed $mode, mixed $rounds = 1): Duel
    {
        $deck = $this->decks->getVisible($user, $deckId);
        if (! $deck->isOwnedBy($user) && ! $this->decks->isFollowing($user, $deck)) {
            throw (new ModelNotFoundException)->setModel(Deck::class, [$deckId]);
        }

        $count = filter_var($players, FILTER_VALIDATE_INT);
        if ($count === false || $count < self::MIN_PLAYERS || $count > self::MAX_PLAYERS) {
            throw ValidationException::withMessages(['players' => 'Bitte '.self::MIN_PLAYERS.' bis '.self::MAX_PLAYERS.' Spieler wählen.']);
        }
        $roundCount = filter_var($rounds, FILTER_VALIDATE_INT);
        if ($roundCount === false || $roundCount < 1 || $roundCount > self::MAX_ROUNDS) {
            throw ValidationException::withMessages(['rounds' => 'Bitte 1 bis '.self::MAX_ROUNDS.' Runden wählen.']);
        }
        if (! in_array($mode, self::MODES, true)) {
            throw ValidationException::withMessages(['mode' => 'Bitte „Auswahl aus 4“ oder „Eintippen“ wählen.']);
        }

        $cards = $this->activeCards($user, $deck);
        if ($cards->count() < self::MIN_CARDS) {
            throw ValidationException::withMessages(['deck' => 'Für ein Duell braucht der Stapel mindestens '.self::MIN_CARDS.' Karten.']);
        }
        ['rows' => $rows, 'repeat_from' => $repeatFrom] = $this->drawRounds($cards, $roundCount, $mode === 'choice');

        return DB::transaction(function () use ($user, $deck, $count, $mode, $roundCount, $rows, $repeatFrom) {
            $duel = $this->insertDuel([
                'creator_id' => $user->id,
                'deck_id' => $deck->id,
                'deck_name' => $deck->name,
                'front_language' => $deck->front_language,
                'back_language' => $deck->back_language,
                'mode' => $mode,
                'player_count' => $count,
                'rounds' => $roundCount,
                'round' => 1,
                'repeat_from_round' => $repeatFrom,
                'expires_at' => now()->addHours(self::HOURS),
                'created_at' => now(),
            ]);
            DB::table('duel_cards')->insert(array_map(fn (array $row) => [
                'duel_id' => $duel->id,
                'position' => $row['position'],
                'round' => $row['round'],
                'question' => $row['question'],
                'hint' => $row['hint'],
                'answers' => json_encode($row['answers'], JSON_UNESCAPED_UNICODE),
                'choices' => $row['choices'] === null ? null : json_encode($row['choices'], JSON_UNESCAPED_UNICODE),
                'correct_choice' => $row['correct_choice'],
                'card_id' => $row['card_id'],
            ], $rows));
            $this->addPlayer($duel, ['user_id' => $user->id]);

            return $duel;
        });
    }

    /**
     * "Runde N starten": only the creator, only between rounds. Who did not
     * finish the last round skips its rest.
     *
     * @throws ValidationException when the time for it ran out
     * @throws ModelNotFoundException<Duel>
     */
    public function nextRound(User $user, string $duelId): Duel
    {
        ['duel' => $duel] = $this->getForMember($user, $duelId);

        return DB::transaction(function () use ($user, $duel) {
            /** @var Duel $locked */
            $locked = Duel::whereKey($duel->id)->lockForUpdate()->firstOrFail();
            if ($this->canManage($user, $locked) && $locked->isFinished() && $locked->round < $locked->rounds) {
                // The page was still open when the time for the next round ran out.
                throw ValidationException::withMessages(['round' => 'Die Zeit für Runde '.($locked->round + 1).' ist abgelaufen – das Duell ist beendet.']);
            }
            if (! $this->canManage($user, $locked) || ! $locked->isBetweenRounds() || $locked->round >= $locked->rounds) {
                throw (new ModelNotFoundException)->setModel(Duel::class, [$duel->id]);
            }
            $start = $this->lastPosition($locked, $locked->round);
            $locked->forceFill([
                'round' => $locked->round + 1,
                'round_finished_at' => null,
                'expires_at' => now()->addHours(self::HOURS),
            ])->save();
            $locked->players()->update([
                'position' => $start,
                'round_correct' => 0,
                'round_time_ms' => 0,
                'shown_at' => null,
                'finished_at' => null,
            ]);

            return $locked;
        });
    }

    /** The creator may take players out and start the next round. */
    public function canStartNextRound(User $user, Duel $duel): bool
    {
        return $this->canManage($user, $duel) && $duel->isBetweenRounds() && $duel->round < $duel->rounds;
    }

    /** Until when the creator may start the next round; null unless between rounds. */
    public function nextRoundUntil(Duel $duel): ?Carbon
    {
        return $duel->isBetweenRounds() ? $duel->round_finished_at?->copy()->addHours(self::HOURS) : null;
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
     * after the end — or, for a duel nobody looked at since, after the round
     * deadline plus the creator's time for the next round plus RESULT_HOURS.
     * Code, ranking and results go with it. At most once a minute: duel
     * pages ask for their state every few seconds.
     */
    public function prune(): void
    {
        if (! Cache::add('duel-prune', true, 60)) {
            return;
        }
        $result = now()->subHours(self::RESULT_HOURS);
        $waited = now()->subHours(self::HOURS + self::RESULT_HOURS);
        Duel::where(fn ($q) => $q
            ->where('finished_at', '<', $result)
            ->orWhere(fn ($q) => $q->whereNull('finished_at')->where(fn ($q) => $q
                // The last round ran out: over at its deadline.
                ->where(fn ($q) => $q->whereColumn('round', '>=', 'rounds')->whereNull('round_finished_at')->where('expires_at', '<', $result))
                // Another round ran out, or waits for the creator: plus the creator's time.
                ->orWhere(fn ($q) => $q->whereColumn('round', '<', 'rounds')->whereNull('round_finished_at')->where('expires_at', '<', $waited))
                ->orWhere('round_finished_at', '<', $waited))))
            ->delete();
    }

    /**
     * Fingerprint of everything the duel page shows: unchanged, the page
     * asking for its state needs nothing new (DuelController::live).
     */
    public function state(Duel $duel): string
    {
        $players = $duel->players()->orderBy('id')->get(['id', 'position', 'correct', 'time_ms', 'answered', 'finished_at']);

        return sha1((string) json_encode([
            $duel->round, $duel->expires_at, $duel->round_finished_at, $duel->finished_at,
            $players->map(fn (DuelPlayer $p) => [$p->id, $p->position, $p->correct, $p->time_ms, $p->answered, $p->finished_at])->all(),
        ]));
    }

    /** The creator may take players out while the duel runs. */
    public function canManage(User $user, Duel $duel): bool
    {
        return $duel->creator_id === $user->id;
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
     * Overall ranking: right answers of all finished rounds, then the shorter
     * time of those rounds. A round somebody did not finish counts nothing —
     * so stopping early never pays off. Who finished no round yet stands
     * below. Winners once the duel is over and at least two took part
     * (answered a card); a winner finished a round.
     * `finished`: done with the round that is up.
     *
     * @return list<Standing>
     */
    public function standings(Duel $duel): array
    {
        $players = $duel->relationLoaded('players') ? $duel->players : $duel->players()->with('user')->get();
        $sums = DB::table('duel_round_results')
            ->whereIn('player_id', $players->modelKeys())
            ->groupBy('player_id')
            ->select('player_id')
            ->selectRaw('sum(correct) as correct, sum(time_ms) as time_ms')
            ->get()
            ->keyBy('player_id');

        $rows = [];
        foreach ($players as $p) {
            $sum = $sums->get($p->id);
            $rows[] = [
                'player' => $p,
                'name' => $p->displayName(),
                'correct' => (int) ($sum->correct ?? 0),
                'time_ms' => (int) ($sum->time_ms ?? 0),
                'played' => $sum !== null,
                'finished' => $p->finished_at !== null,
                'winner' => false,
            ];
        }
        usort($rows, fn (array $a, array $b) => [$b['played'], $b['correct'], $a['time_ms'], $a['player']->joined_at]
            <=> [$a['played'], $a['correct'], $b['time_ms'], $b['player']->joined_at]);

        $played = array_values(array_filter($rows, fn (array $r) => $r['played']));
        $tookPart = $players->filter(fn (DuelPlayer $p) => $p->answered > 0)->count();
        if ($duel->isFinished() && $tookPart >= 2 && $played !== []) {
            foreach ($rows as $i => $row) {
                $rows[$i]['winner'] = $row['played'] && $row['correct'] === $played[0]['correct'] && $row['time_ms'] === $played[0]['time_ms'];
            }
        }

        return $rows;
    }

    /**
     * Ranking of one round: who finished it, by right answers, then time;
     * the others below as "nicht fertig".
     *
     * @return list<RoundStanding>
     */
    public function roundStandings(Duel $duel, int $round): array
    {
        $players = $duel->players()->with('user')->get();
        $results = DB::table('duel_round_results')->whereIn('player_id', $players->modelKeys())->where('round', $round)->get()->keyBy('player_id');

        $rows = [];
        foreach ($players as $p) {
            $result = $results->get($p->id);
            $rows[] = [
                'player' => $p,
                'name' => $p->displayName(),
                'correct' => (int) ($result->correct ?? 0),
                'time_ms' => (int) ($result->time_ms ?? 0),
                'finished' => $result !== null,
            ];
        }
        usort($rows, fn (array $a, array $b) => [$b['finished'], $b['correct'], $a['time_ms']] <=> [$a['finished'], $a['correct'], $b['time_ms']]);

        return $rows;
    }

    /** Free places while the duel runs. */
    public function freeSlots(Duel $duel): int
    {
        return $duel->isFinished() || $duel->round > 1 || $duel->isBetweenRounds() ? 0 : max(0, $duel->player_count - $duel->players()->count());
    }

    /** Words in the round that is up. */
    public function cardCount(Duel $duel): int
    {
        return DB::table('duel_cards')->where('duel_id', $duel->id)->where('round', $duel->round)->count();
    }

    /** Position of the first word of the round that is up, minus one. */
    public function roundStart(Duel $duel): int
    {
        return $duel->round > 1 ? $this->lastPosition($duel, $duel->round - 1) : 0;
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
        if ($player->finished_at !== null || $duel->isFinished() || $duel->isBetweenRounds()) {
            return null;
        }
        $card = $this->card($duel, $player->position + 1, $duel->round);
        if ($card !== null && $player->shown_at === null) {
            $player->forceFill(['shown_at' => now()])->save();
        }

        return $card;
    }

    /**
     * Rates the answer to the card that is up (`step` = its position). An
     * answer to another card, a second one or one between rounds is ignored
     * (null). Members get right answers counted for their daily goal. The
     * last card of a round stores the player's round result.
     *
     * @return array{right: bool, verdict: 'right'|'close'|'wrong', expected: string, done: bool}|null
     */
    public function answer(Duel $duel, DuelPlayer $player, int $step, mixed $choice, mixed $typed): ?array
    {
        return DB::transaction(function () use ($duel, $player, $step, $choice, $typed) {
            // Duel before player, like nextRound(): the same order never deadlocks.
            $fresh = Duel::whereKey($duel->id)->lockForUpdate()->first();
            /** @var DuelPlayer $locked */
            $locked = DuelPlayer::whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ($fresh === null || $locked->finished_at !== null || $fresh->isFinished() || $fresh->isBetweenRounds() || $step !== $locked->position + 1) {
                return null;
            }
            $card = $this->card($fresh, $step, $fresh->round);
            if ($card === null) {
                return null;
            }

            if ($fresh->mode === 'choice') {
                $picked = filter_var($choice, FILTER_VALIDATE_INT);
                $verdict = $picked !== false && $picked === $card['correct_choice'] ? 'right' : 'wrong';
                $expected = $card['answers'][0] ?? '';
            } else {
                $exact = in_array($fresh->back_language, ['formula', 'result'], true);
                $check = $this->checker->check($card['answers'], is_string($typed) ? $typed : '', $exact);
                $verdict = $check['verdict'];
                $expected = $check['expected'];
            }
            $right = $verdict !== 'wrong';

            // Shown_at missing (an answer without the card): counts as the full time.
            $elapsed = $locked->shown_at === null
                ? self::MAX_CARD_MS
                : min(self::MAX_CARD_MS, max(0, (int) $locked->shown_at->diffInMilliseconds(now())));
            $done = $step >= $this->lastPosition($fresh, $fresh->round);
            $locked->forceFill([
                'position' => $step,
                'correct' => $locked->correct + ($right ? 1 : 0),
                'time_ms' => $locked->time_ms + $elapsed,
                'round_correct' => $locked->round_correct + ($right ? 1 : 0),
                'round_time_ms' => $locked->round_time_ms + $elapsed,
                'answered' => $locked->answered + 1,
                'shown_at' => null,
                'finished_at' => $done ? now() : null,
            ])->save();

            if ($right && $locked->user_id !== null) {
                $this->leitner->countAnswer(User::findOrFail($locked->user_id));
            }
            if ($done) {
                DB::table('duel_round_results')->insert([
                    'player_id' => $locked->id,
                    'round' => $fresh->round,
                    'correct' => $locked->round_correct,
                    'time_ms' => $locked->round_time_ms,
                ]);
                $this->closeIfDue($fresh);
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
     * The round that is up ends when everybody finished it (in round 1: as
     * many as set), or when its HOURS are over. Then the duel ends after the
     * last round; otherwise it waits for the creator, for HOURS at most.
     */
    private function closeIfDue(Duel $duel): void
    {
        if ($duel->isFinished()) {
            return;
        }
        if ($duel->isBetweenRounds()) {
            $until = $duel->round_finished_at->copy()->addHours(self::HOURS);
            if ($until->isPast()) {
                $duel->forceFill(['finished_at' => $until])->save();
            }

            return;
        }

        $players = $duel->relationLoaded('players') ? $duel->players : $duel->players()->get();
        $finished = $players->whereNotNull('finished_at')->count();
        $needed = $duel->round === 1 ? $duel->player_count : $players->count();
        $end = match (true) {
            $finished >= $needed => now(),
            $duel->expires_at->isPast() => $duel->expires_at,
            default => null,
        };
        if ($end === null) {
            return;
        }
        $duel->forceFill($duel->round >= $duel->rounds ? ['finished_at' => $end] : ['round_finished_at' => $end])->save();
        if ($duel->isBetweenRounds()) {
            // The creator may have waited too long already (a duel nobody looked at).
            $this->closeIfDue($duel);
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

    /** Joining only while round 1 runs. @throws ValidationException */
    private function ensureOpen(?Duel $duel): Duel
    {
        if ($duel === null || $duel->isFinished()) {
            throw ValidationException::withMessages(['code' => self::WRONG_CODE]);
        }
        if ($duel->round > 1 || $duel->isBetweenRounds()) {
            throw ValidationException::withMessages(['code' => 'Das Duell läuft schon – einsteigen geht nur in Runde 1.']);
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
     * The questions of all rounds: per round up to ten cards that were not
     * up yet; when fewer are left, the rest is filled with earlier ones and
     * the drawing starts over. `repeat_from` is the first round with words
     * that came before (null: none).
     *
     * @param  Collection<int, Card>  $cards  active cards of the deck
     * @return array{rows: list<DuelQuestion>, repeat_from: int|null}
     *
     * @throws ValidationException
     */
    private function drawRounds(Collection $cards, int $rounds, bool $withChoices): array
    {
        $need = min(self::CARDS, $cards->count());
        $used = [];
        $rows = [];
        $repeatFrom = null;
        for ($round = 1; $round <= $rounds; $round++) {
            $fresh = $cards->reject(fn (Card $c) => in_array($c->id, $used, true))->shuffle()->values();
            if ($fresh->count() >= $need) {
                $picked = $fresh->take($need)->values();
                $used = [...$used, ...$picked->modelKeys()];
            } else {
                // All words were up: the fresh rest, then earlier ones — and a new cycle.
                $again = $cards->filter(fn (Card $c) => in_array($c->id, $used, true))->shuffle()->take($need - $fresh->count());
                $picked = $fresh->concat($again)->shuffle()->values();
                $used = $picked->modelKeys();
                $repeatFrom ??= $round;
            }
            foreach ($this->questions($picked, $cards, $withChoices) as $row) {
                $rows[] = ['position' => count($rows) + 1, 'round' => $round] + $row;
            }
        }

        return ['rows' => $rows, 'repeat_from' => $repeatFrom];
    }

    /**
     * The drawn cards front → back; with choices three wrong answers each,
     * drawn from the main meanings of all `$cards` of the deck.
     *
     * @param  Collection<int, Card>  $picked
     * @param  Collection<int, Card>  $cards
     * @return list<array{question: string, hint: string|null, answers: list<string>, choices: list<string>|null, correct_choice: int|null, card_id: string}>
     *
     * @throws ValidationException
     */
    private function questions(Collection $picked, Collection $cards, bool $withChoices): array
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

        $rows = [];
        foreach ($picked as $card) {
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
                'question' => $card->frontText(),
                'hint' => $card->hint,
                'answers' => $card->back,
                'choices' => $choices,
                'correct_choice' => $correct,
                'card_id' => $card->id,
            ];
        }

        return $rows;
    }

    /** Position of the last word of a round. */
    private function lastPosition(Duel $duel, int $round): int
    {
        return (int) DB::table('duel_cards')->where('duel_id', $duel->id)->where('round', '<=', $round)->max('position');
    }

    /** Comparable form of an answer: case and outer spaces do not matter. */
    private static function key(string $text): string
    {
        return mb_strtolower(trim($text));
    }

    /** @return DuelCard|null */
    private function card(Duel $duel, int $position, int $round): ?array
    {
        $row = DB::table('duel_cards')->where('duel_id', $duel->id)->where('position', $position)->where('round', $round)->first();
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
