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
| PHP | **8.4.1 oder neuer**, im Kundenmenü einstellbar; mit `pdo_pgsql`, `intl`, `mbstring`, `xml`, `curl` und Argon2 |
| Datenbank | eine **PostgreSQL** (Name, Benutzer, Passwort, Rechner aus dem Kundenmenü) |
| FTP | Zugang, am besten mit FTPS |
| Domain | eine Adresse, deren **Zielordner frei wählbar** ist (für `…/public`), mit HTTPS (z. B. Let's Encrypt im Kundenmenü) |

Ob PHP passt, zeigt dir der erste Deploy (Schritt 4 oben), du musst es nicht
vorher herausfinden.

**Auf deinem Rechner:** `git`, `php`, [Composer](https://getcomposer.org),
`curl`, `lftp` (`brew install lftp` bzw. `apt install lftp`) und für die
Prüfung vor dem Hochladen [just](https://github.com/casey/just) samt
Entwicklungsumgebung (siehe README, „Entwickeln“).

---

## Einmalig einrichten

### 1. Domain auf den Ordner `public` zeigen lassen

Im Kundenmenü des Hosters die Domain (z. B. `cardbox.example.com`) auf den
Ordner **`cardbox/public`** zeigen lassen und HTTPS einschalten. `cardbox` ist
der Ordner, in den `deploy.sh` hochlädt (`FTP_DIR`).

> Zeigt die Domain stattdessen auf `cardbox/`, verweigert eine `.htaccess` dort
> jeden Zugriff (403). So kann niemand `.env` oder den Code herunterladen. Die
> Lösung ist dann immer: Zielordner auf `…/public` stellen. Die `.htaccess`
> wirkt nur bei Apache und LiteSpeed (die meisten Webspaces). Deshalb prüft
> `deploy.sh` am Ende zusätzlich, dass `/.env` nicht abrufbar ist, und
> schlägt sonst Alarm.

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
| `FTP_DIR` | Zielordner, z. B. `cardbox` (die Domain zeigt auf `cardbox/public`) |
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
| „… ist abrufbar!“ | Die Domain zeigt nicht auf `…/public`, und der Server beachtet die schützende `.htaccess` nicht. Sofort im Kundenmenü korrigieren und `APP_KEY`, Datenbank-Passwort und `DEPLOY_HOOK_TOKEN` erneuern. |
| „Deploy-Hook antwortet 404“ | `DEPLOY_HOOK_TOKEN` in `.env.production` und `.env.deploy` gleich? Mindestens 32 Zeichen? |
| ✗ bei `php` oder `ext-…` | im Kundenmenü PHP 8.4+ wählen bzw. Erweiterungen einschalten |
| ✗ bei `database` | Zugangsdaten in `.env.production`. Liegt die Datenbank woanders, `DB_SSLMODE=require` probieren |
| ✗ bei `database-schema` | `DB_SCHEMA` ist kein gültiger Name, oder das Schema fehlt und das Datenbankkonto darf keins anlegen – dann im Kundenmenü anlegen bzw. den Hoster fragen |
| ✗ bei `writable-…` | im FTP-Programm `storage/` und `bootstrap/cache/` beschreibbar machen (Rechte 775) |
| 403 auf der ganzen Seite | Die Domain zeigt auf `cardbox/` statt auf `cardbox/public` |
| Anmeldung klappt nie | Läuft die Seite über HTTPS? Mit `SESSION_SECURE_COOKIE=true` setzen Browser das Cookie nur über HTTPS |
| Fehlerseite | Log unter `cardbox/storage/logs/` per FTP herunterladen |

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
