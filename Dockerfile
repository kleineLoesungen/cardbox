# Mehrstufiger Build. Ergebnis: ein Abbild mit FrankenPHP (PHP + Webserver in
# einem Prozess), ohne Composer, ohne Tailwind, ohne Node.

# ---------------------------------------------------------------------------
# Stufe 1: Stylesheet bauen
# ---------------------------------------------------------------------------
FROM debian:bookworm-slim AS css
ARG TAILWIND_VERSION=v4.3.3
ARG TARGETARCH

RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates curl \
    && rm -rf /var/lib/apt/lists/*

# TARGETARCH von Docker ("amd64"/"arm64") auf die Namen der Release-Dateien abbilden.
RUN case "${TARGETARCH}" in \
      amd64) ASSET=tailwindcss-linux-x64 ;; \
      arm64) ASSET=tailwindcss-linux-arm64 ;; \
      *) echo "Nicht unterstuetzte Architektur: ${TARGETARCH}" >&2; exit 1 ;; \
    esac \
    && curl -sSL --fail -o /usr/local/bin/tailwindcss \
       "https://github.com/tailwindlabs/tailwindcss/releases/download/${TAILWIND_VERSION}/${ASSET}" \
    && chmod +x /usr/local/bin/tailwindcss

WORKDIR /build
# Tailwind durchsucht Templates UND PHP-Quellen nach Klassennamen.
COPY resources/ resources/
COPY app/ app/
RUN tailwindcss -i resources/css/app.css -o /out/app.css --minify

# ---------------------------------------------------------------------------
# Stufe 2: Laufzeit
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.5-bookworm AS runtime

# pdo_pgsql fuer Postgres, intl fuer Datums- und Zahlenformate, opcache fuer Tempo.
RUN install-php-extensions pdo_pgsql intl opcache zip \
    && apt-get update && apt-get install -y --no-install-recommends curl unzip \
    && rm -rf /var/lib/apt/lists/* \
    && useradd --system --uid 10001 --create-home app

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php.ini $PHP_INI_DIR/conf.d/zz-app.ini

WORKDIR /app

# Erst nur die Abhaengigkeiten. Diese Schicht bleibt im Cache, solange sich
# composer.json/composer.lock nicht aendern.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
COPY --from=css /out/app.css public/css/app.css

RUN composer dump-autoload --optimize --no-dev \
    && rm -f .env \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    # FrankenPHP will in /data und /config schreiben (Caddy-Zustand).
    && chown -R app:app /data /config \
    && chmod +x docker/entrypoint.sh

USER app
EXPOSE 8000

# Nur HTTP auf 8000 — TLS macht der Reverse-Proxy davor.
ENV SERVER_NAME=:8000 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    HASH_DRIVER=argon2id \
    SESSION_DRIVER=database \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    DB_CONNECTION=pgsql

# Prueft mit, ob die Datenbank erreichbar ist — nicht nur, ob der Prozess laeuft.
HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8000/api/v1/health || exit 1

ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
