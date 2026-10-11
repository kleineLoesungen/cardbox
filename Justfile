# Alle wiederkehrenden Handgriffe an einem Ort.
# `just` ohne Argument zeigt die Liste.
#
# Installation von just:  brew install just   (oder: apt install just)

_default:
    @just --list --unsorted

# --- Erster Start ----------------------------------------------------------

# Einmalige Einrichtung: Abhaengigkeiten, Tailwind, .env, Datenbank, Mailpit.
setup:
    composer install
    ./scripts/get-tailwind.sh
    [ -f .env ] || (cp .env.example .env && php artisan key:generate)
    just db-up
    just mail-up
    php artisan migrate
    just css
    @echo ""
    @echo "Fertig. Weiter mit:  just dev"

# --- Entwicklung -----------------------------------------------------------

# Server starten (http://localhost:8000). Vorher wird das Stylesheet frisch gebaut.
dev: css
    php artisan migrate
    php artisan serve --port=8000

# Stylesheet einmalig bauen.
css:
    .bin/tailwindcss -i resources/css/app.css -o public/css/app.css

# Stylesheet beobachten (zweites Terminal, neben `just dev`).
css-watch:
    .bin/tailwindcss -i resources/css/app.css -o public/css/app.css --watch

# Neue Migration anlegen, z. B.: just migration create_projects_table
migration name:
    php artisan make:migration {{name}}

# --- Datenbank -------------------------------------------------------------

# Lokale Postgres im Container starten.
db-up:
    docker compose up -d db
    @echo "Warte auf Postgres ..."
    @until docker compose exec -T db pg_isready -U starter -q; do sleep 1; done
    @echo "Postgres bereit."

db-down:
    docker compose down

# Mailpit starten: faengt alle Mails ab, Ansicht unter http://localhost:59025
mail-up:
    docker compose up -d mail
    @echo "Mailpit läuft: http://localhost:59025"

# ACHTUNG: loescht alle lokalen Daten und legt die Datenbank neu an.
db-reset:
    docker compose down -v
    just db-up
    php artisan migrate

# psql-Eingabeaufforderung oeffnen.
db-shell:
    docker compose exec db psql -U starter -d starter

# --- Qualitaet -------------------------------------------------------------

# Alles pruefen: Formatierung, statische Analyse, Templates, Tests.
check: fmt-check lint views test

# Tests laufen gegen die echte Postgres — die wird deshalb vorher gestartet
# (laeuft sie schon, kostet das nur einen Augenblick).
test: db-up
    php artisan config:clear --ansi
    php artisan test

fmt:
    vendor/bin/pint

fmt-check:
    vendor/bin/pint --test

# Statische Analyse (Larastan). Findet Tippfehler in Methoden, Feldern, Typen.
lint:
    vendor/bin/phpstan analyse --memory-limit=1G --no-progress

# Alle Blade-Templates kompilieren — Syntaxfehler fallen hier auf, nicht erst im Browser.
views:
    php artisan view:cache
    php artisan view:clear

# --- Container -------------------------------------------------------------

# Abbild bauen.
build:
    docker build -t starter-php:latest .

# Vollstaendig im Container starten (Anwendung + Datenbank + Mailpit).
up:
    docker compose --profile app up --build
