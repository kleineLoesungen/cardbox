#!/bin/sh
# Laeuft bei jedem Containerstart, bevor der Webserver startet.
set -e

# Konfiguration, Routen und Templates vorkompilieren. Ab hier liefert env()
# ausserhalb von config/*.php null — deshalb gilt im Code nur config('…').
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrationen beim Start. Laravel sperrt dabei die Tabelle `migrations`,
# gleichzeitig startende Instanzen sind also unkritisch.
php artisan migrate --force --isolated
php artisan login-attempts:prune

exec "$@"
