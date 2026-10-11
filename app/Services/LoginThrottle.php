<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bremse gegen das Durchprobieren von Passwoertern.
 *
 * Drei Versuche sind frei — Vertipper sollen niemanden bestrafen. Nach dem
 * dritten Fehlversuch wird gesperrt, und die Sperre waechst mit jedem weiteren:
 *
 * | Fehlversuche bisher | naechster Versuch moeglich in |
 * |---|---|
 * | 1–2 | sofort |
 * | 3 | 5 Sekunden |
 * | 4 | 15 Sekunden |
 * | 5 | 45 Sekunden |
 * | 6 | 2 Minuten 15 |
 * | 7 | 6 Minuten 45 |
 * | 8 und mehr | 15 Minuten (Obergrenze) |
 *
 * **Warum eine Sperre und kein echtes Warten?** Die Antwort einfach zu
 * verzoegern haelt pro Versuch einen PHP-Worker fest — ein Angreifer koennte
 * den Server damit gezielt zustellen. Die Sperre antwortet sofort und sagt,
 * wie lange es noch dauert. Fuer den Angreifer ist die Bremse dieselbe, fuer
 * den Server kostet sie nichts.
 *
 * **Warum nicht Laravels RateLimiter?** Der kennt nur feste Zeitfenster, keine
 * wachsende Sperre, und liegt im Cache statt in der Datenbank.
 *
 * **Bekannte Grenze:** Wer die E-Mail-Adresse eines anderen kennt, kann dessen
 * Anmeldung durch absichtliche Fehlversuche voruebergehend sperren. Das ist der
 * uebliche Tausch — ohne Sperre pro Konto nuetzt ein Angreifer mit vielen
 * Adressen die IP-Bremse einfach aus. Die Sperre laeuft von selbst ab.
 */
class LoginThrottle
{
    /** So viele Versuche sind frei. Bei 3 ist der vierte der erste gesperrte. */
    public const FREE_ATTEMPTS = 3;

    /** Wartezeit fuer den ersten Versuch nach den Freiversuchen. */
    public const BASE_SECONDS = 5;

    /** Faktor, um den die Wartezeit je weiterem Fehlversuch waechst. */
    public const GROWTH_FACTOR = 3;

    /** Obergrenze, damit ein Konto nicht dauerhaft unbenutzbar wird. */
    public const MAX_SECONDS = 15 * 60;

    /** Wartezeit nach `$failures` Fehlversuchen, in Sekunden. */
    public static function delaySeconds(int $failures): int
    {
        if ($failures < self::FREE_ATTEMPTS) {
            return 0;
        }

        $step = min($failures - self::FREE_ATTEMPTS, 10);

        return (int) min(self::BASE_SECONDS * self::GROWTH_FACTOR ** $step, self::MAX_SECONDS);
    }

    /**
     * Schluessel, unter denen ein Anmeldeversuch gezaehlt wird.
     *
     * @return list<string>
     */
    public static function keys(string $email, ?string $ip): array
    {
        $keys = ['email:'.mb_strtolower(trim($email))];
        if ($ip !== null && $ip !== '') {
            $keys[] = 'ip:'.$ip;
        }

        return $keys;
    }

    /**
     * Prueft VOR dem Anmeldeversuch, ob gerade eine Sperre laeuft.
     *
     * @param  list<string>  $keys
     * @return int|null Verbleibende Sekunden, oder `null`, wenn frei.
     */
    public function lockedFor(array $keys): ?int
    {
        $lockedUntil = DB::table('login_attempts')
            ->whereIn('attempt_key', $keys)
            ->where('locked_until', '>', now())
            ->max('locked_until');

        if ($lockedUntil === null) {
            return null;
        }

        return max(1, (int) ceil(now()->diffInSeconds(Carbon::parse($lockedUntil))));
    }

    /**
     * Vermerkt einen Fehlversuch und setzt die naechste Sperre.
     *
     * @param  list<string>  $keys
     */
    public function recordFailure(array $keys): void
    {
        foreach ($keys as $key) {
            // Zaehler atomar hochsetzen und den neuen Stand zurueckbekommen.
            $row = DB::selectOne(
                'INSERT INTO login_attempts (attempt_key, failures, last_attempt_at)
                 VALUES (?, 1, now())
                 ON CONFLICT (attempt_key) DO UPDATE
                   SET failures = login_attempts.failures + 1,
                       last_attempt_at = now()
                 RETURNING failures',
                [$key],
            );

            $seconds = self::delaySeconds((int) $row->failures);
            if ($seconds > 0) {
                DB::update(
                    'UPDATE login_attempts SET locked_until = now() + make_interval(secs => ?) WHERE attempt_key = ?',
                    [$seconds, $key],
                );
            }
        }
    }

    /**
     * Nach erfolgreicher Anmeldung ist die Vorgeschichte erledigt.
     *
     * @param  list<string>  $keys
     */
    public function recordSuccess(array $keys): void
    {
        DB::table('login_attempts')->whereIn('attempt_key', $keys)->delete();
    }

    /**
     * Raeumt Eintraege weg, die lange niemand mehr angefasst hat.
     * Laeuft beim Containerstart (`php artisan login-attempts:prune`).
     */
    public function prune(): int
    {
        return DB::table('login_attempts')
            ->where('last_attempt_at', '<', now()->subDays(7))
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', now()))
            ->delete();
    }
}
