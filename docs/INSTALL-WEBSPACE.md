# Installation auf einem Webspace (nur FTP)

Für gemietete Webspaces **ohne Kommandozeile auf dem Server**: Du lädst per FTP
hoch, PHP und eine **PostgreSQL** stellt der Hoster. Alles, was sonst auf dem
Server laufen würde, erledigt `deploy.sh` auf deinem Rechner. Nach der
einmaligen Einrichtung ist jedes Update ein einziger Befehl:

```bash
./deploy.sh
```

Andere Wege: [mit Docker](../README.md#installation-auf-einem-server),
[Webserver mit SSH](INSTALL-WEBSERVER.md).

---

## So funktioniert es

`deploy.sh` macht auf deinem Rechner:

1. **Prüfen:** `just check` und ob alles committet ist. Hochgeladen wird immer
   der letzte Commit, nie halbfertige Änderungen.
2. **Bauen:** den Commit in einem leeren Ordner – PHP-Pakete ohne
   Entwicklungswerkzeuge, Stylesheet.
3. **Hochladen:** per **FTPS** (verschlüsseltes FTP) mit `lftp`. Gelöschte
   Dateien verschwinden auch auf dem Server. Logs, Zwischenspeicher und die
   `.env` des Servers bleiben unangetastet.
4. **Migrieren:** ruft `https://…/api/v1/deploy` auf. Der Server prüft PHP,
   Erweiterungen, Schreibrechte und Datenbank, und nur wenn alles passt,
   bringt er die Datenbank auf den neuen Stand. Geschützt ist der Aufruf
   durch ein langes Geheimnis (`DEPLOY_HOOK_TOKEN`). Ohne dieses Geheimnis
   gibt es die Adresse nicht.
5. **Abschlussprüfung:** `https://…/api/v1/health`.

---

## Was du brauchst

**Beim Hoster:**

| | |
|---|---|
| PHP | **8.4.1 oder neuer**, im Kundenmenü einstellbar; mit `pdo_pgsql`, `intl`, `mbstring`, `xml`, `curl` und Argon2; freiwillig `zip` (Export/Import mehrerer Stapel als ZIP) |
| Datenbank | eine **PostgreSQL** (Name, Benutzer, Passwort, Rechner aus dem Kundenmenü) |
| FTP | Zugang, am besten mit FTPS |
| Domain | eine eigene Adresse (Domain oder Subdomain) mit HTTPS, z. B. `cardbox.example.com`. Ihr Zielordner liegt meist unter `public_html`. Ein Unterpfad wie `example.com/cardbox` geht **nicht** |
| Webserver | Apache oder LiteSpeed mit `.htaccess` und `mod_rewrite` (bei Webspaces üblich) |

Ob PHP passt, zeigt dir der erste Deploy (Schritt 4 oben), du musst es nicht
vorher herausfinden.

**Auf deinem Rechner:** `git`, `php`, [Composer](https://getcomposer.org),
`curl`, `lftp` (`brew install lftp` bzw. `apt install lftp`) und für die
Prüfung vor dem Hochladen [just](https://github.com/casey/just) samt
Entwicklungsumgebung (siehe README, „Entwickeln“).

---

## Einmalig einrichten

### 1. Domain auf den Ordner zeigen lassen

Im Kundenmenü des Hosters eine Domain oder Subdomain (z. B.
`cardbox.example.com`) anlegen. Ihr Zielordner ist der Ordner, in den
`deploy.sh` hochlädt (`FTP_DIR`), z. B. **`public_html/cardbox`**. HTTPS
einschalten.

So sieht der Ordner nach dem ersten Deploy aus:

```
public_html/cardbox/       ← FTP_DIR, hierauf zeigt die Domain
├── .htaccess              ← reicht jede Anfrage nach public/ weiter
├── .env                   ← nie abrufbar
├── app/  bootstrap/  config/  vendor/  …
├── storage/               ← Logs, Sitzungen (beschreibbar)
└── public/                ← das Einzige, was der Browser sieht
    ├── index.php
    └── css/  js/  icons/
```

Die `.htaccess` in `FTP_DIR` macht aus `/login` intern `public/login` und aus
`/.env` ein `public/.env`, das es nicht gibt. Erreichbar ist also nur, was
in `public/` liegt. Kann der Hoster die Domain auf `…/public` zeigen lassen,
geht das genauso.

> Cardbox braucht eine eigene Adresse. Ein Unterpfad der Hauptdomain
> (`example.com/cardbox`, weil `public_html` die Hauptdomain ist und Cardbox
> darin liegt) funktioniert nicht: Alle Links beginnen bei `/`. Dafür eine
> Subdomain anlegen, die auf `public_html/cardbox` zeigt.

> Die `.htaccess` wirkt nur bei Apache und LiteSpeed (die meisten
> Webspaces). Deshalb prüft `deploy.sh` am Ende zusätzlich, dass `/.env`
> nicht abrufbar ist, und schlägt sonst Alarm.

### 2. Die `.env` für den Server

Im Projektordner auf deinem Rechner:

```bash
cp .env.example .env.production
```

`.env.production` wird bei jedem Deploy als `.env` auf den Server geladen und
nie committet. Diese Werte setzen:

| Eintrag | Wert |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Ausgabe von `echo "base64:$(openssl rand -base64 32)"` – **einmal** erzeugen, danach nie ändern |
| `APP_URL` | `https://cardbox.example.com` |
| `APP_NAME`, `APP_SHORT_NAME` | Name der App, mit Leerzeichen in Anführungszeichen: `"Cardbox Schule"` |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | aus dem Kundenmenü |
| `DB_SCHEMA` | Schema für die Tabellen, Standard `public`. Gibt der Hoster ein Schema vor oder teilen sich mehrere Anwendungen eine Datenbank, hier den Namen eintragen. Fehlt das Schema, legt der erste Deploy es an, sofern das Datenbankkonto das darf |
| `DB_SSLMODE` | `require`, wenn die Datenbank auf einem anderen Rechner liegt; sonst `prefer` |
| `SESSION_SECURE_COOKIE` | `true` |
| `TRUST_PROXY` | `false`. `true` nur, wenn der Hoster einen Proxy davorschaltet **und** der Server ausschließlich über diesen erreichbar ist. Sonst kann jeder die Anmeldebremse pro IP umgehen. Im Zweifel `false` |
| `LOG_CHANNEL` | `daily` |
| `LEARNING_TIMEZONE` | `Europe/Berlin` |
| `DEPLOY_HOOK_TOKEN` | Ausgabe von `openssl rand -hex 32` |

### 3. Die Einstellungen für `deploy.sh`

```bash
cp .env.deploy.example .env.deploy
```

`.env.deploy` öffnen und ausfüllen. Die Datei bleibt auf deinem Rechner, sie
wird nie committet und nie hochgeladen.

| Eintrag | Wert |
|---|---|
| `FTP_HOST`, `FTP_PORT`, `FTP_USER` | FTP-Zugang vom Hoster |
| `FTP_PASS` | leer lassen, dann fragt `deploy.sh` jedes Mal ohne Anzeige |
| `FTP_DIR` | Zielordner, so wie das FTP-Programm ihn nach der Anmeldung zeigt, z. B. `public_html/cardbox`. Die Domain zeigt auf diesen Ordner. `deploy.sh` prüft das nach dem Hochladen mit einer Testdatei |
| `FTP_SSL` | `true`. Nur auf `false`, wenn der Hoster kein FTPS kann – dann reisen Passwort und `.env` unverschlüsselt |
| `DEPLOY_URL` | `https://cardbox.example.com` |
| `DEPLOY_HOOK_TOKEN` | **derselbe Wert** wie in `.env.production` |

### 4. Erster Deploy

```bash
./deploy.sh
```

Am Ende steht eine Liste mit ✓ und ✗:

```
  ✓ php                      8.5.0 (nötig: 8.4.1 oder neuer)
  ✓ argon2                   vorhanden
  ✓ ext-pdo_pgsql            geladen
  …
  ✓ database                 erreichbar
```

Steht irgendwo ✗, beim Hoster beheben (PHP-Version, Erweiterungen, Zugangsdaten) und `./deploy.sh` erneut ausführen.

### 5. Erstes Konto

`https://cardbox.example.com/register` öffnen. **Das erste Konto wird
Administrator.** Weitere Konten melden sich selbst an und warten unter
**Mehr → Benutzer** auf Freigabe.

---

## Erinnerungen einrichten (freiwillig)

Wer möchte, bekommt jeden Tag eine Mitteilung aufs Handy („12 Karten warten
auf dich“). Auf dem Webspace läuft kein Hintergrundprozess, deshalb ruft ein
Dienst von außen alle 15 Minuten eine geschützte Adresse auf.

1. Lokal Schlüssel erzeugen und in die `.env` auf dem Webspace eintragen:

   ```bash
   php artisan push:keys
   ```

   Dazu `VAPID_SUBJECT="mailto:admin@example.com"` und ein Token:
   `REMINDER_TOKEN=` mit der Ausgabe von `openssl rand -hex 32`.

2. Einen Aufrufer einrichten, z. B. **Cronicle** (auf einer NAS) oder
   cron-job.org:

   | Feld | Wert |
   |---|---|
   | Plugin / Art | HTTP Request |
   | Methode | `POST` |
   | URL | `https://cardbox.example.com/api/v1/reminders/run` |
   | Kopfzeile | `X-Cron-Token: <REMINDER_TOKEN>` |
   | Zeitplan | alle 15 Minuten |
   | Zeitlimit | 60 Sekunden |

   Antwort bei Erfolg: `{"sent": 3, "skipped": 5, "removed": 0}`. Ein 404
   heißt: Token fehlt, ist kürzer als 32 Zeichen oder falsch.

3. Unter **Mehr → System** steht danach, wann zuletzt verschickt wurde.

Der Kopf heißt bewusst `X-Cron-Token` statt `Authorization`, weil PHP über
FastCGI den `Authorization`-Kopf oft verliert. Die Mitteilungen laufen über
die Push-Dienste der Browser-Hersteller (Apple, Google, Mozilla); übertragen
werden nur die Kartenzahl und die Lernserie, verschlüsselt.

---

## Aktualisieren

```bash
git pull
./deploy.sh
```

Was neu ist, steht in [CHANGELOG.md](../CHANGELOG.md). Vorher eine Sicherung der Datenbank machen.
Die meisten Hoster bieten dafür im Kundenmenü einen Export (pg_dump) an.

Willst du nur die `.env` auf dem Server ändern: `.env.production` anpassen und
`./deploy.sh` erneut ausführen.

---

## Wenn etwas klemmt

| Meldung / Problem | Lösung |
|---|---|
| „Es gibt nicht committete Änderungen“ | erst committen – hochgeladen wird nur der letzte Commit |
| „just check ist rot“ | `just check` ansehen und beheben |
| „Hochladen fehlgeschlagen“ mit Zertifikatsfehler | Das FTPS-Zertifikat lautet oft auf den Servernamen des Hosters (z. B. `s123.hoster.de`), nicht auf deine Domain. Diesen Namen als `FTP_HOST` eintragen. |
| „Hochladen fehlgeschlagen“, Hoster kann gar kein FTPS | Besser den Hoster wechseln oder um FTPS bitten. Notfalls `FTP_SSL=false` – **dann reisen FTP-Passwort und die ganze `.env` samt `APP_KEY` und Datenbank-Passwort unverschlüsselt durchs Netz.** |
| „… ist abrufbar!“ | Der Server beachtet die `.htaccess` in `FTP_DIR` nicht. Sofort die Domain auf `…/public` stellen oder die Dateien per FTP löschen, dann `APP_KEY`, Datenbank-Passwort und `DEPLOY_HOOK_TOKEN` erneuern. |
| „Deploy-Hook antwortet 404: Cardbox läuft, aber das Token passt nicht“ | `DEPLOY_HOOK_TOKEN` in `.env.production` und `.env.deploy` gleich? Mindestens 32 Zeichen? |
| „Die Domain zeigt nicht auf den Upload-Ordner“ | Im Kundenmenü den Zielordner der Domain nachsehen. `FTP_DIR` ist der Weg dorthin **vom Startordner des FTP-Kontos aus**: Startet das FTP-Programm schon in `public_html`, heißt es `FTP_DIR=cards`, nicht `public_html/cards`. Neue Subdomains brauchen manchmal einige Minuten |
| „… antwortet 404, aber nicht von Cardbox“ | Die Adresse erreicht Cardbox nicht: Zielordner der Domain = `FTP_DIR`? `DEPLOY_URL` richtig geschrieben? Ist die Subdomain beim Hoster schon aktiv? |
| ✗ bei `php` oder `ext-…` | im Kundenmenü PHP 8.4+ wählen bzw. Erweiterungen einschalten |
| ✗ bei `database` | Zugangsdaten in `.env.production`. Liegt die Datenbank woanders, `DB_SSLMODE=require` probieren |
| ✗ bei `database-schema` | `DB_SCHEMA` ist kein gültiger Name, oder das Schema fehlt und das Datenbankkonto darf keins anlegen – dann im Kundenmenü anlegen bzw. den Hoster fragen |
| ✗ bei `writable-…` | im FTP-Programm `storage/` und `bootstrap/cache/` beschreibbar machen (Rechte 775) |
| 403 auf der ganzen Seite | Der Server hat kein `mod_rewrite`; dann sperrt die `.htaccess` alles. Den Hoster fragen oder die Domain auf `…/public` stellen |
| Startseite geht, Links landen auf der Hauptdomain bzw. 404 | Cardbox liegt in einem Unterpfad (`example.com/cardbox`). Eine Subdomain auf `public_html/cardbox` anlegen und als `APP_URL` und `DEPLOY_URL` eintragen |
| Testmail unter **System**: „535 Incorrect authentication data“ | Der Mailserver lehnt Benutzer oder Passwort ab. Häufigste Ursache: Das Passwort enthält `#` und steht ohne Anführungszeichen in `.env.production` – Laravel liest dann nur den Teil davor. Richtig: `MAIL_PASSWORD='…'` (seit 2026.10.13.5 bricht `deploy.sh` in diesem Fall ab). Sonst: `MAIL_USERNAME` ist die volle Adresse, das Passwort ist das des Postfachs (nicht das des Kundenmenüs), und das Postfach muss ein echtes Postfach sein, keine Weiterleitung |
| Anmeldung klappt nie | Läuft die Seite über HTTPS? Mit `SESSION_SECURE_COOKIE=true` setzen Browser das Cookie nur über HTTPS |
| Fehlerseite | Log unter `<FTP_DIR>/storage/logs/` per FTP herunterladen |

## Ohne Kommandozeile – was fehlt

- **Kein Wartungsmodus.** Zwischen Hochladen und Migrieren können für ein paar
  Sekunden neuer Code und alte Datenbank zusammentreffen. Updates am besten zu
  ruhigen Zeiten.
- **Kein Cronjob nötig.** Alte Einträge der Anmeldebremse räumt jeder Deploy
  weg.
- **Kein Passwort-Rücksetzen über die Konsole.** Hat der einzige
  Administrator sein Passwort vergessen, gibt es ohne Kommandozeile keinen
  einfachen Weg zurück. Deshalb **gleich zu Beginn unter Mehr → Benutzer ein
  zweites Administratorkonto anlegen**. Die beiden können sich gegenseitig
  ein neues Passwort setzen.
