# Changelog und Update-Prüfung

## Ziel

Jede Installation weiß, welche Version von ihr läuft, und zeigt Administratoren
unter **System**, was sich seit wann geändert hat. Ist eine Prüfadresse
eingetragen, meldet sie zusätzlich, dass im Repository eine neuere Version
liegt — mitsamt der Änderungen und einer Hervorhebung nötiger Migrationen.

Vorbild ist die Umsetzung in `kleineLoesungen/vibe-starter-rust`; Format und
Ablauf sind absichtlich dieselben, damit beide Kits gleich zu bedienen sind.

## Nicht dabei

- **Keine Seite „Was ist neu" für alle Benutzer** und keine Markierung
  „gelesen". Das wäre eine eigene Tabelle und ein eigener Menüpunkt.
- **Kein automatisches Aktualisieren.** Die Anwendung lädt nichts herunter und
  installiert nichts. Sie zeigt nur an, dass etwas Neueres existiert.
- **Keine privaten Repositories.** Geprüft wird eine öffentlich lesbare
  Rohdatei. Ein Zugangstoken kommt erst, wenn es gebraucht wird.
- **Kein Hinweis außerhalb der Systemseite.** Kein Abzeichen am Menü, keine
  Hinweisleiste.
- **Die Version wird nicht in `/api/v1/health` ausgegeben** — der Endpunkt ist
  ohne Anmeldung erreichbar.

## Verhalten

### Format von `CHANGELOG.md`

```markdown
# Änderungen

## 2026.10.11.2
- Version (Admin): Änderungen der installierten und früherer Versionen

## 2026.10.11
- Suche in der Benutzerliste
- Migration: 2026_10_11_000000_create_projects_table.php
```

- Eine Version ist eine Überschrift zweiter Ebene im Format `JJJJ.MM.TT`,
  bei mehreren Veröffentlichungen am selben Tag mit angehängter Zählung
  (`.2`, `.3`).
- Stichpunkte darunter (`-` oder `*`) gehören zu dieser Version.
- Ein Stichpunkt, der mit `Migration:` beginnt, wird getrennt und hervorgehoben
  angezeigt.
- Nach der Version darf Zusatztext stehen (`## 2026.10.11 — Herbstausgabe`).
- Zeilen, die in kein Muster passen, werden stillschweigend übergangen. Die
  Anwendung darf an einer formlosen Zeile nicht scheitern.
- **Die installierte Version ist der oberste Eintrag.** Keine Versionsnummer in
  `composer.json`, die gepflegt werden muss.

Anzeige für Menschen: `2026.10.11` → `11.10.2026`, mit Zählung
`2026.10.11.2` → `11.10.2026 (2)`.

### Systemseite (`/admin/system`)

Ein Abschnitt **Version** oberhalb der bestehenden:

| Zeile | Inhalt |
|---|---|
| Installiert | Version der laufenden Instanz, sonst „unbekannt" |
| Neueste | oberste Version an der Prüfadresse, sonst „–" |
| Zuletzt geprüft | Zeitpunkt, bei Fehlschlag mit dem Zusatz „nicht erreichbar" |

Darunter:

- Liegt eine neuere Version vor: deren Einträge, neueste zuerst, Migrationen
  hervorgehoben.
- Immer: die Änderungen der installierten und der **drei vorherigen** Versionen.
- Ein Knopf **Jetzt prüfen**, der den Zwischenspeicher übergeht.

Ist keine Prüfadresse gesetzt, entfallen „Neueste" und „Zuletzt geprüft"; statt
dessen steht dort, dass die Prüfung abgeschaltet ist.

### Prüfung gegen das Repository

- Adresse aus `UPDATE_CHECK_URL`, gelesen in `config/updates.php`. **Leer oder
  nicht gesetzt bedeutet abgeschaltet** — das ist die Vorgabe im Kit, damit
  abgeleitete Projekte nicht die Versionen des Kits anzeigen und nicht
  ungefragt nach außen sprechen.
- Beispiel für ein eigenes Repository:
  `https://raw.githubusercontent.com/<konto>/<repo>/main/CHANGELOG.md`
- Abgerufen wird höchstens **alle 24 Stunden**, beim Aufruf der Systemseite.
  Der Knopf „Jetzt prüfen" erzwingt einen Abruf.
- **Zeitlimit 3 Sekunden.** Die Systemseite darf nie an einer langsamen
  Verbindung hängen.
- Schlägt der Abruf fehl, bleiben die zuletzt bekannten Einträge stehen; die
  Zeile „Zuletzt geprüft" weist den Fehlschlag aus. Es gibt **keine**
  Fehlerseite: Eine nicht erreichbare Prüfadresse ist kein Fehler der
  Anwendung.
- Abgerufen werden höchstens die 100 obersten Einträge, höchstens 256 KiB.

## Berechtigungen

- Systemseite und Prüfung: **nur Administratoren**, über die vorhandene
  Routengruppe `role:admin`. Der Knopf ist ein `POST` und läuft damit durch die
  CSRF-Prüfung.
- Die installierte Version steht zusätzlich klein in der **Fußzeile** und ist
  damit für alle angemeldeten Benutzer sichtbar. Bewusste Entscheidung: Sie
  hilft bei Rückfragen („welche Version hast du?") und ist kein Geheimnis. Ohne
  Anmeldung ist sie nirgends zu sehen — der Styleguide ist öffentlich und
  benutzt dasselbe Layout.
- Keine Scopes, kein API-Zugang.

## Datenmodell

Neue Migration `2026_10_11_000000_create_settings_table.php`:

```php
Schema::create('settings', function (Blueprint $table) {
    $table->string('key')->primary();
    $table->text('value');
    $table->timestampTz('updated_at')->useCurrent();
});
```

Ein allgemeiner Schlüssel-Wert-Speicher für Dinge, die zur Laufzeit entstehen
und keine eigene Tabelle rechtfertigen. Erster und vorerst einziger Eintrag:

- Schlüssel `update_check`, Wert ein JSON-Text mit `url`, `checked_at`, `ok`
  und `entries`.

Die Prüfadresse steht im Wert, damit ein Wechsel der Adresse den alten Stand
nicht als „neuere Version" ausgibt.

## Betroffene Stellen

**Neu**

| Datei | Inhalt |
|---|---|
| `CHANGELOG.md` | Erster Eintrag mit dem Datum der Einführung |
| `app/Support/Changelog.php` | Parser, Versionsvergleich, Beschriftung — reine Logik, gut testbar |
| `app/Support/ChangelogEntry.php` | Eine Version mit ihren Stichpunkten |
| `app/Support/UpdateStatus.php` | Was die Systemseite anzeigt |
| `app/Services/UpdateCheck.php` | Abruf, Zwischenspeicher, Statusermittlung |
| `app/Models/Setting.php` | Schlüssel-Wert-Speicher |
| `config/updates.php` | `UPDATE_CHECK_URL` |
| `database/migrations/2026_10_11_000000_create_settings_table.php` | siehe oben |
| `resources/views/components/changelog-items.blade.php` | Stichpunkte einer Version |

**Geändert**

| Datei | Änderung |
|---|---|
| `app/Http/Controllers/Admin/SystemController.php` | Versionsabschnitt füllen, Handler für „Jetzt prüfen" |
| `routes/web.php` | Route `POST /admin/system/update-check` |
| `app/Providers/AppServiceProvider.php` | Version für die Fußzeile (View Composer) |
| `resources/views/admin/system.blade.php` | Abschnitt „Version" |
| `resources/views/layouts/app.blade.php` | Version in der Fußzeile, nur für Angemeldete |
| `.env.example`, `docs/DEPLOY.md`, `README.md`, `CLAUDE.md` | `UPDATE_CHECK_URL`, Pflege des Changelogs |
| `scripts/new-project.sh` | `CHANGELOG.md` für das neue Projekt zurücksetzen |

Die Schichtregel bleibt gewahrt: Der Parser ist reine Logik in `app/Support/`,
der Abruf liegt als Service in `app/Services/`, der Controller bleibt dünn.

## Tests

**Parser und Versionen** (`tests/Unit/ChangelogTest.php`)

- Mehrere Versionen mit Stichpunkten, neueste zuerst
- `Migration:`-Zeilen landen getrennt, Groß-/Kleinschreibung egal
- Formlose Zeilen, leere Datei, Datei ohne Überschrift — nie ein Absturz
- Vergleich: `2026.10.09` < `2026.10.09.2` < `2026.10.10`
- Beschriftung inklusive Zählung
- Die eigene `CHANGELOG.md` hat eine gültige Version

**Abruf, Zwischenspeicher und Oberfläche** (`tests/Feature/UpdateCheckTest.php`,
mit `Http::fake()` und echter Datenbank)

- Erster Abruf schreibt den Eintrag, zweiter Aufruf innerhalb von 24 Stunden
  ruft nicht erneut ab, nach 24 Stunden wieder
- „Jetzt prüfen" übergeht den Zwischenspeicher
- Fehlgeschlagener Abruf (404, 500, Verbindungsfehler) behält die alten
  Einträge und setzt `ok` auf falsch
- Gewechselte Prüfadresse verwirft die alten Einträge
- Keine Adresse gesetzt: kein Netzzugriff, kein Eintrag in `settings`
- Übergroße Antwort und mehr als 100 Einträge werden begrenzt
- Systemseite zeigt Version und neuere Einträge; „Jetzt prüfen" ist für
  Nicht-Administratoren gesperrt (403)
- Fußzeile zeigt die Version nur Angemeldeten

## Annahmen und Risiken

**`CHANGELOG.md` muss im Abbild liegen.** Anders als im Rust-Kit (`include_str!`)
wird die Datei zur Laufzeit gelesen. Das `Dockerfile` kopiert das ganze
Projekt, `.dockerignore` schließt `*.md` nicht aus — beides darf sich nicht
ändern. Deshalb gehört `docker build` zur Abnahme dieser Funktion.

**Ausgehende Verbindung.** Eine Installation mit gesetzter Prüfadresse ruft
höchstens einmal am Tag das Repository auf und verrät dabei ihre IP-Adresse. Im
Kit ist das abgeschaltet; in `docs/DEPLOY.md` wird es benannt.

**Das Umleitungsziel bestimmt die Gegenstelle.** Deshalb folgt der Abruf
Umleitungen nur zu `https` und höchstens dreimal (drei, weil
`github.com/…/raw/…` auf `raw.githubusercontent.com` umleitet). Ohne diese
Regel führte ein `302` auf eine interne Adresse dazu, dass der Server aus dem
eigenen Netz heraus abfragt.

**Abgeleitete Projekte.** Ein Projekt aus dem Kit erbt sonst den Changelog des
Kits. `scripts/new-project.sh` ersetzt die Datei durch einen frischen ersten
Eintrag mit dem Datum des Tages.

**Pflegeaufwand.** Der Changelog wird von Hand geschrieben. Wird er vergessen,
bleibt die Versionsanzeige stehen — falsch wird sie nie, nur alt. Eine
Erzeugung aus Git-Commits ist bewusst nicht vorgesehen: Commit-Titel sind für
Entwickler geschrieben, Changelog-Einträge für Betreiber.

**Offen gelassen:** private Repositories (Token), Signaturprüfung der
Prüfadresse, Benachrichtigung per E-Mail bei neuer Version.

---

## Stand der Umsetzung (2026-10-11)

Umgesetzt wie beschrieben. Abweichungen und Ergänzungen:

- Changelog-Einträge sind **reiner Text**; Markdown darin wird nicht
  ausgewertet. Das steht als Hinweis in `CHANGELOG.md`.
- `App\Support\Changelog` liest die Datei über `dirname(__DIR__, 2)` statt über
  `base_path()`. So funktioniert der Parser auch im Unit-Test, der keine
  laufende Anwendung hat.
- Der Antwortkörper wird über den PSR-Stream in Stücken gelesen und bei
  256 KiB verworfen. `$response->body()` würde erst alles puffern und danach
  begrenzen — eine Gegenstelle, die endlos liefert, füllte damit den Speicher.
- Geprüft: `docker build` übernimmt `CHANGELOG.md`, und der Container meldet
  die richtige Version.
- Geprüft gegen einen echten lokalen Server (nicht nur `Http::fake`): Abruf,
  Parsen und Zwischenspeicher laufen durch; eine Umleitung auf `http` wird mit
  „does not use one of the allowed redirect protocols: https" abgewiesen.
- 134 Tests grün, davon 31 für Changelog und Update-Prüfung. **Nicht**
  abgedeckt: das Zeitlimit von 3 s — ein Test dafür müsste drei Sekunden
  warten; das ist den Preis bei jedem `just check` nicht wert.
- Beim Schreiben der Tests aufgefallen: Ein zweites `Http::fake()` ersetzt das
  erste **nicht** (der erste passende Eintrag gewinnt). Für „erst erreichbar,
  dann nicht mehr" ist `Http::fakeSequence()` nötig.
