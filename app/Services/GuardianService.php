<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Parents see the progress (docs/specs/eltern-ansicht.md).
 *
 * A child hands out a one-time code; whoever redeems it may look at the
 * child's learning progress until either side ends the link. This is the
 * only place where one account reads another account's learning data, so
 * every read goes through getChild() first.
 *
 * Codes are stored as a keyed hash only (HMAC with the app key). Wrong,
 * expired and used codes give the same message, so nothing tells whether a
 * code ever existed.
 */
class GuardianService
{
    /** Without 0/O, 1/I/L: easy to read aloud and to type. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private const CODE_LENGTH = 8;

    public const MAX_GUARDIANS = 4;

    public const MAX_CHILDREN = 10;

    private const CODE_HOURS = 24;

    private const WRONG_CODE = 'Dieser Code passt nicht. Bitte das Kind um einen neuen.';

    public function __construct(
        private LearningOverviewService $overview,
        private LeitnerService $leitner,
        private DeckService $decks,
    ) {}

    /**
     * New code for the child, shown as "XXXX-XXXX". Replaces an open one.
     */
    public function createCode(User $child): string
    {
        $this->deleteExpiredCodes();

        for ($attempt = 1; ; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            try {
                DB::table('guardian_codes')->upsert([
                    'child_id' => $child->id,
                    'code_hash' => $this->hashCode($code),
                    'expires_at' => now()->addHours(self::CODE_HOURS),
                    'created_at' => now(),
                ], ['child_id'], ['code_hash', 'expires_at', 'created_at']);

                return substr($code, 0, 4).'-'.substr($code, 4);
            } catch (UniqueConstraintViolationException $e) {
                // Another child holds the same code (about 1 in 10¹²); draw again.
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** When the child's open code runs out, or null without one. */
    public function codeExpiry(User $child): ?Carbon
    {
        $expires = DB::table('guardian_codes')
            ->where('child_id', $child->id)
            ->where('expires_at', '>', now())
            ->value('expires_at');

        return $expires === null ? null : Carbon::parse($expires);
    }

    /**
     * "Kind hinzufügen": links the child behind the code to the guardian and
     * uses the code up.
     *
     * @throws ValidationException
     */
    public function redeem(User $guardian, mixed $input): User
    {
        $code = is_string($input) ? strtoupper((string) preg_replace('/[\s-]+/u', '', $input)) : '';
        if ($code === '') {
            throw ValidationException::withMessages(['code' => 'Bitte den Code vom Kind eingeben.']);
        }
        if (strlen($code) !== self::CODE_LENGTH || strspn($code, self::ALPHABET) !== self::CODE_LENGTH) {
            throw ValidationException::withMessages(['code' => self::WRONG_CODE]);
        }

        $this->deleteExpiredCodes();

        return DB::transaction(function () use ($guardian, $code) {
            $row = DB::table('guardian_codes')
                ->where('code_hash', $this->hashCode($code))
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();
            $child = $row === null ? null : User::find($row->child_id);
            if ($child === null) {
                throw ValidationException::withMessages(['code' => self::WRONG_CODE]);
            }

            if ($child->id === $guardian->id) {
                throw ValidationException::withMessages(['code' => 'Das ist dein eigener Code.']);
            }
            // Serialises redeeming for both accounts, so both limits hold. Fixed order: no deadlock.
            User::whereKey([$guardian->id, $child->id])->orderBy('id')->lockForUpdate()->get();
            $links = DB::table('guardian_links');
            if ((clone $links)->where('guardian_id', $guardian->id)->where('child_id', $child->id)->exists()) {
                throw ValidationException::withMessages(['code' => "{$child->name} ist schon in deiner Liste."]);
            }
            if ((clone $links)->where('child_id', $child->id)->count() >= self::MAX_GUARDIANS) {
                throw ValidationException::withMessages(['code' => 'Höchstens '.self::MAX_GUARDIANS.' Eltern je Kind.']);
            }
            if ((clone $links)->where('guardian_id', $guardian->id)->count() >= self::MAX_CHILDREN) {
                throw ValidationException::withMessages(['code' => 'Höchstens '.self::MAX_CHILDREN.' Kinder.']);
            }

            DB::table('guardian_links')->insert(['guardian_id' => $guardian->id, 'child_id' => $child->id, 'created_at' => now()]);
            DB::table('guardian_codes')->where('child_id', $child->id)->delete();

            return $child;
        });
    }

    /**
     * Who sees the child's progress.
     *
     * @return Collection<int, User>
     */
    public function guardiansOf(User $child): Collection
    {
        return User::whereIn('id', DB::table('guardian_links')->where('child_id', $child->id)->select('guardian_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Whose progress the guardian sees.
     *
     * @return Collection<int, User>
     */
    public function childrenOf(User $guardian): Collection
    {
        return User::whereIn('id', DB::table('guardian_links')->where('guardian_id', $guardian->id)->select('child_id'))
            ->orderBy('name')
            ->get();
    }

    /** For the menu: one cheap query. */
    public function hasChildren(User $guardian): bool
    {
        return DB::table('guardian_links')->where('guardian_id', $guardian->id)->exists();
    }

    /**
     * "Nicht mehr zeigen" by the child.
     *
     * @throws ModelNotFoundException<User>
     */
    public function unlinkAsChild(User $child, string $guardianId): User
    {
        return $this->unlink($guardianId, $child->id, $guardianId);
    }

    /**
     * "Entfernen" by the guardian.
     *
     * @throws ModelNotFoundException<User>
     */
    public function unlinkAsGuardian(User $guardian, string $childId): User
    {
        return $this->unlink($guardian->id, $childId, $childId);
    }

    /**
     * A child linked to the guardian; everything else is "not found", for
     * admins too.
     *
     * @throws ModelNotFoundException<User>
     */
    public function getChild(User $guardian, string $childId): User
    {
        $this->ensureUuid($childId);

        return User::whereKey($childId)
            ->whereIn('id', DB::table('guardian_links')->where('guardian_id', $guardian->id)->select('child_id'))
            ->firstOrFail();
    }

    /**
     * What a guardian sees of a child (eltern-ansicht.md, B). Read-only and
     * without card lists, answers or settings. A blocked account shows nothing.
     *
     * @return array{blocked: true}|array{
     *     blocked: false,
     *     streak: int,
     *     day: array{answers: int, goal: int, goal_met: bool},
     *     weeks: list<list<array{date: CarbonImmutable, today: bool, state: string}>>,
     *     learned_ever: bool,
     *     vocabulary: array{mastered: int, mastered_week: int, in_box: int},
     *     recent: list<string>,
     *     decks: Collection<int, Deck>,
     *     stats: array<string, array{due: int, mastered_percent: int}>,
     *     progress: array<string, array{new: int, learning: int, mastered: int, total: int, mastered_percent: float, learning_percent: float}>,
     *     paused: int,
     *     hard: list<array{word: string, deck: string}>
     * }
     */
    public function overview(User $child): array
    {
        if (! $child->is_active) {
            return ['blocked' => true];
        }

        [$decks, $stats] = $this->overview->myDecksWithStats($child);

        return [
            'blocked' => false,
            'streak' => $this->leitner->streak($child),
            'day' => $this->leitner->dayStatus($child),
            'weeks' => $this->calendar($child),
            'learned_ever' => DB::table('study_days')->where('user_id', $child->id)->exists(),
            'vocabulary' => $this->overview->vocabulary($child),
            'recent' => $this->overview->recentlyMastered($child, 10),
            'decks' => $decks,
            'stats' => $stats,
            'progress' => $this->overview->deckProgress($child, $decks),
            // Only how many, no names: parents see that something is paused (issue #15).
            'paused' => count($this->decks->pausedIds($child)),
            'hard' => $this->overview->hardWords($child, 20),
        ];
    }

    /**
     * Four weeks, Monday to Sunday, ending with the current one. Each day:
     * "goal" (daily goal met), "learned", "none" or "future". Days in the
     * learning time zone, like study_days.
     *
     * @return list<list<array{date: CarbonImmutable, today: bool, state: string}>>
     */
    private function calendar(User $child): array
    {
        $today = $this->leitner->today();
        $start = $today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(3);

        $days = DB::table('study_days')
            ->where('user_id', $child->id)
            ->whereBetween('day', [$start->toDateString(), $today->toDateString()])
            ->get(['day', 'goal_met', 'reviews', 'answers'])
            ->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

        $weeks = [];
        for ($week = 0; $week < 4; $week++) {
            $row = [];
            for ($weekday = 0; $weekday < 7; $weekday++) {
                $date = $start->addDays($week * 7 + $weekday);
                $day = $days->get($date->toDateString());
                $row[] = ['date' => $date, 'today' => $date->equalTo($today), 'state' => match (true) {
                    $date->greaterThan($today) => 'future',
                    $day !== null && $day->goal_met => 'goal',
                    $day !== null && ($day->reviews > 0 || $day->answers > 0) => 'learned',
                    default => 'none',
                }];
            }
            $weeks[] = $row;
        }

        return $weeks;
    }

    /** @throws ModelNotFoundException<User> */
    private function unlink(string $guardianId, string $childId, string $otherId): User
    {
        $this->ensureUuid($otherId);

        return DB::transaction(function () use ($guardianId, $childId, $otherId) {
            $deleted = DB::table('guardian_links')->where('guardian_id', $guardianId)->where('child_id', $childId)->delete();
            if ($deleted === 0) {
                throw (new ModelNotFoundException)->setModel(User::class, [$otherId]);
            }

            return User::findOrFail($otherId);
        });
    }

    /** @throws ModelNotFoundException<User> */
    private function ensureUuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(User::class, [$id]);
        }
    }

    /**
     * Keyed with the app key: 31⁸ codes are few enough to try all against a
     * plain sha256 from a leaked backup.
     */
    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    private function deleteExpiredCodes(): void
    {
        DB::table('guardian_codes')->where('expires_at', '<=', now())->delete();
    }
}
