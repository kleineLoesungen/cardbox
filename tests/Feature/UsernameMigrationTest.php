<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Runs the email → username migration against legacy rows: down() restores
 * the old shape, then up() must derive unique usernames. Schema changes are
 * transactional in Postgres, so RefreshDatabase rolls all of this back.
 */
it('bildet für bestehende Konten eindeutige Benutzernamen aus der E-Mail', function () {
    $migration = require database_path('migrations/2026_10_11_090000_replace_email_with_username_on_users_table.php');
    $migration->down();

    $rows = [
        'Anna@A.de', 'anna@b.de', 'jörg.groß@example.com', '42@example.com', 'x@example.com',
    ];
    foreach ($rows as $i => $email) {
        DB::table('users')->insert([
            'id' => (string) Str::uuid(),
            'email' => $email,
            'name' => "Person {$i}",
            'password' => 'x',
            'role' => 'user',
            'is_active' => true,
            'created_at' => now()->addSeconds($i),
            'updated_at' => now(),
        ]);
    }

    $migration->up();

    expect(DB::table('users')->orderBy('created_at')->pluck('username')->all())
        ->toBe(['anna', 'anna2', 'joerg.gross', 'user', 'user2'])
        ->and(DB::table('users')->whereNull('approved_at')->count())->toBe(0);
});
