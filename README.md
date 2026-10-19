# Cardbox — Vokabeln lernen mit dem Karteikasten

Cardbox ist ein digitaler Karteikasten fürs **Smartphone**. Man legt Stapel mit
Vokabelkarten an oder übernimmt geteilte Stapel, und die App fragt jeden Tag die
Karten ab, die gerade dran sind. Was sitzt, wandert ein Fach weiter und kommt
seltener. Was nicht sitzt, kommt bald wieder.

Gedacht für **Schülerinnen und Schüler ab 10 Jahren** und für **Erwachsene ohne
viel Technikerfahrung**:
- große Knöpfe im Daumenbereich
- einfache Sprache
- eine Hauptaktion pro Bildschirm

Cardbox ist der Neuaufbau von
[vocaBox](https://github.com/kleineLoesungen/vocaBox), jetzt mit PHP, Laravel und
htmx statt Nuxt.

---

## Stand

| Bereich | Stand | Spezifikation |
|---|---|---|
| Konten: Anmeldung mit Benutzername, Registrierung mit Freigabe | ✅ fertig | [konten-ohne-email.md](docs/specs/konten-ohne-email.md) |
| Stapel und Karten: anlegen, Liste einfügen, folgen, kopieren | ✅ fertig | [vokabelkarten.md](docs/specs/vokabelkarten.md) |
| Lernen: Karteikasten, Lernrunde, freies Üben, Lernserie | ✅ fertig | [vokabelkarten.md](docs/specs/vokabelkarten.md) |
| Feinschliff: Lernplan, Hilfe, Vorlesen, Wischgesten | ✅ fertig | [vokabelkarten.md](docs/specs/vokabelkarten.md) |
| Einfacher lernen: „Los geht's“ über alle Stapel, Für den Test üben | ✅ fertig | [einfacher-lernen.md](docs/specs/einfacher-lernen.md) |
| Motivation: neue Wörter zuerst zeigen, Tagesziel, Wortschatz | ✅ fertig | [motivierender-start.md](docs/specs/motivierender-start.md) |
| Antworten eintippen, tägliche Erinnerung | ✅ fertig | [schreiben-und-erinnern.md](docs/specs/schreiben-und-erinnern.md) |
| Karten je Runde, knifflige Wörter | ✅ fertig | [runden-und-knifflige-woerter.md](docs/specs/runden-und-knifflige-woerter.md) |
| Eltern sehen den Fortschritt | ✅ fertig | [eltern-ansicht.md](docs/specs/eltern-ansicht.md) |
| Stapel pausieren, Karten „Vorderseite → Rückseite“ | ✅ fertig | [aktive-stapel-und-freie-karten.md](docs/specs/aktive-stapel-und-freie-karten.md) |

Alle drei Stufen der Spezifikation sind umgesetzt. Offen ist ein Test auf
echten Telefonen.

---

## So funktioniert es

**Konten.** Jede Person kann sich unter `/register` ein Konto anlegen und braucht
dafür nur einen Namen, einen Benutzernamen und ein Passwort, keine E-Mail-Adresse.
Nutzbar wird das Konto erst, wenn eine Administratorin oder ein Administrator es
unter **Benutzer** freigibt. Administratoren können Konten auch direkt anlegen.
Ein Knopf schlägt merkbare Passwörter vor, z. B. `Wolke-Tiger-Apfel-47`.

**Stapel.** Ein Stapel hat eine Sprache vorne und eine hinten, z. B. Deutsch →
Englisch – oder „Aufgabe → Ergebnis“ für Mathe und „Vorderseite → Rückseite“
für alles andere. Wer einen Stapel gerade nicht lernen will, pausiert ihn; er
kommt dann nicht auf **Heute**. Er ist entweder privat oder mit allen geteilt. Für einen geteilten
Stapel einer anderen Person gibt es zwei Wege:
- **Folgen:** lernen ja, ändern nein. Korrekturen der Besitzerin kommen sofort an.
- **Kopieren:** Man bekommt eine eigene Fassung zum Ändern, der Lernstand wird
  mitgenommen.

**Lernen.** Der Karteikasten hat fünf Fächer, die Abstände sind 1, 2, 4 und 7 Tage.
- Gewusst: Die Karte rückt ein Fach weiter.
- Nicht gewusst: Die Karte geht zurück in Fach 1 und kommt am Ende der Runde
  noch einmal.

Gelernt wird in beide Richtungen getrennt. **Heute → „Los geht's“** nimmt die
fälligen Karten aller Stapel und ein paar neue. Neue Wörter zeigt die App zuerst
offen und fragt sie ein paar Karten später ab. In der Runde kann man wischen
(rechts gewusst, links nochmal) und sich die Karte vorlesen lassen. Im Stapel
lässt sich **Antworten eintippen** einschalten; kleine Tippfehler gelten als
„Fast!“. **Für den Test üben** fragt alle Karten eines Stapels ab, ohne den
Karteikasten zu verändern. Karten, die dreimal falsch waren, sind **knifflig**
und lassen sich gezielt üben.

Unter **Mein Konto** wählt jede Person ihren Lernplan (Normal, Schnell,
Gründlich oder eigener), das Tagesziel, die Karten je Runde (5, 10 oder 20) und
eine tägliche **Erinnerung** als Mitteilung aufs Handy (Einrichtung:
[docs/INSTALL-WEBSPACE.md](docs/INSTALL-WEBSPACE.md#erinnerungen-einrichten-freiwillig)).
**Mehr → Hilfe** erklärt alles in einfachen Worten.

**Motivation ohne Punktesammeln.** Ein Ring unter **Heute** zeigt das Tagesziel,
sieben Punkte die letzte Woche (grün: Ziel erreicht), dazu die Lernserie und der
wachsende Wortschatz („Du kannst schon 124 Wörter“).
Abzeichen, Ranglisten oder Vergleiche gibt es nicht.

**Eltern.** Ein Kind erzeugt unter **Mein Konto → Eltern** einen Code (gilt
24 Stunden, nur einmal). Ein Elternteil gibt ihn im eigenen Konto unter **Kind
hinzufügen** ein und sieht dann unter **Meine Kinder** Lerntage, Tagesziel,
Wortschatz, Stapel mit Fortschritt und knifflige Wörter – nur ansehen, keine
einzelnen Antworten. Das Kind sieht, wer zuschaut, und kann es jederzeit
beenden. Dafür braucht es weder eine eigene Rolle noch einen Administrator.

**Rollen.**

| Rolle | darf |
|---|---|
| Benutzer | eigene Stapel, geteilte Stapel folgen und kopieren, lernen |
| Moderator | zusätzlich bei fremden geteilten Stapeln das Teilen beenden oder sie löschen |
| Administrator | zusätzlich Konten freigeben, anlegen, sperren und löschen, App-Tokens, System |

---

## Installation auf einem Server

Zwei Wege:

| Weg | Für wen | Anleitung |
|---|---|---|
| **A: mit Docker** | neuer Server, alles aus einer Hand (Datenbank und HTTPS inklusive) | gleich hier unten |
| **B: Webserver ohne Container** | eigener Server mit SSH, nginx/Apache und PHP, **vorhandene PostgreSQL** | [docs/INSTALL-WEBSERVER.md](docs/INSTALL-WEBSERVER.md) |
| **C: Webspace mit FTP** | gemieteter Webspace ohne Kommandozeile, PostgreSQL vom Hoster | [docs/INSTALL-WEBSPACE.md](docs/INSTALL-WEBSPACE.md) |

Für B und C gibt es fertige Skripte, die Installation und Updates in einem
Befehl erledigen:
- **B:** `scripts/deploy-webserver.sh` läuft auf dem Server und prüft vorher die Voraussetzungen.
- **C:** `./deploy.sh` läuft auf deinem Rechner, mit einmal ausgefüllter `.env.deploy`. Es baut, lädt per FTPS hoch und migriert die Datenbank.

### Weg A: mit Docker

Im Ordner [`deploy/`](deploy/) liegt eine fertige Konfiguration mit drei Diensten:
- **Cardbox** selbst
- **PostgreSQL** für die Daten
- **Caddy**, der das HTTPS-Zertifikat automatisch holt und erneuert

Zeitaufwand: etwa 15 Minuten, der erste Build dauert ein paar Minuten.

### Was du brauchst

- Einen Linux-Server, schon ein kleiner mit 1–2 GB RAM reicht.
- [Docker Engine mit dem Compose-Plugin](https://docs.docker.com/engine/install/).
  Prüfen: `docker compose version`.
- Eine Adresse wie `cardbox.example.com`. Ihr DNS-Eintrag (A bzw. AAAA) muss auf
  den Server zeigen.
- Offene Ports **80** und **443**. Caddy braucht beide, um das Zertifikat zu holen.

### 1. Code holen

```bash
git clone https://github.com/kleineLoesungen/cardbox.git
cd cardbox/deploy
```

### 2. Einstellungen ausfüllen

```bash
cp .env.example .env
chmod 600 .env
```

Dann `.env` öffnen (z. B. `nano .env`) und drei Werte eintragen:

| Eintrag | Wert |
|---|---|
| `CARDBOX_DOMAIN` | deine Adresse, ohne `https://`, z. B. `cardbox.example.com` |
| `APP_KEY` | Ausgabe von `echo "base64:$(openssl rand -base64 32)"` |
| `DB_PASSWORD` | Ausgabe von `openssl rand -hex 24` |

Alles Weitere hat sinnvolle Vorgaben und ist in der Datei erklärt (Name der
App, Zeitzone, optionaler Update-Hinweis, optionale E-Mail).

> **`APP_KEY` und `DB_PASSWORD` gut aufheben**, z. B. im Passwortmanager. Ein
> neuer `APP_KEY` meldet alle ab. Ohne `DB_PASSWORD` kommt man nicht mehr an die
> Datenbank heran.

### 3. Starten

```bash
docker compose up -d --build
```

Beim ersten Start legt Cardbox die Tabellen selbst an. Prüfen, ob alles läuft:

```bash
docker compose ps
```

```bash
curl https://cardbox.example.com/api/v1/health
```

`docker compose ps` zeigt bei `app` und `db` „healthy“. Die Adresse antwortet mit
`{"status":"ok","database":"ok"}`.

### 4. Erstes Konto anlegen

`https://cardbox.example.com/register` öffnen. **Das erste Konto wird
Administrator** und ist sofort nutzbar. Danach können sich alle unter
`/register` selbst anmelden. Neue Konten warten unter **Mehr → Benutzer** auf
Freigabe, oder du legst sie dort direkt an.

### Aktualisieren

Vorher in [CHANGELOG.md](CHANGELOG.md) nachlesen, was sich ändert, und eine
Sicherung machen (siehe unten).

```bash
cd cardbox
git pull
cd deploy
docker compose up -d --build
```

Neue Migrationen laufen beim Start automatisch. Die installierte Version steht
unter **Mehr → System**.

### Sicherung

Alle Daten liegen in der Datenbank. Im Ordner `deploy/` eine Sicherung als
Datei anlegen:

```bash
docker compose exec -T db sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' > sicherung-$(date +%F).dump
```

Zurückspielen (überschreibt den aktuellen Stand):

```bash
docker compose exec -T db sh -c 'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists' < sicherung-2026-10-11.dump
```

Die Sicherung am besten täglich per `cron` anlegen, auf einen anderen Rechner
kopieren und das Zurückspielen einmal ausprobieren.

### Wenn etwas klemmt

| Problem | Lösung |
|---|---|
| Kein HTTPS, Zertifikatsfehler | Zeigt der DNS-Eintrag auf diesen Server? Sind 80 und 443 offen? `docker compose logs caddy` zeigt den Grund. |
| `app` startet immer wieder neu | `docker compose logs app` lesen. Oft fehlt `APP_KEY` oder `DB_PASSWORD` in `.env`. |
| Passwort vergessen | Eine Administratorin setzt es unter **Benutzer** neu. |
| Einziger Administrator hat sein Passwort vergessen | `docker compose exec app php artisan tinker`, dann `App\Models\User::where('username', 'anna')->first()->update(['password' => 'ein-neues-langes-passwort']);` |
| Logs ansehen | `docker compose logs -f app` |

### Andere Aufbauten

Ohne Container mit vorhandener PostgreSQL: [docs/INSTALL-WEBSERVER.md](docs/INSTALL-WEBSERVER.md).
Container mit **bestehender PostgreSQL**, **eigenem Reverse-Proxy** (nginx,
Traefik) oder mehreren Instanzen: [docs/DEPLOY.md](docs/DEPLOY.md). Dort stehen auch alle
Umgebungsvariablen, die Sicherheits-Checkliste und der Update-Hinweis
(`UPDATE_CHECK_URL`). Für ein geführtes Ausrollen mit KI-Assistent gibt es
`/deploy` (siehe unten).

---

## Entwickeln

Voraussetzungen:
- PHP ≥ 8.4 mit `pdo_pgsql` (und `zip` für den Export mehrerer Stapel)
- [Composer](https://getcomposer.org)
- Docker
- [just](https://github.com/casey/just) (`brew install just`)

**Kein Node.**

```bash
just setup    # Composer, Tailwind, .env, Postgres, Mailpit, Migrationen, Stylesheet
just dev      # Server auf http://localhost:8001
```

Mit Beispieldaten:

```bash
php artisan db:seed
```

Das legt die Konten `anna` (Admin), `moritz` (Moderator), `bea` (Benutzer) und
`neu.nutzer` (wartet auf Freigabe) an, alle mit dem Passwort
`richtig-langes-passwort`. Die Beispieldaten zeigen jede Funktion einmal:

| Konto | Was es zeigt |
|---|---|
| `bea` | „Englisch Unit 3“ mit zwei Lernplänen (Normal mit fälligen Karten, Schnell), Hauptbedeutungen und Hinweisen, 3 Tage Lernserie; folgt „Einmaleins“; Kopie von „Reisen“ mit einer Änderung im Original |
| `anna` | geteilte Stapel „Reisen“ und „Einmaleins“; ein offener Vorschlag zu „Reisen“ |
| `moritz` | folgt „Reisen“ und hat dort den Vorschlag gemacht |

Der Seeder läuft nur auf einer leeren Datenbank; sind die Konten schon da, tut
er nichts. Von vorn: `just db-reset && php artisan db:seed` (löscht alle
lokalen Daten). `tests/Feature/DatabaseSeederTest.php` prüft, dass die
Beispieldaten zur Anwendung passen.

| Dienst | Adresse |
|---|---|
| Anwendung | http://localhost:8001 |
| Postgres | `localhost:56433`, Datenbank `cardbox`, Tests in `cardbox_test` |
| Mailpit | http://localhost:59026 |
| Styleguide | http://localhost:8001/styleguide |

### Häufige Befehle

```bash
just dev          # Server starten (baut das Stylesheet vorher)
just css-watch    # Stylesheet beobachten (zweites Terminal)
just test         # Tests
just check        # Formatierung + Larastan + Templates + Tests — vor jedem Commit
just fmt          # Formatierung automatisch richten
just db-shell     # psql öffnen
just up           # alles im Container starten
php artisan route:list      # alle Routen samt Middleware
```

`just check` ist die einzige Prüfung, es gibt keine CI. Ein Hook führt den Befehl
vor jedem `git commit` aus und bricht bei einem roten Ergebnis ab.

### Technik

| Bereich | Umsetzung |
|---|---|
| Server | PHP 8.5, Laravel 13, lokal `artisan serve`, im Container FrankenPHP |
| Oberfläche | Blade, htmx 2.0.10, Tailwind v4 (Standalone-Binary) über Design-Tokens |
| Datenbank | PostgreSQL, Eloquent, UUIDs, Migrationen beim Containerstart |
| Anmeldung | Session-Cookie, Argon2id, Anmeldebremse pro Benutzername und IP |
| Rechte | Rollen an der Route, von `RouteProtectionTest` bewacht |
| API | JSON unter `/api/v1` für Maschinen, Zugriff über Scopes ([docs/API.md](docs/API.md)) |
| App | PWA (installierbar), Navigation unten im Daumenbereich |
| Qualität | Pint, Larastan Stufe 6, Strict Mode, Pest-Tests gegen echte Postgres |

### Wo was liegt

```
app/Services/          Fachlogik und Validierung — kennt weder Request noch View
                       (DeckService, CardService, LeitnerService, StudyRoundService …)
app/Http/Controllers/  dünne HTML-Controller; Api/V1/ für JSON
app/Models/            Daten und Beziehungen, keine Regeln
routes/web.php         wer was darf, entscheidet die Routengruppe
resources/views/       Blade: pages/, partials/ (htmx), components/, admin/
resources/css/         theme.css (Farben, Rundung) und components.css
database/migrations/   nur neue Dateien, nie bestehende ändern
tests/                 Pest: Unit/ und Feature/
docs/                  ausführliche Doku, docs/specs/ für Spezifikationen
deploy/                Server-Konfiguration: compose.yaml, Caddyfile, .env.example
deploy.sh              Deploy auf Webspace per FTP (Einstellungen in .env.deploy)
```

### Weiterlesen

| Datei | Inhalt |
|---|---|
| [CLAUDE.md](CLAUDE.md) | Regeln für dieses Projekt — für Menschen und KI-Assistenten |
| [docs/specs/](docs/specs/) | Spezifikationen: was vor dem Code entschieden wurde |
| [docs/RECIPES.md](docs/RECIPES.md) | Schrittfolgen: Seite, Ressource, Feld, Rolle, E-Mail … |
| [docs/DESIGN.md](docs/DESIGN.md) | Farben, Komponenten, schmale Bildschirme, Barrierefreiheit |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Warum es so aufgebaut ist |
| [docs/API.md](docs/API.md) | JSON-Schnittstelle und App-Tokens |
| [docs/INSTALL-WEBSERVER.md](docs/INSTALL-WEBSERVER.md) | Installation ohne Container auf nginx/Apache mit vorhandener PostgreSQL |
| [docs/INSTALL-WEBSPACE.md](docs/INSTALL-WEBSPACE.md) | Webspace nur mit FTP: `deploy.sh` und `.env.deploy` |
| [docs/DEPLOY.md](docs/DEPLOY.md) | Produktivbetrieb im Detail: eigene Datenbank, Proxy, Sicherheit, alle Einstellungen |

### Arbeiten mit KI-Assistenten

| Datei | Wirkung |
|---|---|
| `CLAUDE.md` | gilt immer: Stack, Schichtregeln, Fallstricke |
| `/spec <Beschreibung>` | neue Funktion: erst Spezifikation nach `docs/specs/`, dann Code |
| `/deploy <Ziel>` | Ausrollen nach festem Ablauf |
| `.claude/agents/` | `code-reviewer` vor dem Commit, `security-reviewer` nach Änderungen an Anmeldung und Rechten |
| `.claude/hooks/pre-bash.sh` | `just check` vor jedem Commit, weist datenlöschende Befehle ab |

---

## Lizenz

[MIT](LICENSE) – kostenlos und frei nutzbar, auch zum Weitergeben und
Verändern. Nur der Lizenzhinweis muss erhalten bleiben.

Produktseite: [cardbox.se-wi.com](https://cardbox.se-wi.com). Sie ist eine
einzelne statische Datei in `site/`, unabhängig von der App, und wird bei
Änderungen in `site/` über GitHub Pages veröffentlicht
(`.github/workflows/pages.yml`).
