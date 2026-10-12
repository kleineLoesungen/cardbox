<?php

use App\Services\DeployService;
use Illuminate\Support\Facades\DB;

/** A token long enough to switch the hook on. */
const HOOK_TOKEN = 'test-token-0123456789abcdef0123456789abcdef';

it('gibt es nicht, solange kein Token eingerichtet ist', function () {
    config(['deploy.hook_token' => null]);

    $this->withToken(HOOK_TOKEN)->postJson('/api/v1/deploy')->assertNotFound();
});

it('gilt bei einem zu kurzen Token als ausgeschaltet', function () {
    config(['deploy.hook_token' => 'kurz']);

    $this->withToken('kurz')->postJson('/api/v1/deploy')->assertNotFound();
});

it('antwortet ohne oder mit falschem Token mit 404', function () {
    config(['deploy.hook_token' => HOOK_TOKEN]);

    // deploy.sh tells a refused token from a wrong address by this code.
    $this->postJson('/api/v1/deploy')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    $this->withToken(HOOK_TOKEN.'x')->postJson('/api/v1/deploy')->assertNotFound();
    $this->getJson('/api/v1/deploy', ['Authorization' => 'Bearer '.HOOK_TOKEN])->assertStatus(405);
});

it('nimmt das Token auch im Header X-Deploy-Token an', function () {
    config(['deploy.hook_token' => HOOK_TOKEN, 'app.debug' => false]);

    $this->postJson('/api/v1/deploy', [], ['X-Deploy-Token' => HOOK_TOKEN.'x'])->assertNotFound();
    $this->postJson('/api/v1/deploy', [], ['X-Deploy-Token' => HOOK_TOKEN])
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('migriert mit dem richtigen Token und meldet die Prüfungen', function () {
    config(['deploy.hook_token' => HOOK_TOKEN, 'app.debug' => false]);

    $this->withToken(HOOK_TOKEN)->postJson('/api/v1/deploy')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('checks.php.ok', true)
        ->assertJsonPath('checks.ext-pdo_pgsql.ok', true)
        ->assertJsonPath('checks.database.ok', true)
        ->assertJsonPath('checks.argon2.ok', true)
        ->assertJsonPath('checks.app-key.ok', true);
});

it('migriert nicht, wenn eine Prüfung fehlschlägt', function () {
    config(['deploy.hook_token' => HOOK_TOKEN, 'app.debug' => true]);

    $this->withToken(HOOK_TOKEN)->postJson('/api/v1/deploy')
        ->assertStatus(500)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('checks.app-debug.ok', false)
        ->assertJsonPath('migrations', 'nicht ausgeführt – erst die Prüfungen beheben');
});

it('ist gegen Durchprobieren gedrosselt', function () {
    config(['deploy.hook_token' => HOOK_TOKEN]);

    foreach (range(1, 10) as $_) {
        $this->postJson('/api/v1/deploy');
    }

    $this->postJson('/api/v1/deploy')->assertStatus(429);
});

it('lässt keinen zweiten Deploy gleichzeitig laufen', function () {
    config(['deploy.hook_token' => HOOK_TOKEN, 'app.debug' => false]);

    // Another connection holds the lock, like a deploy that is still running.
    $holder = new PDO(
        'pgsql:host='.config('database.connections.pgsql.host').';port='.config('database.connections.pgsql.port').';dbname='.config('database.connections.pgsql.database'),
        config('database.connections.pgsql.username'),
        config('database.connections.pgsql.password'),
    );
    $holder->query('SELECT pg_advisory_lock('.DeployService::LOCK_KEY.')');

    try {
        $this->withToken(HOOK_TOKEN)->postJson('/api/v1/deploy')
            ->assertStatus(500)
            ->assertJsonPath('migrations', 'Ein anderer Deploy läuft gerade. Kurz warten und erneut versuchen.');
    } finally {
        $holder->query('SELECT pg_advisory_unlock('.DeployService::LOCK_KEY.')');
    }
});

describe('DB_SCHEMA', function () {
    it('nutzt ohne Angabe das Schema public', function () {
        expect(config('database.connections.pgsql.search_path'))->toBe('public')
            ->and(app(DeployService::class)->ensureSchema())->toBeNull();
    });

    it('legt ein fehlendes Schema an', function () {
        config(['database.connections.pgsql.search_path' => 'cardbox_schema_test']);

        expect(app(DeployService::class)->ensureSchema())->toBeNull()
            ->and(DB::table('information_schema.schemata')
                ->where('schema_name', 'cardbox_schema_test')->exists())->toBeTrue();
    });

    it('lehnt ungültige Schemanamen ab und migriert dann nicht', function (string $schema) {
        config(['deploy.hook_token' => HOOK_TOKEN, 'app.debug' => false, 'database.connections.pgsql.search_path' => $schema]);

        $this->withToken(HOOK_TOKEN)->postJson('/api/v1/deploy')
            ->assertStatus(500)
            ->assertJsonPath('checks.database-schema.ok', false)
            ->assertJsonPath('migrations', 'nicht ausgeführt – erst die Prüfungen beheben');
    })->with(['x"; DROP SCHEMA public; --', '1abc', 'mit leerzeichen', str_repeat('a', 64)]);
});
