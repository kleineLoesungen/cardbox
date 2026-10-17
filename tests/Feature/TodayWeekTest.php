<?php

use App\Models\Deck;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * "Heute": the daily goal of the last 7 days as dots.
 */

it('zeigt die letzten 7 Tage als Punkte, grün mit erreichtem Tagesziel', function () {
    // 14.10. 00:30 in Berlin, still the 13th in UTC.
    $this->travelTo(Carbon::parse('2026-10-13 22:30:00', 'UTC'));
    $ich = actingAsUser();
    Deck::factory()->for($ich)->create();
    DB::table('study_days')->insert([
        ['user_id' => $ich->id, 'day' => '2026-10-13', 'reviews' => 20, 'correct' => 20, 'answers' => 20, 'goal_met' => true],
        ['user_id' => $ich->id, 'day' => '2026-10-12', 'reviews' => 5, 'correct' => 3, 'answers' => 3, 'goal_met' => false],
        ['user_id' => $ich->id, 'day' => '2026-10-08', 'reviews' => 20, 'correct' => 20, 'answers' => 20, 'goal_met' => true],
        // Eight days ago: no longer shown.
        ['user_id' => $ich->id, 'day' => '2026-10-07', 'reviews' => 20, 'correct' => 20, 'answers' => 20, 'goal_met' => true],
    ]);
    // Another account's day does not count.
    DB::table('study_days')->insert(['user_id' => User::factory()->create()->id, 'day' => '2026-10-14', 'reviews' => 20, 'correct' => 20, 'answers' => 20, 'goal_met' => true]);

    $html = $this->get('/learn')
        ->assertSeeInOrder([
            'Do 8.10.: Ziel erreicht',
            'Fr 9.10.: Ziel nicht erreicht',
            'Mo 12.10.: Ziel nicht erreicht',
            'Di 13.10.: Ziel erreicht',
            'Mi 14.10.: Ziel noch offen',
        ])
        ->assertDontSee('7.10.:')
        ->getContent();

    expect(substr_count($html, 'class="week-dots-day"'))->toBe(7)
        ->and(substr_count($html, 'week-dot-met'))->toBe(2)
        ->and(substr_count($html, 'week-dot-today'))->toBe(1);
});

it('färbt heute grün, sobald das Tagesziel erreicht ist', function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
    $ich = actingAsUser();
    Deck::factory()->for($ich)->create();
    DB::table('study_days')->insert(['user_id' => $ich->id, 'day' => '2026-10-14', 'reviews' => 20, 'correct' => 20, 'answers' => 20, 'goal_met' => true]);

    $this->get('/learn')->assertSee('Mi 14.10.: Ziel erreicht')->assertSee('week-dot week-dot-met week-dot-today', false);
});

it('zeigt ohne Stapel keine Punkte', function () {
    actingAsUser();

    $this->get('/learn')->assertDontSee('week-dots');
});
