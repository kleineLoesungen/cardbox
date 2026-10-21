<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Until 2026.10.21 the connection used the server's time zone. Laravel writes
 * times without offset ("Y-m-d H:i:s", meant as UTC); a server in, say,
 * Europe/Berlin took them as German time and stored them two hours early.
 * Duels showed it first: every card counted the full minute.
 *
 * Since then every connection talks UTC (config/database.php). This turns the
 * stored times back: the wall clock the app wrote, now read as UTC. Nothing
 * happens on a server that runs in UTC anyway.
 *
 * Times the database set itself (now() in SQL, DEFAULT now()) were right and
 * stay. Not told apart: deck_preferences rows that 2026_10_19_100000 filled
 * with now() move two hours as well — their times are never read.
 *
 * The zone it repaired from is kept in `settings`, so down() turns the times
 * back exactly.
 */
return new class extends Migration
{
    /** Set by the database (LoginThrottle's SQL, DEFAULT now() without the app writing it): already right. */
    private const SET_BY_DATABASE = [
        'login_attempts.locked_until',
        'login_attempts.last_attempt_at',
        'deck_follows.created_at',
    ];

    private const SETTING = 'times_repaired_from_zone';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || DB::table('settings')->where('key', self::SETTING)->exists()) {
            return;
        }
        // reset_val: the server's zone for new connections, before our SET TIME ZONE.
        $zone = (string) DB::selectOne("select reset_val from pg_settings where name = 'TimeZone'")->reset_val;
        $offsets = DB::selectOne(
            "select extract(epoch from (timestamp '2026-01-15 12:00' at time zone ?) - timestamptz '2026-01-15 12:00+00') as winter,
                    extract(epoch from (timestamp '2026-07-15 12:00' at time zone ?) - timestamptz '2026-07-15 12:00+00') as summer",
            [$zone, $zone],
        );
        if ((float) $offsets->winter === 0.0 && (float) $offsets->summer === 0.0) {
            return;
        }

        $this->repair($zone);
    }

    public function down(): void
    {
        $zone = DB::table('settings')->where('key', self::SETTING)->value('value');
        if (! is_string($zone)) {
            return;
        }
        DB::transaction(function () use ($zone) {
            $this->shift("({c} at time zone 'UTC') at time zone ?", $zone);
            DB::table('settings')->where('key', self::SETTING)->delete();
        });
    }

    /** Stored instant → the wall clock the app wrote → read as UTC. Public for its test. */
    public function repair(string $zone): void
    {
        DB::transaction(function () use ($zone) {
            $this->shift("({c} at time zone ?) at time zone 'UTC'", $zone);
            DB::table('settings')->insert(['key' => self::SETTING, 'value' => $zone]);
        });
    }

    /** Applies `$expression` ({c} = the column, ? = the zone) to every time column the app wrote, one UPDATE per table. */
    private function shift(string $expression, string $zone): void
    {
        $columns = DB::select(
            "select table_name, column_name from information_schema.columns
             where table_schema = current_schema() and data_type = 'timestamp with time zone'
             order by table_name, column_name",
        );
        $tables = [];
        foreach ($columns as $column) {
            if (! in_array("{$column->table_name}.{$column->column_name}", self::SET_BY_DATABASE, true)) {
                $tables[$column->table_name][] = $column->column_name;
            }
        }
        $quote = fn (string $name) => '"'.str_replace('"', '""', $name).'"';
        foreach ($tables as $table => $names) {
            // A null stays null: "null at time zone …" is null.
            $sets = array_map(fn (string $name) => $quote($name).' = '.str_replace('{c}', $quote($name), $expression), $names);
            DB::update('update '.$quote($table).' set '.implode(', ', $sets), array_fill(0, count($names), $zone));
        }
    }
};
