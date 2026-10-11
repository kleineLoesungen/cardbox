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
| Lernen: Karteikasten, Lernrunde, freies Üben, Lernserie | 🔜 Stufe 2 | [vokabelkarten.md](docs/specs/vokabelkarten.md) |
| Feinschliff: Lernplan, Hilfe, Vorlesen, Wischgesten | 🔜 Stufe 3 | [vokabelkarten.md](docs/specs/vokabelkarten.md) |

Gelernt wird ab Stufe 2. Bis dahin zeigt „Heute“ die eigenen und gefolgten
Stapel.

---

## So funktioniert es

**Konten.** Jede Person kann sich unter `/register` ein Konto anlegen und braucht
dafür nur einen Namen, einen Benutzernamen und ein Passwort, keine E-Mail-Adresse.
Nutzbar wird das Konto erst, wenn eine Administratorin oder ein Administrator es
unter **Benutzer** freigibt. Administratoren können Konten auch direkt anlegen.
Ein Knopf schlägt merkbare Passwörter vor, z. B. `Wolke-Tiger-Apfel-47`.

**Stapel.** Ein Stapel hat eine Sprache vorne und eine hinten, z. B. Deutsch →
Englisch. Er ist entweder privat oder mit allen geteilt. Für einen geteilten
Stapel einer anderen Person gibt es zwei Wege:
- **Folgen:** lernen ja, ändern nein. Korrekturen der Besitzerin kommen sofort an.
- **Kopieren:** Man bekommt eine eigene Fassung zum Ändern, der Lernstand wird
  mitgenommen.

**Lernen.** Der Karteikasten hat fünf Fächer, die Abstände sind 1, 2, 4 und 7 Tage.
- Gewusst: Die Karte rückt ein Fach weiter.
- Nicht gewusst: Die Karte geht zurück in Fach 1 und kommt am Ende der Runde
  noch einmal.

Gelernt wird in beide Richtungen getrennt. „Freies Üben“ fragt zufällige Karten
ab, ohne dass etwas gezählt wird.

**Rollen.**

| Rolle | darf |
|---|---|
| Benutzer | eigene Stapel, geteilte Stapel folgen und kopieren, lernen |
| Moderator | zusätzlich bei fremden geteilten Stapeln das Teilen beenden oder sie löschen |
| Administrator | zusätzlich Konten freigeben, anlegen, sperren und löschen, App-Tokens, System |

---

## Betreiben

Cardbox läuft als Container (FrankenPHP) und nutzt eine **externe
PostgreSQL**. Ablauf, Sicherheitseinstellungen und Sicherung stehen in
[docs/DEPLOY.md](docs/DEPLOY.md). Für ein geführtes Ausrollen gibt es den Befehl
`/deploy` (siehe unten).

Nach dem ersten Start `/register` öffnen. **Das erste Konto wird
Administrator** und ist sofort nutzbar. Alle weiteren Registrierungen warten
danach unter **Benutzer** auf Freigabe.

Was sich geändert hat, steht in [CHANGELOG.md](CHANGELOG.md). Die oberste
Überschrift ist die Version, die die Anwendung unter **System** anzeigt. Wer
neue Versionen angezeigt bekommen möchte, trägt in `.env` ein:

```
UPDATE_CHECK_URL=https://raw.githubusercontent.com/kleineLoesungen/cardbox/main/CHANGELOG.md
```

---

## Entwickeln

Voraussetzungen:
- PHP ≥ 8.3 mit `pdo_pgsql`
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
`richtig-langes-passwort`. Dazu kommen ein geteilter Stapel von `anna` und ein
privater von `bea`.

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
                       (DeckService, CardService, CardImportService, UserService …)
app/Http/Controllers/  dünne HTML-Controller; Api/V1/ für JSON
app/Models/            Daten und Beziehungen, keine Regeln
routes/web.php         wer was darf, entscheidet die Routengruppe
resources/views/       Blade: pages/, partials/ (htmx), components/, admin/
resources/css/         theme.css (Farben, Rundung) und components.css
database/migrations/   nur neue Dateien, nie bestehende ändern
tests/                 Pest: Unit/ und Feature/
docs/                  ausführliche Doku, docs/specs/ für Spezifikationen
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
| [docs/DEPLOY.md](docs/DEPLOY.md) | Produktivbetrieb und Sicherheit |

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

Noch keine Lizenz festgelegt.
