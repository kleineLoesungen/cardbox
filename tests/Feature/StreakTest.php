<?php

use App\Models\User;
use App\Services\LeitnerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * The streak with a missed day (issue #27): three times the daily goal on
 * the day after bridges a single missed day, which itself does not count.
 * Daily goal 20 (the default).
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

it('überbrückt einen fehlenden Tag mit dem dreifachen Tagesziel am Tag danach; der Tag selbst zählt nicht', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-10' => 20, '2026-10-11' => 20, '2026-10-13' => 60]);

    expect(streakStatus($ich))->toBe(['days' => 3, 'rescue' => null]);
});

it('überbrückt nicht mit doppeltem Tagesziel und nicht zwei fehlende Tage', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-11' => 20, '2026-10-13' => 59]);
    expect(streakStatus($ich)['days'])->toBe(1);

    $du = User::factory()->create();
    streakDays($du, ['2026-10-10' => 20, '2026-10-13' => 90]);
    expect(streakStatus($du)['days'])->toBe(1);
});

it('sagt nach einem fehlenden Tag, was heute die Lernfolge rettet – auch nach dem ersten Tagesziel', function () {
    $ich = actingAsUser();
    streakDays($ich, ['2026-10-11' => 20, '2026-10-12' => 25]);

    // Yesterday missed, nothing learned yet today.
    expect(streakStatus($ich))->toBe(['days' => 0, 'rescue' => ['days' => 3, 'target' => 60, 'answers' => 0]]);
    $this->get('/learn')->assertSee('Gestern hast du ausgesetzt. Schaffst du heute dein Tagesziel dreimal (60 Karten), geht deine Lernfolge weiter: 3 Tage.');

    // The goal once: how many are still missing.
    streakDays($ich, ['2026-10-14' => 20]);
    $this->get('/learn')->assertSee('Tagesziel geschafft! Noch 40 Karten heute, dann geht deine Lernfolge weiter: 3 Tage.');

    // Three times: bridged.
    DB::table('study_days')->where('user_id', $ich->id)->where('day', '2026-10-14')->update(['answers' => 60]);
    expect(streakStatus($ich))->toBe(['days' => 3, 'rescue' => null]);
    $this->get('/learn')->assertSee('3 Tage in Folge gelernt')->assertDontSee('ausgesetzt');
});

it('überbrückt auch zwei Lücken hintereinander, wenn jedes Mal das Dreifache geschafft ist', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-09' => 20, '2026-10-11' => 60, '2026-10-13' => 60]);

    expect(streakStatus($ich)['days'])->toBe(3);
});

it('nennt nach dem ersten Tagesziel die Karten, die zur Rettung noch fehlen', function () {
    $html = view('learn.partials.streak-rescue', ['rescue' => ['days' => 3, 'target' => 60, 'answers' => 25], 'goal' => 20])->render();

    expect(trim($html))->toBe('Tagesziel geschafft! Noch 35 Karten heute, dann geht deine Lernfolge weiter: 3 Tage.');
});

it('bietet keine Rettung an, wenn schon vorgestern nichts war', function () {
    $ich = User::factory()->create();
    streakDays($ich, ['2026-10-11' => 20]);

    expect(streakStatus($ich))->toBe(['days' => 0, 'rescue' => null]);
});
