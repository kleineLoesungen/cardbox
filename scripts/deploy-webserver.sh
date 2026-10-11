#!/usr/bin/env bash
# Installs or updates Cardbox on a web server without containers
# (nginx/Apache + PHP-FPM + an existing PostgreSQL).
#
#   ./scripts/deploy-webserver.sh
#
# Run it in the checked-out repository after `git pull`, as the user that
# owns the files (not as root). It is safe to run again: every step only does
# what is still missing. Guide: docs/INSTALL-WEBSERVER.md
set -euo pipefail

cd "$(dirname "$0")/.."

fehler() { echo "Fehler: $*" >&2; exit 1; }
schritt() { echo; echo "==> $*"; }

# --- Prerequisites ----------------------------------------------------------

schritt "Voraussetzungen prüfen"

[ "$(id -u)" -ne 0 ] || fehler "Bitte nicht als root ausführen, sondern als Besitzer der Dateien."
[ -f .env ] || fehler ".env fehlt. Erst: cp .env.example .env – und ausfüllen (siehe docs/INSTALL-WEBSERVER.md)."
command -v php >/dev/null || fehler "php nicht gefunden."
command -v composer >/dev/null || fehler "composer nicht gefunden (https://getcomposer.org/download/)."

php -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' \
  || fehler "PHP $(php -r 'echo PHP_VERSION;') ist zu alt, nötig ist 8.4.1 oder neuer."

# Read the module list once: `php -m | grep -q` would trip over pipefail
# (grep stops early, php gets SIGPIPE).
modules="$(php -m)"
for ext in pdo_pgsql intl mbstring xml curl; do
  grep -qix "$ext" <<<"$modules" || fehler "PHP-Erweiterung '$ext' fehlt."
done

# Passwords are hashed with Argon2id; PHP must be built with libargon2.
php -r 'exit(defined("PASSWORD_ARGON2ID") ? 0 : 1);' \
  || fehler "PHP kann kein Argon2id (Passwörter). PHP aus den Paketen von Debian/Ubuntu bzw. sury.org/ondrej nutzen."

grep -q '^APP_ENV=production' .env || fehler "In .env muss APP_ENV=production stehen."
grep -q '^APP_DEBUG=false' .env || fehler "In .env muss APP_DEBUG=false stehen."

# --- Code -------------------------------------------------------------------

schritt "PHP-Abhängigkeiten installieren (ohne Entwicklungswerkzeuge)"
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

schritt "Stylesheet bauen"
./scripts/get-tailwind.sh
.bin/tailwindcss -i resources/css/app.css -o public/css/app.css --minify

if ! grep -q '^APP_KEY=base64:' .env; then
  schritt "Geheimen Schlüssel erzeugen (APP_KEY)"
  php artisan key:generate --force
  echo "APP_KEY steht jetzt in .env – bitte sicher aufheben (z. B. im Passwortmanager)."
fi

# --- Database ---------------------------------------------------------------

schritt "Datenbank aktualisieren"
# Cached config would hide changes in .env; clear it before talking to the DB.
php artisan config:clear --no-interaction >/dev/null
php artisan migrate --force
php artisan login-attempts:prune

# --- Caches -----------------------------------------------------------------

schritt "Konfiguration, Routen und Ansichten vorbereiten"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- Permissions ------------------------------------------------------------

for dir in storage bootstrap/cache; do
  [ -w "$dir" ] || fehler "$dir ist nicht beschreibbar – siehe „Rechte“ in docs/INSTALL-WEBSERVER.md."
done

cat <<'FERTIG'

Fertig.

Jetzt PHP-FPM neu laden, damit der neue Code sicher aktiv ist, z. B.:
  sudo systemctl reload php8.5-fpm

Prüfen:
  curl https://<deine-adresse>/api/v1/health
FERTIG
