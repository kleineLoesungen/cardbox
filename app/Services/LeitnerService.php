<?php

namespace App\Services;

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\DeckRun;
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
 * * The plan (N and the intervals) belongs to the deck run and is fixed
 *   when the run starts (docs/specs/lernplaene-und-karten.md). The person's
 *   own plan is only the standard for new runs.
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
     * The person's standard plan for new runs, or the default plan.
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
     * Records one answer and moves the card between boxes — in every given
     * run, each by its own plan. "Los geht's" passes all runs in which the
     * card is due; the deck page passes one. The answer counts once for the
     * streak, however many runs it moves.
     *
     * @param  list<DeckRun>  $runs  the person's runs of the card's deck
     * @return array{from: int, to: int} the move in the first run
     */
    public function review(User $user, array $runs, Card $card, Direction $direction, bool $known): array
    {
        $today = $this->today();

        return DB::transaction(function () use ($user, $runs, $card, $direction, $known, $today) {
            $first = null;
            foreach ($runs as $run) {
                $move = $this->reviewInRun($user, $run, $card, $direction, $known, $today);
                $first ??= $move;
            }

            $this->recordStudyDay($user, $known, $today);

            return $first ?? ['from' => 0, 'to' => 0];
        });
    }

    /** @return array{from: int, to: int} */
    private function reviewInRun(User $user, DeckRun $run, Card $card, Direction $direction, bool $known, CarbonImmutable $today): array
    {
        $boxCount = $run->box_count;

        // Make sure the row exists before locking it: FOR UPDATE cannot lock
        // a row that is not there, and two first answers at the same time
        // would otherwise collide on the unique index.
        DB::statement(
            'INSERT INTO card_progress (id, user_id, deck_run_id, card_id, direction, box, review_count, correct_count, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 0, 0, 0, ?, ?)
             ON CONFLICT (deck_run_id, card_id, direction) DO NOTHING',
            // The application clock, not the database's: created_at says when a card was started ("new_per_day").
            [(string) Str::uuid(), $user->id, $run->id, $card->id, $direction->value, now(), now()],
        );

        $progress = CardProgress::query()
            ->where('deck_run_id', $run->id)
            ->where('card_id', $card->id)
            ->where('direction', $direction->value)
            ->lockForUpdate()
            ->firstOrFail();

        $from = min($progress->box, $boxCount);
        $to = $known ? min($from + 1, $boxCount) : 1;

        $progress->fill([
            'box' => $to,
            'due_on' => $to >= $boxCount ? null : $today->addDays($this->gap($run->day_gaps, $to))->toDateString(),
            'last_reviewed_at' => now(),
            'review_count' => $progress->review_count + 1,
            'correct_count' => $progress->correct_count + ($known ? 1 : 0),
        ])->save();

        return ['from' => $from, 'to' => $to];
    }

    /**
     * The person's daily goal: answers a day (docs/specs/motivierender-start.md, B).
     */
    public function dailyGoal(User $user): int
    {
        $goal = DB::table('learning_settings')->where('user_id', $user->id)->value('daily_goal');

        return $goal === null ? (int) config('learning.default_daily_goal') : (int) $goal;
    }

    /**
     * Counts one answer of any round (learning, repeats, practice, test)
     * for today's goal; reaching the goal makes the day count for the streak.
     */
    public function countAnswer(User $user): void
    {
        $goal = $this->dailyGoal($user);
        DB::statement(
            'INSERT INTO study_days (user_id, day, reviews, correct, answers, goal_met) VALUES (?, ?, 0, 0, 1, ? <= 1)
             ON CONFLICT (user_id, day) DO UPDATE SET answers = study_days.answers + 1,
                 goal_met = study_days.goal_met OR study_days.answers + 1 >= ?',
            [$user->id, $this->today()->toDateString(), $goal, $goal],
        );
    }

    /**
     * After the goal was changed: a goal now reached counts for today at once.
     */
    public function refreshGoalMet(User $user): void
    {
        DB::table('study_days')
            ->where('user_id', $user->id)
            ->where('day', $this->today()->toDateString())
            ->where('answers', '>=', $this->dailyGoal($user))
            ->update(['goal_met' => true]);
    }

    /**
     * Today counts for the streak although the goal is not reached: "Heute"
     * had nothing more to offer. Only after a round, so a day without any
     * learning never counts.
     */
    public function markGoalMet(User $user): void
    {
        DB::table('study_days')
            ->where('user_id', $user->id)
            ->where('day', $this->today()->toDateString())
            ->update(['goal_met' => true]);
    }

    /**
     * Today's answers against the goal.
     *
     * @return array{answers: int, goal: int, goal_met: bool}
     */
    public function dayStatus(User $user): array
    {
        $row = DB::table('study_days')
            ->where('user_id', $user->id)
            ->where('day', $this->today()->toDateString())
            ->first(['answers', 'goal_met']);

        return [
            'answers' => (int) ($row->answers ?? 0),
            'goal' => $this->dailyGoal($user),
            'goal_met' => (bool) ($row->goal_met ?? false),
        ];
    }

    /**
     * Days in a row with the daily goal met, counting back from today — or
     * from yesterday, so the streak survives until the person learns today.
     */
    public function streak(User $user): int
    {
        $today = $this->today();
        $days = DB::table('study_days')
            ->where('user_id', $user->id)
            ->where('goal_met', true)
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
