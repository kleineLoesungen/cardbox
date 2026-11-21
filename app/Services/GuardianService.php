<?php

namespace App\Services;

use App\Models\Deck;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Parents see the progress (docs/specs/eltern-ansicht.md) and look after
 * their children's decks (docs/specs/eltern-konten.md).
 *
 * An admin assigns parents (UserService); the child cannot end the link.
 * This is the only place where one account reads another account's learning
 * data, so every read goes through getChild() first. Changing a child's
 * decks needs a parent of 18 or older (isGuardianOf).
 *
 * @phpstan-import-type Progress from LearningOverviewService
 */
class GuardianService
{
    public const MAX_GUARDIANS = 4;

    public function __construct(
        private LearningOverviewService $overview,
        private LeitnerService $leitner,
    ) {}

    /**
     * Who sees the child's progress.
     *
     * @return Collection<int, User>
     */
    public function guardiansOf(User $child): Collection
    {
        // link_confirmed: assigned or confirmed by an admin; otherwise a link
        // from the time of codes, which shows the overview only.
        return User::query()
            ->join('guardian_links', 'guardian_links.guardian_id', '=', 'users.id')
            ->where('guardian_links.child_id', $child->id)
            ->select('users.*')
            ->selectRaw('guardian_links.confirmed_at is not null as link_confirmed')
            ->orderBy('users.name')
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

    /** How many children the guardian sees. */
    public function childCount(User $guardian): int
    {
        return DB::table('guardian_links')->where('guardian_id', $guardian->id)->count();
    }

    /** For the menu: one cheap query. */
    public function hasChildren(User $guardian): bool
    {
        return DB::table('guardian_links')->where('guardian_id', $guardian->id)->exists();
    }

    /**
     * Whether `$guardian` is a parent of `$child` who may act for them
     * (docs/specs/eltern-konten.md).
     */
    public function isGuardianOf(User $guardian, User $child): bool
    {
        return self::managedChildIds($guardian)->where('child_id', $child->id)->exists();
    }

    /**
     * Ids of the children a parent may act for: the parent is 18 or older,
     * the child under 18, and an admin assigned or confirmed the link.
     * Links from the time of codes (confirmed_at null) show the overview
     * only — the child agreed to being watched, not to edits.
     */
    public static function managedChildIds(User $guardian): QueryBuilder
    {
        $ids = DB::table('guardian_links')
            ->where('guardian_id', $guardian->id)
            ->whereNotNull('confirmed_at')
            ->whereIn('child_id', DB::table('users')->where('is_adult', false)->select('id'))
            ->select('child_id');

        return $guardian->is_adult ? $ids : $ids->whereRaw('false');
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
     *     progress: array<string, Progress>,
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

    private function ensureUuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(User::class, [$id]);
        }
    }
}
