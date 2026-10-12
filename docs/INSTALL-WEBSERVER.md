# Installation auf einem Webserver (ohne Container)

Für Server, auf denen schon **nginx oder Apache mit PHP** läuft und eine
**PostgreSQL vorhanden** ist. Ohne Docker, ohne Node.

Der Weg mit Docker steht in der [README](../README.md#installation-auf-einem-server).
Alle Einstellungen im Detail: [DEPLOY.md](DEPLOY.md).

Die Beispiele gehen von Debian oder Ubuntu, nginx, PHP 8.5 und dem Ordner
`/var/www/cardbox` aus. Auf anderen Systemen heißen Pakete und Pfade etwas
anders, die Schritte bleiben gleich.

---

## Was du brauchst

| | |
|---|---|
| Zugang | SSH mit `sudo` |
| Webserver | nginx (oder Apache, siehe unten) |
| PHP | **8.4 oder neuer** als PHP-FPM, empfohlen 8.5; mit den Erweiterungen `pgsql`, `intl`, `mbstring`, `xml`, `curl`, `zip` und Argon2-Unterstützung |
| Werkzeuge | `git`, `curl`, `unzip`, [Composer 2](https://getcomposer.org/download/) |
| Datenbank | eine vorhandene **PostgreSQL 13 oder neuer** (getestet mit 17), auf demselben oder einem anderen Rechner |
| Adresse | z. B. `cardbox.example.com`, DNS zeigt auf den Server |

Kein Node, kein npm. Das Stylesheet baut ein einzelnes Programm, das das
Installationsskript selbst herunterlädt.

**PHP auf Debian/Ubuntu**, falls noch nicht vorhanden. PHP 8.5 gibt es über die
Paketquellen von [sury.org](https://deb.sury.org) (Debian) bzw. `ppa:ondrej/php`
(Ubuntu):

```bash
sudo apt install nginx git curl unzip php8.5-fpm php8.5-pgsql php8.5-intl php8.5-mbstring php8.5-xml php8.5-curl php8.5-zip
```

Prüfen, ob PHP passt: Die beiden Befehle zeigen die Version und
`bool(true)` für Argon2.

```bash
php -v
```

```bash
php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'
```

---

## 1. Datenbank anlegen

In der vorhandenen PostgreSQL ein eigenes Konto und eine eigene Datenbank für
Cardbox anlegen. Als Datenbank-Administrator, z. B. `sudo -u postgres psql`:

```sql
CREATE ROLE cardbox LOGIN PASSWORD 'hier-ein-langes-passwort';
CREATE DATABASE cardbox OWNER cardbox ENCODING 'UTF8';
```

Ein gutes Passwort erzeugt `openssl rand -hex 24`.

**Eigenes Schema statt `public`** – etwa wenn sich mehrere Anwendungen eine
Datenbank teilen: Schema anlegen und in `.env` `DB_SCHEMA=cardbox` setzen.

```sql
CREATE SCHEMA cardbox AUTHORIZATION cardbox;
```

**Läuft die Datenbank auf einem anderen Rechner**, muss sie Verbindungen vom
Webserver annehmen (`listen_addresses` in `postgresql.conf` und ein Eintrag in
`pg_hba.conf`). Die Verbindung sollte verschlüsselt sein, dazu später in
`.env` `DB_SSLMODE=require` setzen.

---

## 2. Benutzer und Ordner

Cardbox gehört einem eigenen Benutzer. PHP-FPM läuft als `www-data` und darf
nur in `storage/` und `bootstrap/cache/` schreiben.

```bash
sudo adduser --disabled-password --gecos "" cardbox
sudo usermod -aG www-data cardbox
sudo mkdir -p /var/www/cardbox
sudo chown cardbox:www-data /var/www/cardbox
```

Ab hier als dieser Benutzer arbeiten:

```bash
sudo -iu cardbox
```

```bash
git clone https://github.com/kleineLoesungen/cardbox.git /var/www/cardbox
cd /var/www/cardbox
```

---

## 3. Einstellungen

```bash
cp .env.example .env
chmod 640 .env
```

`.env` öffnen (z. B. `nano .env`) und diese Werte setzen:

| Eintrag | Wert |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://cardbox.example.com` |
| `APP_NAME`, `APP_SHORT_NAME` | Name der App, z. B. `Cardbox`. Mit Leerzeichen in Anführungszeichen: `"Cardbox Schule"` |
| `DB_HOST` | Rechner der Datenbank, z. B. `127.0.0.1` |
| `DB_PORT` | `5432` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | aus Schritt 1 |
| `DB_SCHEMA` | `public`, oder das eigene Schema aus Schritt 1 |
| `DB_SSLMODE` | `require`, wenn die Datenbank auf einem anderen Rechner liegt; sonst `prefer` |
| `SESSION_SECURE_COOKIE` | `true` |
| `TRUST_PROXY` | `false` – **außer** vor nginx steht noch ein weiterer Proxy oder Loadbalancer, dann `true`. In diesem Fall per Firewall sicherstellen, dass der Server **nur** über den Proxy erreichbar ist, sonst kann jeder die Anmeldebremse pro IP umgehen |
| `LOG_CHANNEL` | `daily` (Logs unter `storage/logs/`, eine Datei pro Tag) |
| `LOG_LEVEL` | `info` |
| `LEARNING_TIMEZONE` | `Europe/Berlin` |

`APP_KEY` leer lassen, das Skript erzeugt ihn. Alles andere kann bleiben,
wie es ist. E-Mail ist nicht nötig, Cardbox schickt Benutzern keine Mails.

> Werte mit `#`, Leerzeichen oder `<` `>` brauchen Anführungszeichen,
> z. B. `PWA_THEME_COLOR="#3370c7"`.

---

## 4. Installieren

```bash
./scripts/deploy-webserver.sh
```

Das Skript erledigt in dieser Reihenfolge:
1. **Prüfen:** PHP-Version, Erweiterungen und Argon2, außerdem `APP_ENV` und `APP_DEBUG` in `.env`.
2. **Abhängigkeiten:** PHP-Pakete ohne Entwicklungswerkzeuge installieren.
3. **Stylesheet:** das Tailwind-Programm laden und das Stylesheet bauen.
4. **Schlüssel:** `APP_KEY` erzeugen, falls er noch fehlt. **Den Schlüssel
   danach aus `.env` sicher aufheben.** Ein neuer Schlüssel meldet alle ab.
5. **Datenbank:** Tabellen anlegen bzw. aktualisieren und alte Einträge der
   Anmeldebremse entfernen.
6. **Vorbereiten:** Konfiguration, Routen und Ansichten für schnelle Antworten
   vorkompilieren.

Bricht es ab, steht in der letzten Zeile, was fehlt.

---

## 5. Rechte

Wieder mit `sudo` (also `exit` aus dem `cardbox`-Benutzer):

```bash
cd /var/www/cardbox
sudo chown -R cardbox:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod g+s {} +
```

`g+s` sorgt dafür, dass neue Dateien und Ordner der Gruppe `www-data` gehören.
So können PHP-FPM und der Benutzer `cardbox` beide darin schreiben.

---

## 6. nginx

`/etc/nginx/sites-available/cardbox`:

```nginx
server {
    listen 80;
    server_name cardbox.example.com;

    root /var/www/cardbox/public;
    index index.php;
    charset utf-8;

    # "Liste einfügen" nimmt Dateien bis 1 MB an.
    client_max_body_size 2m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Stylesheet, htmx und Icons dürfen lange im Browser-Cache liegen.
    location ~ ^/(css|js|icons)/ {
        expires 7d;
        add_header Cache-Control "public";
        try_files $uri =404;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.5-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Keine versteckten Dateien ausliefern (.env, .git …).
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Einschalten und HTTPS holen (Let's Encrypt mit certbot):

```bash
sudo ln -s /etc/nginx/sites-available/cardbox /etc/nginx/sites-enabled/
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

```bash
sudo apt install certbot python3-certbot-nginx && sudo certbot --nginx -d cardbox.example.com
```

certbot ergänzt die HTTPS-Einstellungen in der Datei selbst und erneuert das
Zertifikat automatisch.

**Wichtig:** `root` zeigt auf `public/`, nie auf den Projektordner. Sonst wären
`.env` und der Quellcode über das Netz abrufbar.

### Apache statt nginx

```apache
<VirtualHost *:80>
    ServerName cardbox.example.com
    DocumentRoot /var/www/cardbox/public

    <Directory /var/www/cardbox/public>
        AllowOverride All
        Require all granted
    </Directory>

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.5-fpm.sock|fcgi://localhost"
    </FilesMatch>
</VirtualHost>
```

Dazu `sudo a2enmod rewrite proxy_fcgi setenvif`. Die Weiterleitungen stehen in
`public/.htaccess`. HTTPS dann mit `sudo certbot --apache -d cardbox.example.com`.

---

## 7. PHP-Einstellungen

Die Vorgaben von PHP passen meist. Empfohlen in der `php.ini` von PHP-FPM
(z. B. `/etc/php/8.5/fpm/conf.d/99-cardbox.ini`):

```ini
expose_php = Off
memory_limit = 256M
upload_max_filesize = 2M
post_max_size = 8M
opcache.enable = 1
```

Danach `sudo systemctl reload php8.5-fpm`.

---

## 8. Regelmäßige Aufgaben

Der Container räumt beim Start die Anmeldebremse auf. Auf dem Webserver
übernimmt das ein Cronjob. Als Benutzer `cardbox` mit `crontab -e`:

```cron
# Alte Einträge der Anmeldebremse entfernen, täglich 3:15 Uhr
15 3 * * * cd /var/www/cardbox && php artisan login-attempts:prune >/dev/null 2>&1
```

---

## 9. Erstes Konto

`https://cardbox.example.com/register` öffnen. **Das erste Konto wird
Administrator** und ist sofort nutzbar. Weitere Konten melden sich selbst an
und warten unter **Mehr → Benutzer** auf Freigabe.

Prüfen:

```bash
curl https://cardbox.example.com/api/v1/health
```

Antwort: `{"status":"ok","database":"ok"}`.

---

## Aktualisieren

Vorher in [CHANGELOG.md](../CHANGELOG.md) nachlesen und eine Sicherung machen.
Als Benutzer `cardbox`:

```bash
cd /var/www/cardbox
php artisan down --retry=30
git pull
./scripts/deploy-webserver.sh
php artisan up
```

`down`/`up` zeigt Besuchern während des Updates eine Wartungsseite. Danach PHP-FPM neu
laden, damit der neue Code sicher aktiv ist:

```bash
sudo systemctl reload php8.5-fpm
```

---

## Sicherung

Alle Daten liegen in der Datenbank. Eine Sicherung als Datei:

```bash
pg_dump "postgresql://cardbox@127.0.0.1:5432/cardbox" --format=custom --file=sicherung-$(date +%F).dump
```

Das Passwort fragt `pg_dump` ab. Für eine Sicherung per Cronjob gehört es
in `~/.pgpass` des Benutzers (eine Zeile `127.0.0.1:5432:cardbox:cardbox:PASSWORT`,
Rechte `600`):

```cron
30 2 * * * pg_dump "postgresql://cardbox@127.0.0.1:5432/cardbox" --format=custom --file=$HOME/sicherung-$(date +\%F).dump
```

Zurückspielen (überschreibt den aktuellen Stand):

```bash
pg_restore --clean --if-exists -d "postgresql://cardbox@127.0.0.1:5432/cardbox" sicherung-2026-10-11.dump
```

Sicherungen auf einen anderen Rechner kopieren und das Zurückspielen einmal
ausprobieren. Zusätzlich `.env` sichern, vor allem wegen `APP_KEY`.

---

## Wenn etwas klemmt

| Problem | Lösung |
|---|---|
| Weiße Seite oder „Server Error“ | `storage/logs/laravel-*.log` lesen. |
| „Permission denied“ in `storage` | Schritt 5 wiederholen. |
| `502 Bad Gateway` | Läuft PHP-FPM (`systemctl status php8.5-fpm`)? Stimmt der Socket-Pfad in der nginx-Datei? |
| „could not find driver“ | `php8.5-pgsql` fehlt, danach PHP-FPM neu laden. |
| Anmeldung klappt nie, man landet wieder beim Login | Läuft die Seite wirklich über HTTPS? Mit `SESSION_SECURE_COOKIE=true` setzen Browser das Cookie nur über HTTPS. |
| Alle Anmeldungen werden gemeinsam gesperrt | Steht ein Proxy vor nginx? Dann `TRUST_PROXY=true`, sonst `false`. |
| Änderung in `.env` wirkt nicht | `php artisan config:cache` (oder das Skript erneut laufen lassen). |
| Einziger Administrator hat sein Passwort vergessen | `php artisan tinker`, dann `App\Models\User::where('username', 'anna')->first()->update(['password' => 'ein-neues-langes-passwort']);` |

## Webspace ohne SSH

Reiner Webspace ohne SSH-Zugang reicht nicht. Cardbox braucht Composer,
PHP 8.4+ mit `pdo_pgsql` und Argon2 sowie einen Cronjob, und der Ordner
`public/` muss das Wurzelverzeichnis der Website sein.
