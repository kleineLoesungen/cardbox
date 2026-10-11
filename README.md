# Starter — Webanwendungen in PHP

Startpunkt für Webanwendungen mit **Laravel 13**, **Blade**, **htmx** und
**Tailwind CSS v4** auf einer **externen PostgreSQL**-Datenbank, ausgeliefert
als **Container** (FrankenPHP).

Anmeldung, Rollen, App-Tokens und ein anpassbares Design sind bereits eingebaut.
Gemacht für die Arbeit mit KI-Assistenten: klare Regeln in [CLAUDE.md](CLAUDE.md),
fertige Rezepte, ein strenges `just check` — und kein Node.

Das Gegenstück in Rust: [kleineLoesungen/vibe-starter-rust](https://github.com/kleineLoesungen/vibe-starter-rust).
Gleiche Oberfläche, gleiche Konzepte, gleiche Doku-Struktur.

---

## In fünf Minuten laufen

Voraussetzungen: PHP ≥ 8.3 mit `pdo_pgsql`, [Composer](https://getcomposer.org),
Docker, [just](https://github.com/casey/just) (`brew install just`).

```bash
just setup    # Composer, Tailwind, .env, Postgres, Mailpit, Migrationen, Stylesheet
just dev      # Server starten
```

Dann [http://localhost:8000](http://localhost:8000) öffnen und die
**Ersteinrichtung** durchlaufen.

> **Das erste Konto wird Administrator** — sonst käme niemand an die
> Benutzerverwaltung heran. Danach ist die Selbstregistrierung geschlossen:
> alle weiteren Konten legt der Administrator unter **Benutzer** an.

Ohne `just` geht es auch von Hand:

```bash
composer install
./scripts/get-tailwind.sh
cp .env.example .env && php artisan key:generate
docker compose up -d db mail
php artisan migrate
.bin/tailwindcss -i resources/css/app.css -o public/css/app.css
php artisan serve --port=8000
```

Beispieldaten (drei Konten, ein paar Notizen): `php artisan db:seed`.

---

## Eigenes Projekt aus dem Kit

Das Kit selbst bleibt unverändert. Für jede Anwendung eine eigene Kopie:

```bash
git clone <pfad-oder-url-zum-kit> vereinsportal
cd vereinsportal
./scripts/new-project.sh vereinsportal "Vereinsportal"
just setup
just check
```

Das Skript benennt alles um, was „starter" heißt, und wählt freie Ports.
**Direkt nach dem Klonen ausführen, vor jedem `just`-Befehl:** Bis dahin heißt
der Klon noch „starter-php" und würde die Container des Kits benutzen — ein
`just db-reset` löschte dann dessen Datenbank.
**Nicht auslassen**, auch wenn nur ein Projekt geplant ist: Zwei Projekte mit
demselben Namen teilen sich unbemerkt Container und Datenbank — das zweite
überschreibt die Daten des ersten.

Die Ports stehen danach in `.env` und `compose.yaml`; das Skript nennt sie am
Ende. Zeigt `git remote -v` noch auf das Kit: `git remote remove origin`.

---

## Was drin ist

| Bereich | Umsetzung |
|---|---|
| HTTP | Laravel 13, lokal `artisan serve`, im Container FrankenPHP |
| HTML | Blade — `just views` kompiliert alle Templates und findet Syntaxfehler |
| Interaktivität | htmx 2 — Teilaktualisierungen ohne eigenes JavaScript |
| Aussehen | Tailwind v4 als Standalone-Binary, komplett über Design-Tokens steuerbar |
| Datenbank | PostgreSQL, Eloquent, UUIDs als Schlüssel, Migrationen beim Containerstart |
| Anmeldung | Session-Cookie, Sitzungen in Postgres |
| Passwörter | Argon2id |
| Rollen | `user` · `moderator` · `admin`, erzwungen an der Route, bewacht von einem Test |
| Benutzer | Anlegen, bearbeiten, sperren, löschen, Passwort zurücksetzen |
| Eigenes Konto | Name, E-Mail und Passwort selbst ändern |
| API | JSON unter `/api/v1` für Maschinen, Zugriff über Scopes |
| CSRF | Laravel: `Sec-Fetch-Site` plus Token als Rückfallebene |
| Mobil | unter 1024 px Navigationsleiste unten, „Mehr"-Blatt, kein waagerechtes Scrollen |
| Anmeldeschutz | 3 Versuche frei, danach wachsende Sperre pro Konto und IP |
| Zeitangaben | in UTC gespeichert, in der Ortszeit des Betrachters angezeigt |
| Installierbar | PWA mit Manifest, Icons und Offline-Seite; Name und Farben per Umgebung |
| E-Mail | SMTP, lokal Mailpit, Testmail unter **System** |
| Container | Mehrstufiges Dockerfile, FrankenPHP, unprivilegierter Benutzer |
| Version | `CHANGELOG.md` ist die Version; Anzeige unter **System**, Hinweis auf neuere Versionen |
| Qualität | Pint, Larastan (Stufe 6), Strict Mode für Eloquent |
| Tests | 134 Pest-Tests gegen eine echte Postgres |

---

## Wo was liegt

```
app/
├── Services/              ← FACHLOGIK UND VALIDIERUNG. Kennt weder Request noch View.
│   ├── NoteService.php      Beispielressource zum Kopieren
│   ├── UserService.php      Benutzer, letzter Administrator, Passwörter
│   ├── ApiTokenService.php  Ausstellen, Prüfen, Widerrufen
│   └── LoginThrottle.php    Bremse gegen Passwort-Raten
│
├── Models/                Daten und Beziehungen (User, Note, ApiToken, Setting)
├── Enums/
│   ├── Role.php             Rollen und ihre Rangfolge
│   └── Scope.php            Was ein App-Token darf
│   └── UpdateCheck.php      Abgleich mit dem Changelog im Repository
├── Auth/TokenFormat.php   Aufbau und Prüfung der App-Tokens
├── Support/               Branding (Name, Farben), Changelog (Version)
├── Mail/                  Eine Klasse je Mail
│
└── Http/
    ├── Controllers/         ← HTML-Routen (Session-Cookie). Dünn.
    │   ├── Admin/             Benutzer, Tokens, System — nur Administratoren
    │   └── Api/V1/            ← JSON-Routen (Bearer-Token, Scopes). Dünn.
    ├── Middleware/          role:…, scope:…, gesperrte Konten abmelden, Proxy
    └── Resources/           JSON-Form von Notiz und Benutzer

routes/
├── web.php                ← Wer darf was? Die GRUPPE entscheidet.
└── api.php                ← Welcher Scope? Steht an der Route.

CHANGELOG.md               ← die Version der Anwendung, von Hand gepflegt
bootstrap/app.php          Middleware, Fehlerbehandlung für API und htmx
config/                    Die einzige Stelle, an der env() gelesen wird

resources/
├── views/
│   ├── layouts/             Grundgerüst und angemeldeter Rahmen
│   ├── pages/               eine Datei je Seite
│   ├── admin/               Verwaltungsseiten
│   ├── partials/            htmx-Fragmente
│   ├── components/          <x-datetime>, <x-note-item>, <x-nav-links> …
│   ├── mail/                HTML-Mails, nur Inline-Styles
│   └── errors/              Fehlerseiten
├── css/theme.css          ← HIER das Aussehen ändern
├── css/components.css       Komponentenklassen (.btn, .card, …)
└── icons/                   Quelle der App-Icons (SVG)

public/icons/              erzeugte PNG-Icons — nach Änderung ./scripts/icons.sh
database/migrations/       Migrationen
tests/                     Unit/ und Feature/ (Pest)
docs/                      Ausführliche Dokumentation
docs/specs/                Spezifikationen, die vor dem Code entstanden sind
.claude/                   Festes Vorgehen für KI-Assistenten (siehe unten)
```

---

## Weiterlesen

| Datei | Inhalt |
|---|---|
| [docs/RECIPES.md](docs/RECIPES.md) | **Hier anfangen.** Wie füge ich eine Seite, eine Ressource, eine Rolle hinzu? |
| [docs/DESIGN.md](docs/DESIGN.md) | Design-Guide: Farben, Abstände, Komponenten anpassen |
| [docs/API.md](docs/API.md) | JSON-Schnittstelle und App-Tokens |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Warum es so aufgebaut ist, wie es aufgebaut ist |
| [docs/DEPLOY.md](docs/DEPLOY.md) | Produktivbetrieb, Sicherheitseinstellungen |
| [CLAUDE.md](CLAUDE.md) | Regeln für KI-Assistenten in diesem Projekt |
| [docs/specs/](docs/specs/) | Spezifikationen: was vor dem Code entschieden wurde |

Der **Styleguide** unter [/styleguide](http://localhost:8000/styleguide) zeigt jede
Komponente im echten Zustand — mit Reglern zum Ausprobieren von Farbe und Rundung.

---

## Arbeiten mit KI-Assistenten

Das Kit bringt ein festes Vorgehen mit, das in jedem abgeleiteten Projekt ab
dem ersten Commit vorhanden ist:

| Datei | Wirkung |
|---|---|
| `CLAUDE.md` | gilt immer: Regeln, Fallstricke, Stack |
| `.claude/skills/spec/` | `/spec` — erst Spezifikation, dann Code |
| `.claude/skills/deploy/` | `/deploy` — fester Ablauf fürs Ausrollen |
| `.claude/agents/` | Subagents `code-reviewer` und `security-reviewer` |
| `.claude/hooks/pre-bash.sh` | führt vor jedem Commit `just check` aus und weist datenlöschende Befehle ab |

Der Hook ist das Sicherheitsnetz anstelle einer CI: Ein roter `just check`
verhindert den Commit, und die Meldung sagt, was zu tun ist — auch im
häufigsten Fall „Datenbank läuft nicht".

---

## Häufige Befehle

```bash
just dev          # Server starten (baut das Stylesheet vorher)
just css-watch    # Stylesheet beobachten (zweites Terminal)
just test         # Tests
just check        # Formatierung + Larastan + Templates + Tests
just fmt          # Formatierung automatisch richten
just db-shell     # psql öffnen
just mail-up      # Mailpit starten, Mails ansehen unter http://localhost:59025
just db-reset     # Datenbank verwerfen und neu anlegen
just up           # alles im Container starten
php artisan tinker          # PHP-Konsole mit geladener Anwendung
php artisan route:list      # alle Routen samt Middleware
```

## Lizenz

Vorlage zur freien Verwendung. Eigene Lizenz ergänzen.
