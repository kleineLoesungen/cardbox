#!/bin/sh
# Laeuft bei jedem Containerstart, bevor der Webserver startet.
set -e

# Migrations first, before the config is cached: on a brand-new database the
# lock table that `--isolated` uses (cache_locks, cache store "database")
# does not exist yet — it is created by the migrations themselves. If the
# isolated run fails, run once more with an in-memory lock store. That only
# matters for the very first start; afterwards the database lock keeps
# instances that start at the same time from migrating twice.
if ! php artisan migrate --force --isolated; then
  echo "Migration mit Datenbanksperre fehlgeschlagen – erster Start? Versuche es ohne Sperre." >&2
  CACHE_STORE=array php artisan migrate --force
fi
php artisan login-attempts:prune

# Konfiguration, Routen und Templates vorkompilieren. Ab hier liefert env()
# ausserhalb von config/*.php null — deshalb gilt im Code nur config('…').
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
