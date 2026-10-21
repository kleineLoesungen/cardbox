<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * The app writes times without offset and means UTC; a server in German time
 * read them two hours off (duel times all at the one-minute limit).
 */

it('spricht mit der Datenbank immer in UTC, egal wie der Server eingestellt ist', function () {
    expect(config('database.connections.pgsql.timezone'))->toBe('UTC');

    // A second connection that starts in German time, like such a server.
    config(['database.connections.zone_check' => config('database.connections.pgsql')]);
    DB::connection('zone_check')->statement("set time zone 'Europe/Berlin'");
    DB::purge('zone_check');

    expect(DB::connection('zone_check')->selectOne('show timezone')->TimeZone)->toBe('UTC')
        ->and(DB::selectOne('show timezone')->TimeZone)->toBe('UTC');
    DB::purge('zone_check');
});

it('rückt Zeiten zurecht, die auf einem Server in deutscher Zeit zwei Stunden zu früh gespeichert wurden', function () {
    $migration = require database_path('migrations/2026_10_21_000000_repair_times_written_in_server_zone.php');
    $user = User::factory()->create();
    // As stored before: 18:00 written by the app, taken as German summer time.
    DB::table('users')->where('id', $user->id)->update(['created_at' => '2026-10-20 16:00:00+00']);
    // Set by the database itself: right already.
    DB::table('login_attempts')->insert(['attempt_key' => 'probe', 'failures' => 3, 'locked_until' => '2026-10-20 16:00:00+00', 'last_attempt_at' => '2026-10-20 16:00:00+00']);
    $times = fn () => [
        DB::selectOne('select created_at::text as t from users where id = ?', [$user->id])->t,
        DB::selectOne("select locked_until::text || ' ' || last_attempt_at::text as t from login_attempts where attempt_key = 'probe'")->t,
    ];

    $migration->repair('Europe/Berlin');
    expect($times())->toBe(['2026-10-20 18:00:00+00', '2026-10-20 16:00:00+00 2026-10-20 16:00:00+00'])
        ->and(DB::table('settings')->where('key', 'times_repaired_from_zone')->value('value'))->toBe('Europe/Berlin');

    // Once only, and back exactly.
    $migration->up();
    expect($times()[0])->toBe('2026-10-20 18:00:00+00');
    $migration->down();
    expect($times()[0])->toBe('2026-10-20 16:00:00+00');
});
