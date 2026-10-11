# Produktivbetrieb

---

## Vor dem ersten Ausrollen

Diese Liste durchgehen:

- [ ] `APP_KEY` eigens erzeugt (`php artisan key:generate --show`) und sicher
      abgelegt. Er verschlüsselt Cookies und Sitzungen — ändert er sich, sind
      alle Benutzer abgemeldet.
- [ ] `APP_DEBUG=false` — sonst sieht jeder Besucher bei einem Fehler
      Stacktraces samt Umgebungsvariablen. Das Abbild setzt es als Vorgabe.
- [ ] `SESSION_SECURE_COOKIE=true` — sonst reist das Session-Cookie unverschlüsselt.
- [ ] `APP_URL` auf die echte Adresse setzen (`https://…`). Links in Mails
      und der Beispielbefehl unter **Tokens** benutzen sie.
- [ ] HTTPS davor. Die Anwendung terminiert selbst kein TLS — das macht der
      Reverse-Proxy (nginx, Caddy, Traefik) oder der Loadbalancer.
- [ ] `TRUST_PROXY=true`, sobald ein Reverse-Proxy davorsteht. Sonst sieht die
      Anwendung nur dessen Adresse, die Anmeldebremse zählt alle Besucher als
      einen, und Laravel erkennt HTTPS nicht. Ohne Proxy muss es `false`
      bleiben, sonst lässt sich die Bremse pro IP mit einem selbst gesetzten
      `X-Forwarded-For` umgehen.
- [ ] `DB_SSLMODE=require`, wenn die Datenbank über ein Netz erreichbar ist.
- [ ] `MAIL_*` gesetzt, danach unter **System** eine Testmail verschickt.
- [ ] `APP_NAME`, `APP_SHORT_NAME`, `PWA_THEME_COLOR` gesetzt. Die Werte landen
      im Manifest; eine bereits installierte App übernimmt Namensänderungen je
      nach Gerät erst verzögert oder nach Neuinstallation.
- [ ] Datenbanksicherung eingerichtet **und einmal zurückgespielt**.
- [ ] `LOG_LEVEL=info` — `debug` protokolliert deutlich mehr.
- [ ] Neuer Eintrag in `CHANGELOG.md` mit dem Datum dieser Veröffentlichung.
      Die oberste Überschrift ist die Version, die die Instanz anzeigt.
- [ ] Erstes Konto anlegen und prüfen, dass es Administrator ist.

---

## Container starten

```bash
docker build -t starter-php:latest .

docker run -d \
  --name starter-php \
  -p 8000:8000 \
  --env-file /etc/starter-php/env \
  --restart unless-stopped \
  starter-php:latest
```

Mit einer Umgebungsdatei `/etc/starter-php/env` (Rechte `600`):

```ini
APP_NAME=Vereinsportal
APP_KEY=base64:…
APP_URL=https://app.example.com
TRUST_PROXY=true
SESSION_SECURE_COOKIE=true
DB_HOST=db.example.com
DB_PORT=5432
DB_DATABASE=starter
DB_USERNAME=starter
DB_PASSWORD=…
DB_SSLMODE=require
MAIL_MAILER=smtp
MAIL_URL=smtps://benutzer:passwort@mail.example.com:465
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=Vereinsportal
LOG_LEVEL=info
```

**Passwörter nicht als `-e` übergeben**, sie stehen sonst in `docker inspect`
und in der Shell-Historie. Docker Secrets, Kubernetes Secrets oder eine
`--env-file` mit engen Dateirechten benutzen.

> In einer `--env-file` von Docker sind Anführungszeichen **Teil des Werts**.
> Dort also `PWA_THEME_COLOR=#3370c7` ohne Anführungszeichen — anders als in
> der `.env` für die lokale Entwicklung, wo phpdotenv sie braucht.

Das Abbild bringt einen `HEALTHCHECK` mit, der `/api/v1/health` abfragt — und
damit auch die Datenbankverbindung, nicht nur den Prozess.

### Was beim Start passiert

`docker/entrypoint.sh`:

1. `config:cache`, `route:cache`, `view:cache` — Konfiguration, Routen und
   Templates vorkompilieren. Ab hier liefert `env()` außerhalb von `config/`
   nichts mehr.
2. `migrate --force --isolated` — Migrationen, mit Sperre gegen gleichzeitig
   startende Instanzen.
3. `login-attempts:prune` — alte Einträge der Anmeldebremse wegräumen.
4. FrankenPHP startet auf Port 8000.

Scheitert ein Schritt — etwa weil die Datenbank nicht erreichbar ist oder eine
Farbe in der Umgebung falsch geschrieben ist —, beendet sich der Container mit
einer Meldung im Log.

---

## Version und Update-Hinweis

Die installierte Version ist die oberste Überschrift in `CHANGELOG.md`. Sie
steht unter **System** und klein in der Fußzeile — beides nur für Angemeldete.

Mit

```bash
UPDATE_CHECK_URL=https://raw.githubusercontent.com/konto/repo/main/CHANGELOG.md
```

vergleicht die Instanz ihre Version höchstens **einmal täglich** mit dieser
Datei und zeigt Administratoren neuere Einträge. Sie lädt nichts herunter und
installiert nichts.

Vier Punkte dazu:

- **Die Instanz spricht dabei nach außen** und verrät der Gegenstelle ihre
  IP-Adresse. Wer das nicht will, lässt die Variable leer — dann findet kein
  Netzzugriff statt. Das ist die Vorgabe.
- Die Datei muss **öffentlich lesbar** sein. Für private Repositories bräuchte
  es ein Zugangstoken; das ist nicht eingebaut. Kein Token in die Adresse
  schreiben — sie taucht im Log auf (ohne Query, aber darauf sollte man sich
  nicht verlassen).
- Umleitungen folgt die Anwendung nur zu `https` und höchstens dreimal, liest
  höchstens 256 KiB und bricht nach 3 Sekunden ab. Sonst könnte die Gegenstelle
  den Server auf eine interne Adresse schicken oder die Systemseite aufhalten.
- Ist die Gegenstelle nicht erreichbar, bleibt der zuletzt bekannte Stand
  stehen und wird als veraltet gekennzeichnet. Die Anwendung läuft unberührt
  weiter.

Der Zwischenspeicher liegt in der Tabelle `settings` unter dem Schlüssel
`update_check`. Ein Wechsel der Prüfadresse verwirft ihn.

---

## E-Mail

```bash
# TLS direkt (Port 465)
MAIL_URL=smtps://benutzer:passwort@mail.example.com:465
# STARTTLS (Port 587)
MAIL_URL=smtp://benutzer:passwort@mail.example.com:587
```

Sonderzeichen im Passwort URL-kodieren (`@` → `%40`, `:` → `%3A`, `/` → `%2F`),
sonst wird die Adresse falsch zerlegt. Alternativ einzeln über `MAIL_HOST`,
`MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`.

Unter **System** (`/admin/system`) zeigt die Anwendung den wirksamen Versandweg
— ohne Passwort — und verschickt auf Knopfdruck eine Testmail. Schlägt sie
fehl, steht dort die Meldung des Mailservers.

---

## Installierbare App (PWA)

Die Anwendung bringt Manifest, Icons und einen Service Worker mit. Browser
bieten die Installation aber **nur über HTTPS** an (oder auf `localhost`). Im
lokalen Netz per `http://192.168…` gibt es weder Installation noch
Offline-Seite — das ist eine Vorgabe der Browser, kein Fehler.

Der Service Worker speichert **keine Seiten** zwischen, nur Stylesheet, htmx
und Icons. Ohne Netz erscheint eine Offline-Seite.

Eigene Icons: SVG unter `resources/icons/` ersetzen, dann `./scripts/icons.sh`.

---

## Umgekehrter Proxy

nginx als Beispiel:

```nginx
server {
    listen 443 ssl http2;
    server_name app.example.com;

    ssl_certificate     /etc/letsencrypt/live/app.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.example.com/privkey.pem;

    client_max_body_size 20m;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Stylesheet, htmx und Icons dürfen lange im Cache liegen.
    location ~ ^/(css|js|icons)/ {
        proxy_pass http://127.0.0.1:8000;
        expires 7d;
        add_header Cache-Control "public";
    }
}
```

`X-Forwarded-Proto` ist wichtig: Ohne ihn hält Laravel jede Anfrage für HTTP
und erzeugt `http://`-Links.

---

## Skalierung

Die Anwendung ist zustandslos: Sitzungen und Cache liegen in Postgres,
hochgeladene Dateien gibt es (noch) nicht. Mehrere Instanzen können ohne
klebrige Sitzungen hinter demselben Loadbalancer laufen.

Was dabei zu beachten ist:

- **Verbindungen.** FrankenPHP startet je Instanz mehrere PHP-Threads, jeder
  mit eigener Datenbankverbindung. Anzahl Threads × Instanzen muss unter
  `max_connections` der Datenbank bleiben. Die Zahl steht beim Start im Log
  (`num_threads`).
- **Migrationen.** Laufen beim Start jeder Instanz, sind aber mit `--isolated`
  gesperrt — gleichzeitiger Start ist unkritisch.
- **Uploads.** Kommen später Dateien dazu, gehören sie in einen gemeinsamen
  Speicher (S3-kompatibel, `FILESYSTEM_DISK=s3`), nicht in den Container.

---

## Strenge Content-Security-Policy einschalten

Standardmäßig setzt die Anwendung keine CSP. Sie fehlt bewusst — die
Oberfläche benutzt htmx-Attribute wie `hx-on::after-request` und einige
Inline-Skripte, die eine CSP ohne `unsafe-inline`/`unsafe-eval` blockieren würde.

Wer eine CSP braucht, geht so vor:

1. Inline-Skripte aus `layouts/base.blade.php`, `layouts/app.blade.php`,
   `pages/styleguide.blade.php`, `admin/user.blade.php` und
   `partials/token-created.blade.php` in Dateien unter `public/js/` auslagern.
2. `onclick`-Attribute (Offline- und Fehlerseiten) durch Ereignis-Listener ersetzen.
3. `hx-on::…` durch `htmx.on(...)` in einer eigenen Datei ersetzen und
   `htmx.config.allowEval = false` setzen.
4. Eine Middleware ergänzen, die den Kopf setzt, und in `bootstrap/app.php`
   an die `web`-Gruppe hängen:

```php
$response->headers->set('Content-Security-Policy',
    "default-src 'self'; script-src 'self'; style-src 'self'; ".
    "img-src 'self' data:; frame-ancestors 'none'; base-uri 'self'");
```

Weitere lohnende Kopfzeilen: `X-Frame-Options: DENY`,
`Referrer-Policy: strict-origin-when-cross-origin`,
`Strict-Transport-Security` (besser im Proxy).

---

## Betrieb

**Logs** gehen nach stderr (`LOG_CHANNEL=stderr`) — `docker logs`,
`journalctl` oder der Log-Sammler der Plattform.

```bash
LOG_LEVEL=debug     # vorübergehend ausführlicher
```

**Zustand prüfen:**

```bash
curl -fsS https://app.example.com/api/v1/health
```

**Konsole im laufenden Container:**

```bash
docker exec -it starter-php php artisan tinker
```

**Sicherung** — die Anwendung selbst hält keinen Zustand, es genügt die
Datenbank:

```bash
pg_dump "postgres://starter:…@db.example.com/starter" --format=custom --file=sicherung-$(date +%F).dump
```

Wiederherstellen mit `pg_restore`. Mindestens einmal geübt haben.

---

## Aktualisieren

```bash
docker build -t starter-php:neu .
docker stop starter-php && docker rm starter-php
docker run -d --name starter-php … starter-php:neu
```

Migrationen laufen beim Start automatisch. Bei mehreren Instanzen erst eine
umstellen, Zustand prüfen, dann die übrigen.

**Rückwärtskompatibel migrieren.** Während einer rollenden Umstellung laufen
alte und neue Version gleichzeitig. Eine Spalte erst hinzufügen und befüllen,
sie erst in einer späteren Version entfernen — nie beides in einem Schritt.
