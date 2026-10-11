#!/usr/bin/env bash
# Macht aus einer Kopie des Kits ein eigenes Projekt.
#
#   git clone <kit> vereinsportal
#   cd vereinsportal
#   ./scripts/new-project.sh vereinsportal "Vereinsportal"
#
# Warum es das braucht: Das Kit heisst an rund zwanzig Stellen "starter".
# Eine davon ist eine stille Falle, wenn man sie vergisst:
#
#   * Der Compose-Projektname ist fest. Zwei Projekte mit demselben Namen
#     teilen sich Container UND das Datenbank-Volume — das zweite Projekt
#     sieht und ueberschreibt die Daten des ersten, ohne jede Fehlermeldung.
#
# Ersetzt werden nur gezielte, eindeutige Muster — nie blind jedes "starter",
# damit Fliesstext wie "Starter-Kit" unangetastet bleibt.
set -euo pipefail

fehler() { echo "Fehler: $*" >&2; exit 1; }

# --- Argumente ------------------------------------------------------------

NAME="${1:-}"
ANZEIGENAME="${2:-}"
PORT_OFFSET="${3:-}"

if [ -z "$NAME" ]; then
  cat >&2 <<'HILFE'
Aufruf: ./scripts/new-project.sh <name> ["Anzeigename"] [port-offset]

  name          technischer Name: Kleinbuchstaben, Ziffern, Bindestrich,
                z. B. vereinsportal oder mein-verein
  Anzeigename   Name in Titel und Kopfzeile, z. B. "Mein Verein"
                (Vorgabe: name mit grossem Anfangsbuchstaben)
  port-offset   1–99, verschiebt alle Ports um diesen Wert
                (Vorgabe: der erste Wert, bei dem alle Ports frei sind)
HILFE
  exit 1
fi

[[ "$NAME" =~ ^[a-z][a-z0-9-]{1,39}$ ]] \
  || fehler "\"$NAME\" ist kein gültiger Name. Erlaubt: Kleinbuchstaben, Ziffern, Bindestrich; mit Buchstabe beginnend."
[[ "$NAME" != *- ]] || fehler "Der Name darf nicht mit einem Bindestrich enden."
[[ "$NAME" != "starter" && "$NAME" != "starter-php" ]] || fehler "Bitte einen eigenen Namen wählen, nicht \"$NAME\"."

# Datenbankname und -benutzer duerfen keinen Bindestrich enthalten.
IDENT="${NAME//-/_}"

if [ -z "$ANZEIGENAME" ]; then
  ANZEIGENAME="$(printf '%s' "${NAME:0:1}" | tr '[:lower:]' '[:upper:]')${NAME:1}"
fi
# Der Kurzname unter dem App-Icon hat kaum Platz.
KURZNAME="${ANZEIGENAME:0:12}"

# --- Voraussetzungen ------------------------------------------------------

cd "$(dirname "$0")/.."

grep -q '"name": "kleineloesungen/starter-php"' composer.json \
  || fehler "composer.json heißt nicht mehr \"kleineloesungen/starter-php\" — das Projekt wurde offenbar schon umbenannt."

if git rev-parse --git-dir >/dev/null 2>&1 && [ -n "$(git status --porcelain)" ]; then
  fehler "Es gibt nicht committete Änderungen. Bitte erst committen, damit sich die Umbenennung mit 'git diff' nachvollziehen lässt."
fi

command -v perl >/dev/null 2>&1 || fehler "perl wird benötigt (auf macOS und Linux üblicherweise vorhanden)."

# --- Ports ----------------------------------------------------------------

port_belegt() { (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }

if [ -z "$PORT_OFFSET" ]; then
  for n in $(seq 1 99); do
    if ! port_belegt $((8000 + n)) && ! port_belegt $((56432 + n)) \
       && ! port_belegt $((52025 + n)) && ! port_belegt $((59025 + n)); then
      PORT_OFFSET=$n
      break
    fi
  done
  [ -n "$PORT_OFFSET" ] || fehler "Kein freier Port-Bereich gefunden. Offset als drittes Argument angeben."
fi
[[ "$PORT_OFFSET" =~ ^[0-9]+$ ]] && [ "$PORT_OFFSET" -ge 1 ] && [ "$PORT_OFFSET" -le 99 ] \
  || fehler "port-offset muss zwischen 1 und 99 liegen."

P_APP=$((8000 + PORT_OFFSET))
P_DB=$((56432 + PORT_OFFSET))
P_SMTP=$((52025 + PORT_OFFSET))
P_UI=$((59025 + PORT_OFFSET))

# --- Ersetzen -------------------------------------------------------------

# ersetze <datei> <von> <nach> — woertlich, ohne regulaere Ausdruecke.
# Die Werte laufen ueber Umgebungsvariablen, damit keine Sonderzeichen
# (Leerzeichen, <, >, @) in den Perl-Befehl geraten.
ersetze() {
  local datei="$1"
  [ -f "$datei" ] || return 0
  VON="$2" NACH="$3" perl -pi -e 's/\Q$ENV{VON}\E/$ENV{NACH}/g' "$datei"
}

echo "Benenne um: starter-php → $NAME  (Datenbank: $IDENT, Anzeige: \"$ANZEIGENAME\")"

# Composer-Paket
ersetze composer.json '"name": "kleineloesungen/starter-php"' "\"name\": \"app/$NAME\""

# Vorgabe-Name (greift, wenn APP_NAME nicht gesetzt ist)
ersetze config/app.php "env('APP_NAME', 'Starter')," "env('APP_NAME', '$ANZEIGENAME'),"
ersetze config/app.php "env('APP_SHORT_NAME', env('APP_NAME', 'Starter'))" "env('APP_SHORT_NAME', env('APP_NAME', '$KURZNAME'))"

# Docker Compose
ersetze compose.yaml 'name: starter-php' "name: $NAME"
ersetze compose.yaml 'POSTGRES_USER: starter' "POSTGRES_USER: $IDENT"
ersetze compose.yaml 'POSTGRES_PASSWORD: starter' "POSTGRES_PASSWORD: $IDENT"
ersetze compose.yaml 'POSTGRES_DB: starter' "POSTGRES_DB: $IDENT"
ersetze compose.yaml 'pg_isready -U starter' "pg_isready -U $IDENT"
ersetze compose.yaml 'DB_DATABASE: starter' "DB_DATABASE: $IDENT"
ersetze compose.yaml 'DB_USERNAME: starter' "DB_USERNAME: $IDENT"
ersetze compose.yaml 'DB_PASSWORD: starter' "DB_PASSWORD: $IDENT"
ersetze compose.yaml 'APP_NAME: Starter' "APP_NAME: \"$ANZEIGENAME\""
ersetze compose.yaml 'MAIL_FROM_NAME: Starter' "MAIL_FROM_NAME: \"$ANZEIGENAME\""
ersetze compose.yaml '"56432:5432"' "\"$P_DB:5432\""
ersetze compose.yaml '56432 statt 5432' "$P_DB statt 5432"
ersetze compose.yaml '"52025:1025"' "\"$P_SMTP:1025\""
ersetze compose.yaml '"59025:8025"' "\"$P_UI:8025\""
ersetze compose.yaml '"8000:8000"' "\"$P_APP:8000\""
ersetze compose.yaml 'APP_URL: http://localhost:8000' "APP_URL: http://localhost:$P_APP"

# Testdatenbank — muss zu phpunit.xml passen
ersetze docker/postgres-init.sql 'CREATE DATABASE starter_test OWNER starter;' "CREATE DATABASE ${IDENT}_test OWNER $IDENT;"
ersetze phpunit.xml '<env name="DB_DATABASE" value="starter_test"/>' "<env name=\"DB_DATABASE\" value=\"${IDENT}_test\"/>"
ersetze phpunit.xml 'Eigene Datenbank starter_test' "Eigene Datenbank ${IDENT}_test"

# Justfile
ersetze Justfile 'pg_isready -U starter' "pg_isready -U $IDENT"
ersetze Justfile 'psql -U starter -d starter' "psql -U $IDENT -d $IDENT"
ersetze Justfile 'docker build -t starter-php:latest' "docker build -t $NAME:latest"
ersetze Justfile 'php artisan serve --port=8000' "php artisan serve --port=$P_APP"

# Vorschau in der Claude-Desktop-App
ersetze .claude/launch.json '"name": "starter-php"' "\"name\": \"$NAME\""
ersetze .claude/launch.json '"--port=8000"' "\"--port=$P_APP\""
ersetze .claude/launch.json '"port": 8000' "\"port\": $P_APP"

# Beispielkonfiguration — und eine vorhandene .env gleich mit, sonst zeigte
# sie weiter auf die Datenbank des Kits.
for f in .env.example .env; do
  ersetze "$f" 'APP_NAME=Starter' "APP_NAME=\"$ANZEIGENAME\""
  ersetze "$f" 'APP_SHORT_NAME=Starter' "APP_SHORT_NAME=\"$KURZNAME\""
  ersetze "$f" 'DB_PORT=56432' "DB_PORT=$P_DB"
  ersetze "$f" 'DB_DATABASE=starter' "DB_DATABASE=$IDENT"
  ersetze "$f" 'DB_USERNAME=starter' "DB_USERNAME=$IDENT"
  ersetze "$f" 'DB_PASSWORD=starter' "DB_PASSWORD=$IDENT"
  ersetze "$f" 'MAIL_PORT=52025' "MAIL_PORT=$P_SMTP"
done

# Ports und Namen in Doku und Kommentaren, damit weder Menschen noch
# KI-Assistenten auf die Werte des Kits verwiesen werden.
for f in README.md CLAUDE.md docs/*.md .env.example .env Justfile compose.yaml; do
  ersetze "$f" 'localhost:59025' "localhost:$P_UI"
  ersetze "$f" 'localhost:8000' "localhost:$P_APP"
done
ersetze docs/DEPLOY.md 'starter-php:latest' "$NAME:latest"
ersetze docs/DEPLOY.md 'starter-php:neu' "$NAME:neu"
ersetze docs/DEPLOY.md '--name starter-php' "--name $NAME"
ersetze docs/DEPLOY.md 'docker stop starter-php && docker rm starter-php' "docker stop $NAME && docker rm $NAME"
ersetze docs/DEPLOY.md 'docker exec -it starter-php' "docker exec -it $NAME"
ersetze docs/DEPLOY.md '/etc/starter-php/env' "/etc/$NAME/env"

# Changelog zuruecksetzen: Ein Projekt aus dem Kit hat seine eigene Geschichte.
# Ohne das zeigte es die Versionen des Kits an.
cat > CHANGELOG.md <<CHANGELOG
# Änderungen

Neueste Version oben. Eine Überschrift je Version im Format \`JJJJ.MM.TT\`,
bei mehreren Veröffentlichungen am selben Tag mit angehängter Zählung (\`.2\`).
Die oberste Überschrift ist die Version, die die Anwendung von sich meldet.

Einträge sind reiner Text — Markdown darin wird nicht ausgewertet.
Zeilen, die mit \`Migration:\` beginnen, werden getrennt hervorgehoben.

## $(date +%Y.%m.%d)
- Projekt aus dem Kit angelegt
CHANGELOG

# composer.lock nachziehen: Nur der Paketname aendert sich, keine
# Abhaengigkeit wird aktualisiert.
if command -v composer >/dev/null 2>&1; then
  composer update --lock --no-interaction --quiet || true
fi

# --- Kontrolle ------------------------------------------------------------

# Alles, was nach Konfiguration aussieht und noch "starter" heisst, ist ein Fehler dieses Skripts.
REST="$(grep -rnE 'starter-php|starter_test|=starter$|: starter$|-U starter' \
          composer.json compose.yaml Justfile phpunit.xml .env.example docker/ .claude/ 2>/dev/null || true)"
if [ -n "$REST" ]; then
  echo ""
  echo "WARNUNG: Diese Stellen heißen noch \"starter\":" >&2
  echo "$REST" >&2
fi

cat <<FERTIG

Fertig. Ports dieses Projekts (Offset $PORT_OFFSET):

  Anwendung   http://localhost:$P_APP
  Postgres    localhost:$P_DB
  Mailpit     http://localhost:$P_UI   (SMTP $P_SMTP)

Nächste Schritte:

  git diff --stat              Änderungen ansehen
  just setup                   Composer, Tailwind, .env, Datenbank, Mailpit
  just check                   alles muss grün sein
  git commit -am "Projekt $NAME aus dem Kit angelegt"

Danach nach Belieben: README-Titel, Icons (resources/icons/), --brand-hue in
resources/css/theme.css. Zeigt 'git remote -v' noch auf das Kit:
  git remote remove origin

Für den Hinweis auf neuere Versionen in .env eintragen (eigenes Repository):
  UPDATE_CHECK_URL=https://raw.githubusercontent.com/<konto>/$NAME/main/CHANGELOG.md
FERTIG
