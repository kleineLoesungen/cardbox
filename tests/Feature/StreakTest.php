<?php

use App\Models\User;
use App\Services\LeitnerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * The streak with a missed day (issue #27): twice the daily goal on the day
 * after bridges a single missed day. Daily goal 20 (the default).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** Study days: date => answers; 20 or more meet the goal. */
function streakDays(User $user, array $days): void
{
    foreach ($days as $day => $answers) {
        DB::table('study_days')->insert(['user_id' => $user->id, 'day' => $day, 'reviews' => $answers, 'correct' => $answers, 'answers' => $answers, 'goal_met' => $answers >= 20]);
    }
}

function streakStatus(User $user): array
{
    return app(LeitnerService::class)->streakStatus($user);
}

it('überbrückt einen fehlenden Tag mit dem doppelten Tagesziel am Tag danach; der Tag zählt mit', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-10' => 20, '2026-10-11' => 20, '2026-10-13' => 40]);

    expect(streakStatus($ich))->toBe(['days' => 4, 'rescue' => null]);
});

it('überbrückt nicht mit einfachem Tagesziel und nicht zwei fehlende Tage', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-11' => 20, '2026-10-13' => 39]);
    expect(streakStatus($ich)['days'])->toBe(1);

    $du = User::factory()->create();
    streakDays($du, ['2026-10-10' => 20, '2026-10-13' => 60]);
    expect(streakStatus($du)['days'])->toBe(1);
});

it('sagt nach einem fehlenden Tag, was heute die Lernfolge rettet – auch nach dem ersten Tagesziel', function () {
    $ich = actingAsUser();
    streakDays($ich, ['2026-10-11' => 20, '2026-10-12' => 25]);

    // Yesterday missed, nothing learned yet today.
    expect(streakStatus($ich))->toBe(['days' => 0, 'rescue' => ['days' => 4, 'target' => 40, 'answers' => 0]]);
    $this->get('/learn')->assertSee('Gestern hast du ausgesetzt. Schaffst du heute dein Tagesziel zweimal (40 Karten), geht deine Lernfolge weiter: 4 Tage.');

    // The goal once: "noch einmal".
    streakDays($ich, ['2026-10-14' => 20]);
    $this->get('/learn')->assertSee('Schaffst du heute noch einmal dein Tagesziel – noch 20 Karten –, geht deine Lernfolge weiter: 4 Tage.');

    // Twice: bridged.
    DB::table('study_days')->where('user_id', $ich->id)->where('day', '2026-10-14')->update(['answers' => 40]);
    expect(streakStatus($ich))->toBe(['days' => 4, 'rescue' => null]);
    $this->get('/learn')->assertSee('4 Tage in Folge gelernt')->assertDontSee('ausgesetzt');
});

it('überbrückt auch zwei Lücken hintereinander, wenn jedes Mal das Doppelte geschafft ist', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-09' => 20, '2026-10-11' => 40, '2026-10-13' => 40]);

    expect(streakStatus($ich)['days'])->toBe(5);
});

it('bietet keine Rettung an, wenn schon vorgestern nichts war', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-11' => 20]);

    expect(streakStatus($ich))->toBe(['days' => 0, 'rescue' => null]);
});
