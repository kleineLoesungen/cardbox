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
    /** Times the daily goal that bridges a single missed day (issue #27). */
    public const RESCUE_FACTOR = 3;

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

    /** Cards per round, chosen under "Mein Konto" (runden-und-knifflige-woerter.md). */
    public function roundSize(User $user): int
    {
        $size = DB::table('learning_settings')->where('user_id', $user->id)->value('round_size');

        return $size === null ? (int) config('learning.round_size') : (int) $size;
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
     * Counts one card known today, from any kind of round, for the daily
     * goal (the caller decides what counts); reaching the goal makes the day
     * count for the streak.
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
     * The last `$days` days up to today, oldest first, each with whether the
     * daily goal was met and how many times over ("Heute": dots for the
     * week). `times` is answers ÷ today's goal, rounded down, at least 1 on
     * a day that counts (also one finished with fewer cards left); 0 otherwise.
     *
     * @return list<array{date: CarbonImmutable, today: bool, met: bool, times: int}>
     */
    public function recentDays(User $user, int $days = 7): array
    {
        $today = $this->today();
        $start = $today->subDays($days - 1);
        $goal = max(1, $this->dailyGoal($user));
        $met = DB::table('study_days')
            ->where('user_id', $user->id)
            ->where('goal_met', true)
            ->whereBetween('day', [$start->toDateString(), $today->toDateString()])
            ->get(['day', 'answers'])
            ->mapWithKeys(fn ($row) => [substr((string) $row->day, 0, 10) => max(1, intdiv((int) $row->answers, $goal))])
            ->all();

        $result = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->addDays($i);
            $times = $met[$date->toDateString()] ?? 0;
            $result[] = ['date' => $date, 'today' => $i === $days - 1, 'met' => $times > 0, 'times' => $times];
        }

        return $result;
    }

    /**
     * Days in a row with the daily goal met, counting back from today — or
     * from yesterday, so the streak survives until the person learns today.
     */
    public function streak(User $user): int
    {
        return $this->streakStatus($user)['days'];
    }

    /**
     * The streak (issue #27): days in a row with the daily goal met. A single
     * missed day does not end it when the day after reaches RESCUE_FACTOR
     * times the goal; the missed day itself does not count. Two missed days
     * in a row end it.
     *
     * `rescue`: yesterday was missed and today can still bridge it — `days`
     * is the streak once today reaches `target` answers, `answers` what
     * today has so far. Null otherwise.
     *
     * @return array{days: int, rescue: array{days: int, target: int, answers: int}|null}
     */
    public function streakStatus(User $user): array
    {
        $today = $this->today();
        $goal = max(1, $this->dailyGoal($user));
        $rows = DB::table('study_days')
            ->where('user_id', $user->id)
            ->where('day', '<=', $today->toDateString())
            // Streaks are shown up to 400 days; enough for a school year and more.
            ->where('day', '>=', $today->subDays(400)->toDateString())
            ->get(['day', 'answers', 'goal_met']);
        $met = [];
        $answers = [];
        foreach ($rows as $row) {
            $day = substr((string) $row->day, 0, 10);
            $answers[$day] = (int) $row->answers;
            if ($row->goal_met) {
                $met[$day] = true;
            }
        }
        $isMet = fn (CarbonImmutable $d) => isset($met[$d->toDateString()]);
        $bridges = fn (CarbonImmutable $d) => $isMet($d) && ($answers[$d->toDateString()] ?? 0) >= self::RESCUE_FACTOR * $goal;

        // Walks back from $day as long as the days count, bridging single gaps.
        $walk = function (CarbonImmutable $day) use ($isMet, $bridges): int {
            $days = 0;
            while ($days < 400 && $isMet($day)) {
                $days++;
                $before = $day->subDay();
                // A single missed day is stepped over, not counted.
                $day = ! $isMet($before) && $isMet($before->subDay()) && $bridges($day) ? $before->subDay() : $before;
            }

            return $days;
        };

        $yesterday = $today->subDay();
        $days = $walk($isMet($today) ? $today : $yesterday);

        // Yesterday missed, the day before counted, today not bridged yet.
        $rescue = null;
        if (! $isMet($yesterday) && $isMet($yesterday->subDay()) && ! $bridges($today)) {
            $rescue = [
                'days' => $walk($yesterday->subDay()) + 1,
                'target' => self::RESCUE_FACTOR * $goal,
                'answers' => $answers[$today->toDateString()] ?? 0,
            ];
        }

        if ($rescue !== null) {
            $rescue['days'] = min(400, $rescue['days']);
        }

        return ['days' => min(400, $days), 'rescue' => $rescue];
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
