#!/usr/bin/env bash
# Deploys Cardbox to web space that only offers FTP (no shell on the server).
#
#   ./deploy.sh
#
# What it does, from your computer:
#   1. checks the code (just check) and that everything is committed
#   2. builds the last commit in a temporary folder: PHP dependencies without
#      dev tools, stylesheet
#   3. uploads it via FTPS (lftp mirror) — server-side data in storage/ and
#      the server's .env stay untouched
#   4. calls POST /api/v1/deploy on the server: checks PHP and runs the
#      database migrations
#   5. checks /api/v1/health
#
# Configuration comes from .env.deploy next to this script (gitignored, never
# uploaded) or from the environment — never from argv, which `ps` shows to
# every user and which lands in shell history. Template: .env.deploy.example.
# Guide: docs/INSTALL-WEBSPACE.md
#
# Requires lftp (brew install lftp / apt install lftp), composer, php, curl.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

fehler() { echo "Fehler: $*" >&2; exit 1; }
schritt() { echo; echo "==> $*"; }

if [[ -f .env.deploy ]]; then
    set -a
    # shellcheck disable=SC1091
    source .env.deploy
    set +a
fi

FTP_HOST="${FTP_HOST:-}"
FTP_PORT="${FTP_PORT:-21}"
FTP_USER="${FTP_USER:-}"
FTP_PASS="${FTP_PASS:-}"
FTP_DIR="${FTP_DIR:-}"
FTP_DIR="${FTP_DIR%/}"
FTP_SSL="${FTP_SSL:-true}"
DEPLOY_URL="${DEPLOY_URL:-}"
DEPLOY_URL="${DEPLOY_URL%/}"
DEPLOY_HOOK_TOKEN="${DEPLOY_HOOK_TOKEN:-}"
DEPLOY_ENV_FILE="${DEPLOY_ENV_FILE:-.env.production}"
DEPLOY_SKIP_CHECK="${DEPLOY_SKIP_CHECK:-false}"
DEPLOY_ALLOW_DIRTY="${DEPLOY_ALLOW_DIRTY:-false}"

# --- Prerequisites ----------------------------------------------------------

schritt "Voraussetzungen prüfen"

[[ -n "$FTP_HOST" && -n "$FTP_USER" && -n "$FTP_DIR" && -n "$DEPLOY_URL" ]] \
    || fehler "FTP_HOST, FTP_USER, FTP_DIR und DEPLOY_URL müssen gesetzt sein (in .env.deploy, Vorlage: .env.deploy.example)."
[[ ${#DEPLOY_HOOK_TOKEN} -ge 32 ]] \
    || fehler "DEPLOY_HOOK_TOKEN fehlt oder ist kürzer als 32 Zeichen (erzeugen: openssl rand -hex 32)."
[[ "$DEPLOY_URL" == https://* ]] \
    || fehler "DEPLOY_URL muss mit https:// beginnen – das Token reist darüber."

for tool in git php composer lftp curl; do
    command -v "$tool" >/dev/null || fehler "$tool fehlt."
done

if [[ "$DEPLOY_ALLOW_DIRTY" != "true" ]]; then
    git diff --quiet && git diff --cached --quiet \
        || fehler "Es gibt nicht committete Änderungen. Hochgeladen wird der letzte Commit – erst committen."
fi

# The server's .env: uploaded on every deploy if present, so changes there
# arrive too. It has to match this file in a few points.
if [[ -f "$DEPLOY_ENV_FILE" ]]; then
    grep -q '^APP_KEY=base64:' "$DEPLOY_ENV_FILE" \
        || fehler "$DEPLOY_ENV_FILE: APP_KEY fehlt (erzeugen: echo \"base64:\$(openssl rand -base64 32)\")."
    grep -q '^APP_ENV=production' "$DEPLOY_ENV_FILE" || fehler "$DEPLOY_ENV_FILE: APP_ENV=production fehlt."
    grep -q '^APP_DEBUG=false' "$DEPLOY_ENV_FILE" || fehler "$DEPLOY_ENV_FILE: APP_DEBUG=false fehlt."
    # Compared in bash: the token must not appear in a process's argv.
    [[ "$(grep -m1 '^DEPLOY_HOOK_TOKEN=' "$DEPLOY_ENV_FILE")" == "DEPLOY_HOOK_TOKEN=${DEPLOY_HOOK_TOKEN}" ]] \
        || fehler "$DEPLOY_ENV_FILE: DEPLOY_HOOK_TOKEN muss derselbe Wert sein wie in .env.deploy."
else
    echo "Hinweis: $DEPLOY_ENV_FILE fehlt – die .env auf dem Server bleibt, wie sie ist."
fi

if [[ -z "$FTP_PASS" ]]; then
    read -rsp "FTP-Passwort für ${FTP_USER}@${FTP_HOST}: " FTP_PASS
    echo ""
fi
[[ -n "$FTP_PASS" ]] || fehler "Kein Passwort angegeben."

# --- Check ------------------------------------------------------------------

if [[ "$DEPLOY_SKIP_CHECK" != "true" ]]; then
    schritt "Code prüfen (just check)"
    command -v just >/dev/null || fehler "just fehlt (oder DEPLOY_SKIP_CHECK=true setzen)."
    just check >/dev/null || fehler "just check ist rot – nicht hochladen. Mit 'just check' ansehen."
fi

# --- Build ------------------------------------------------------------------

schritt "Letzten Commit bauen ($(git log -1 --format='%h %s'))"
BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT

git archive HEAD | tar -x -C "$BUILD"

(cd "$BUILD" && composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet)

./scripts/get-tailwind.sh >/dev/null
(cd "$BUILD" && "$SCRIPT_DIR/.bin/tailwindcss" -i resources/css/app.css -o public/css/app.css --minify 2>/dev/null)

# Not needed on the server.
rm -rf "$BUILD"/{tests,docs,deploy,docker,.claude,scripts} \
       "$BUILD"/{deploy.sh,.env.deploy.example,Dockerfile,compose.yaml,Justfile,phpunit.xml,phpstan.neon,pint.json,CLAUDE.md,README.md,.dockerignore}

# The domain usually points at FTP_DIR itself (hosts that only offer
# public_html). This .htaccess hands every request to public/, so .env and
# the code are never served.
cp deploy/webspace/htaccess-root "$BUILD/.htaccess"

# A file the web server cannot read is served as 403, silently and only for
# that file. Make everything readable before uploading.
chmod -R a+rX "$BUILD"

if [[ -f "$DEPLOY_ENV_FILE" ]]; then
    cp "$DEPLOY_ENV_FILE" "$BUILD/.env"
fi

# Probe file: fetched via DEPLOY_URL after the upload to prove that the
# domain points at FTP_DIR. Lives in .well-known/, which the .htaccess serves
# directly instead of handing it to Laravel.
PROBE_NAME="cardbox-probe-$(php -r 'echo bin2hex(random_bytes(8));').txt"
PROBE_DIR="$(mktemp -d)"
echo "$PROBE_NAME" > "$PROBE_DIR/$PROBE_NAME"
chmod a+r "$PROBE_DIR/$PROBE_NAME"
trap 'rm -rf "$BUILD" "$PROBE_DIR"' EXIT

# --- Upload -----------------------------------------------------------------

schritt "Hochladen nach ${FTP_HOST}:${FTP_DIR} (FTPS: ${FTP_SSL})"

# Credentials go over stdin via `open`, not on the lftp command line.
# Excluded paths are neither uploaded nor deleted on the server: the server's
# .env, logs and caches survive every deploy. `[^.]` keeps the .gitignore
# placeholders, so the folder structure exists after the first deploy.
# cmd:fail-exit: stop at the first failing command and exit non-zero —
# otherwise the final `bye` succeeds and a failed upload looks fine.
lftp <<FTPEOF || fehler "Hochladen fehlgeschlagen (siehe Meldung oben)."
set cmd:fail-exit yes
set ftp:ssl-force ${FTP_SSL}
set ftp:ssl-protect-data ${FTP_SSL}
set ssl:verify-certificate yes
set net:max-retries 2
open -u "${FTP_USER},${FTP_PASS}" -p ${FTP_PORT} "${FTP_HOST}"
mkdir -p -f "${FTP_DIR}"
mirror --reverse --delete --parallel=4 \
    --exclude '^\.env$' \
    --exclude '^storage/logs/[^.]' \
    --exclude '^storage/app/(private|public)/[^.]' \
    --exclude '^storage/framework/cache/data/[^.]' \
    --exclude '^storage/framework/sessions/[^.]' \
    --exclude '^storage/framework/views/[^.]' \
    --exclude '^storage/framework/down$' \
    --exclude '^\.well-known/' \
    "${BUILD}/" "${FTP_DIR}/"
$( [[ -f "$BUILD/.env" ]] && printf 'put -O "%s" "%s/.env"\nchmod 600 "%s/.env"\n' "$FTP_DIR" "$BUILD" "$FTP_DIR" )
mkdir -p -f "${FTP_DIR}/.well-known"
put -O "${FTP_DIR}/.well-known" "${PROBE_DIR}/${PROBE_NAME}"
bye
FTPEOF

schritt "Prüfen, ob ${DEPLOY_URL} auf ${FTP_DIR} zeigt"

PROBE_BODY=$(curl -s --max-time 20 "${DEPLOY_URL}/.well-known/${PROBE_NAME}" || true)

# Remove the probe again; a failure here is not worth aborting the deploy.
lftp <<FTPEOF >/dev/null 2>&1 || true
set ftp:ssl-force ${FTP_SSL}
set ftp:ssl-protect-data ${FTP_SSL}
open -u "${FTP_USER},${FTP_PASS}" -p ${FTP_PORT} "${FTP_HOST}"
rm "${FTP_DIR}/.well-known/${PROBE_NAME}"
bye
FTPEOF

if [[ "$PROBE_BODY" != "$PROBE_NAME" ]]; then
    fehler "Die Domain zeigt nicht auf den Upload-Ordner. Hochgeladen wurde nach '${FTP_DIR}' (vom Startordner des FTP-Kontos aus), aber ${DEPLOY_URL}/.well-known/${PROBE_NAME} liefert die Datei nicht.
  - Im Kundenmenü nachsehen, auf welchen Ordner die Domain zeigt, und FTP_DIR genau so setzen – relativ zu dem Ordner, den das FTP-Programm nach der Anmeldung zeigt.
  - Startet das FTP-Konto schon in public_html, ist FTP_DIR=cards statt public_html/cards richtig.
  - Neue Domains und Subdomains sind beim Hoster manchmal erst nach einigen Minuten aktiv."
fi
echo "✓ ${DEPLOY_URL} zeigt auf ${FTP_DIR}"

# --- Migrate ----------------------------------------------------------------

schritt "Server prüfen und Datenbank migrieren"

RESULT="$BUILD/deploy-result.json"
# The token goes in via a curl config on stdin, not on the command line.
STATUS=$(curl -sS -o "$RESULT" -w '%{http_code}' --max-time 120 --config - <<CURLEOF
url = "${DEPLOY_URL}/api/v1/deploy"
request = "POST"
header = "Authorization: Bearer ${DEPLOY_HOOK_TOKEN}"
header = "X-Deploy-Token: ${DEPLOY_HOOK_TOKEN}"
header = "Accept: application/json"
CURLEOF
) || fehler "Server nicht erreichbar unter ${DEPLOY_URL}."

if [[ "$STATUS" == "404" ]]; then
    # Cardbox answers a refused token with its own JSON 404. Anything else
    # means the address does not reach Cardbox at all.
    if grep -q '"not_found"' "$RESULT"; then
        fehler "Deploy-Hook antwortet 404: Cardbox läuft, aber das Token passt nicht. DEPLOY_HOOK_TOKEN in .env.production und .env.deploy vergleichen."
    fi
    fehler "${DEPLOY_URL}/api/v1/deploy antwortet 404, aber nicht von Cardbox. Zeigt die Domain auf ${FTP_DIR}? Stimmt DEPLOY_URL? Antwort des Servers: $(head -c 300 "$RESULT")"
fi

php -r '
    $r = json_decode(file_get_contents($argv[1]), true);
    if (! is_array($r)) { fwrite(STDERR, "Unerwartete Antwort:\n".file_get_contents($argv[1])."\n"); exit(1); }
    if (isset($r["error"])) {
        // The hook itself failed before its checks (exception, HTTP 500).
        fwrite(STDERR, "  ✗ Der Server meldet: ".($r["error"]["message"] ?? json_encode($r["error"]))."\n"
            ."    Details stehen im Log auf dem Server: ".$argv[2]."/storage/logs/ (per FTP herunterladen).\n");
        exit(1);
    }
    foreach ($r["checks"] ?? [] as $name => $c) {
        printf("  %s %-24s %s\n", $c["ok"] ? "✓" : "✗", $name, $c["detail"]);
    }
    echo "\n", $r["migrations"] ?? "", "\n";
    exit(($r["ok"] ?? false) ? 0 : 1);
' "$RESULT" "$FTP_DIR" || fehler "Der Server ist noch nicht bereit (siehe ✗ oben). Beheben und ./deploy.sh erneut ausführen."

# --- Smoke test -------------------------------------------------------------

schritt "Abschlussprüfung"

# If the server ignores the .htaccess in FTP_DIR (nginx, AllowOverride None),
# secrets would be downloadable. Must not be 200.
for path in .env composer.json; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "${DEPLOY_URL}/${path}")
    [[ "$code" != "200" ]] || fehler "${DEPLOY_URL}/${path} ist abrufbar! Der Server beachtet die .htaccess in ${FTP_DIR} nicht. Sofort die Domain auf ${FTP_DIR}/public stellen (oder die Dateien löschen) und APP_KEY, DB-Passwort und DEPLOY_HOOK_TOKEN erneuern."
done

curl -fsS --max-time 20 "${DEPLOY_URL}/api/v1/health" || fehler "${DEPLOY_URL}/api/v1/health antwortet nicht mit OK."
echo ""
echo ""
echo "==> Fertig: $(grep -m1 '^## ' CHANGELOG.md | sed 's/^## //') ist auf ${DEPLOY_URL}"
