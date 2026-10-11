<?php

namespace App\Services;

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The card box (Leitner system). See docs/specs/vokabelkarten.md,
 * "Karteikasten (Leitner)":
 *
 * * Boxes 0 (new) … N (mastered, "Kann ich"). Box k (1 ≤ k < N) has an
 *   interval A_k in days. Default: N = 5, A = 1, 2, 4, 7.
 * * Known: one box up. Landing in box k < N → due in A_k days. Reaching N
 *   → mastered, never due again.
 * * Not known: back to box 1, due in A_1 days. The round repeats it right
 *   away (StudyRoundService), without touching the progress again.
 * * "Today" is the calendar day in the learning time zone, not UTC.
 * * A smaller N later simply makes boxes ≥ N count as mastered; nothing is
 *   rewritten.
 */
class LeitnerService
{
    /**
     * Plans already loaded in this request. The service is bound as scoped
     * (AppServiceProvider), so this never outlives a request.
     *
     * @var array<string, array{box_count: int, day_gaps: list<int>}>
     */
    private array $plans = [];

    /**
     * The person's plan, or the default plan.
     *
     * @return array{box_count: int, day_gaps: list<int>}
     */
    public function plan(User $user): array
    {
        return $this->plans[$user->id] ??= $this->loadPlan($user);
    }

    /** Drops the remembered plan, e.g. right after it was changed. */
    public function forgetPlan(User $user): void
    {
        unset($this->plans[$user->id]);
    }

    /** @return array{box_count: int, day_gaps: list<int>} */
    private function loadPlan(User $user): array
    {
        $row = DB::table('learning_settings')->where('user_id', $user->id)->first(['box_count', 'day_gaps']);

        if ($row === null) {
            return [
                'box_count' => (int) config('learning.default_box_count'),
                'day_gaps' => array_values(array_map('intval', (array) config('learning.default_day_gaps'))),
            ];
        }

        $gaps = array_values(array_map('intval', (array) json_decode((string) $row->day_gaps, true)));

        return ['box_count' => (int) $row->box_count, 'day_gaps' => $gaps];
    }

    /** Today's date in the learning time zone. */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('learning.timezone'))->startOfDay();
    }

    /**
     * Records one answer and moves the card between boxes.
     *
     * @return array{from: int, to: int}
     */
    public function review(User $user, Card $card, Direction $direction, bool $known): array
    {
        $plan = $this->plan($user);
        $boxCount = $plan['box_count'];
        $today = $this->today();

        return DB::transaction(function () use ($user, $card, $direction, $known, $plan, $boxCount, $today) {
            // Make sure the row exists before locking it: FOR UPDATE cannot
            // lock a row that is not there, and two first answers at the same
            // time would otherwise collide on the unique index.
            DB::statement(
                'INSERT INTO card_progress (id, user_id, card_id, direction, box, review_count, correct_count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, now(), now())
                 ON CONFLICT (user_id, card_id, direction) DO NOTHING',
                [(string) Str::uuid(), $user->id, $card->id, $direction->value],
            );

            $progress = CardProgress::query()
                ->where('user_id', $user->id)
                ->where('card_id', $card->id)
                ->where('direction', $direction->value)
                ->lockForUpdate()
                ->firstOrFail();

            $from = min($progress->box, $boxCount);
            $to = $known ? min($from + 1, $boxCount) : 1;

            $progress->fill([
                'box' => $to,
                'due_on' => $to >= $boxCount ? null : $today->addDays($this->gap($plan['day_gaps'], $to))->toDateString(),
                'last_reviewed_at' => now(),
                'review_count' => $progress->review_count + 1,
                'correct_count' => $progress->correct_count + ($known ? 1 : 0),
            ])->save();

            $this->recordStudyDay($user, $known, $today);

            return ['from' => $from, 'to' => $to];
        });
    }

    /**
     * Days of learning in a row, counting back from today — or from
     * yesterday, so the streak survives until the person learns today.
     */
    public function streak(User $user): int
    {
        $today = $this->today();
        $days = DB::table('study_days')
            ->where('user_id', $user->id)
            ->where('day', '<=', $today->toDateString())
            ->orderByDesc('day')
            // Streaks are shown up to 400 days; enough for a school year and more.
            ->limit(400)
            ->pluck('day')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->all();

        // Start today if learned today, otherwise yesterday; then walk back
        // as long as the days are consecutive.
        $expected = in_array($today->toDateString(), $days, true) ? $today : $today->subDay();
        $streak = 0;
        foreach ($days as $day) {
            if ($day !== $expected->toDateString()) {
                break;
            }
            $streak++;
            $expected = $expected->subDay();
        }

        return $streak;
    }

    /**
     * Interval for landing in box `$box` (1-based); the last one repeats if
     * the list is short.
     *
     * @param  list<int>  $gaps
     */
    private function gap(array $gaps, int $box): int
    {
        return max(1, (int) ($gaps[$box - 1] ?? end($gaps) ?: 1));
    }

    private function recordStudyDay(User $user, bool $known, CarbonImmutable $today): void
    {
        DB::statement(
            'INSERT INTO study_days (user_id, day, reviews, correct) VALUES (?, ?, 1, ?)
             ON CONFLICT (user_id, day) DO UPDATE SET reviews = study_days.reviews + 1, correct = study_days.correct + EXCLUDED.correct',
            [$user->id, $today->toDateString(), $known ? 1 : 0],
        );
    }
}
