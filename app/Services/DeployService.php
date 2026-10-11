<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What deploy.sh triggers after an FTP upload: check the server, then run
 * the migrations. On web space there is no shell, so this is also the only
 * place where an operator sees whether PHP is set up correctly.
 */
class DeployService
{
    /** Same minimum as composer.json; the dependencies need it. */
    public const MIN_PHP = '8.4.1';

    /** Key of the PostgreSQL advisory lock that serialises deploy hooks. */
    public const LOCK_KEY = 4_242_001;

    /** @var list<string> */
    public const EXTENSIONS = ['pdo_pgsql', 'intl', 'mbstring', 'xml', 'curl'];

    /**
     * @return array{ok: bool, checks: array<string, array{ok: bool, detail: string}>, migrations: string}
     */
    public function run(): array
    {
        $checks = $this->checks();
        $ready = ! in_array(false, array_column($checks, 'ok'), true);

        if (! $ready) {
            return ['ok' => false, 'checks' => $checks, 'migrations' => 'nicht ausgeführt – erst die Prüfungen beheben'];
        }

        // A PostgreSQL advisory lock instead of the cache lock: it works on an
        // empty database too (cache_locks is created by the migrations).
        $locked = DB::selectOne('SELECT pg_try_advisory_lock(?) AS ok', [self::LOCK_KEY]);
        if (! ($locked->ok ?? false)) {
            return ['ok' => false, 'checks' => $checks, 'migrations' => 'Ein anderer Deploy läuft gerade. Kurz warten und erneut versuchen.'];
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
            $migrations = trim(Artisan::output());
            Artisan::call('login-attempts:prune');
            // Compiled views are rebuilt on demand from the new templates.
            Artisan::call('view:clear');
        } catch (Throwable $e) {
            return ['ok' => false, 'checks' => $checks, 'migrations' => 'Fehler: '.$e->getMessage()];
        } finally {
            DB::select('SELECT pg_advisory_unlock(?)', [self::LOCK_KEY]);
        }

        return ['ok' => true, 'checks' => $checks, 'migrations' => $migrations];
    }

    /**
     * @return array<string, array{ok: bool, detail: string}>
     */
    public function checks(): array
    {
        $checks = [
            'php' => [
                'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
                'detail' => PHP_VERSION.' (nötig: '.self::MIN_PHP.' oder neuer)',
            ],
            'argon2' => [
                'ok' => defined('PASSWORD_ARGON2ID'),
                'detail' => defined('PASSWORD_ARGON2ID') ? 'vorhanden' : 'fehlt – PHP ohne libargon2',
            ],
        ];

        foreach (self::EXTENSIONS as $extension) {
            $checks['ext-'.$extension] = [
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? 'geladen' : 'fehlt',
            ];
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $name => $path) {
            $checks['writable-'.$name] = [
                'ok' => is_writable($path),
                'detail' => is_writable($path) ? 'beschreibbar' : 'nicht beschreibbar',
            ];
        }

        $key = config('app.key');
        $checks['app-key'] = [
            'ok' => is_string($key) && str_starts_with($key, 'base64:'),
            'detail' => is_string($key) && $key !== '' ? 'gesetzt' : 'APP_KEY fehlt in .env',
        ];

        $checks['app-debug'] = [
            'ok' => config('app.debug') === false,
            'detail' => config('app.debug') ? 'APP_DEBUG=true – auf false setzen' : 'aus',
        ];

        try {
            DB::select('SELECT 1');
            $checks['database'] = ['ok' => true, 'detail' => 'erreichbar'];
        } catch (Throwable $e) {
            $checks['database'] = ['ok' => false, 'detail' => 'nicht erreichbar: '.$e->getMessage()];
        }

        return $checks;
    }
}
