# Regeln für dieses Projekt

Diese Datei ist für KI-Assistenten gedacht (Claude Code, Cursor, …) und
gleichzeitig als Kurzreferenz für Menschen brauchbar. Sie beschreibt, was in
diesem Projekt gilt und wo die Fallstricke liegen.

---

## Der Stack in einem Satz

PHP 8.5 mit Laravel 13, serverseitig gerendertes HTML mit Blade,
Teilaktualisierungen mit htmx, Aussehen über Tailwind-v4-Tokens, Daten in einer
externen PostgreSQL. **Kein Node, kein Vite, kein npm, kein Frontend-Framework.**

---

## Festgenagelte Versionen — bitte nicht „aktualisieren"

| Paket | Version | Grund |
|---|---|---|
| **htmx** | **2.0.10** | htmx 4 ist erschienen, bringt aber Breaking Changes (`<hx-partial>`, umbenannte Events, Erweiterungs-Allowlist). Sprachmodelle kennen fast ausschließlich htmx-1/2-Syntax und erzeugen für htmx 4 still kaputtes Markup. Liegt als Datei unter `public/js/htmx.min.js`. |
| **Tailwind** | **v4.3.3** | Standalone-Binary, festgelegt in `scripts/get-tailwind.sh` und im `Dockerfile`. **Nicht** über npm installieren. |
| **Laravel** | **13.x** | `composer update` innerhalb von 13.x ist in Ordnung, ein Sprung auf 14 nicht ohne Upgrade-Guide. |

---

## Laravel-Werkzeuge, die hier NICHT benutzt werden

Das Laravel-Ökosystem bietet für vieles fertige Pakete. Diese sind bewusst
**nicht** im Einsatz — bitte nicht „nachrüsten":

| Nicht | Stattdessen | Warum |
|---|---|---|
| Vite, `npm`, `@vite(...)` | `.bin/tailwindcss`, `<link href="/css/app.css">` | Kein Node im Projekt |
| Livewire, Inertia, Alpine, React, Vue | htmx + wenige Zeilen Vanilla-JS | Eine Sprache, serverseitig gerendert |
| Breeze, Jetstream, Fortify | `AuthController`, `UserService` | Kleine, lesbare Anmeldung mit Anmeldebremse und Ersteinrichtung |
| Sanctum-Tokens | `ApiToken`, `RequireScope` | Sanctum-Tokens gehören einem Benutzer. Unsere gehören **niemandem**, siehe unten |
| Queue-Worker, `Mail::queue()` | `defer(fn () => Mail::…->send(…))` | Kein zweiter Prozess im Container |
| `@vite`-Direktiven, `resources/js` | Inline-`<script>` im Template | Es gibt keinen JS-Build |
| Laravel Boost | diese Datei | Boost überschreibt `CLAUDE.md` |

---

## Sprache im Code

* **Bezeichner englisch** — Klassen, Methoden, Variablen, Datenbanktabellen und
  -spalten, Routen, Formularfelder, CSS-Klassen, `data`-Attribute:
  `NoteService::listForUser()`, `notes.user_id`, `name="title"`.
  So heißen sie auch in Laravel, und so rät man sie.
* **Kommentare, Doku, Oberflächentexte, Testnamen deutsch.**
  `it('behandelt fremde Notizen wie nicht vorhandene', …)`.

---

## Die eine wichtige Schichtregel

```
app/Services/          →  Fachlogik und Validierung. Kennt WEDER Request NOCH Response NOCH View.
app/Http/Controllers/  →  HTML-Routen. Dünn. Ruft Services auf.
app/Http/Controllers/Api/V1/  →  JSON-Routen. Dünn. Ruft DIESELBEN Services auf.
app/Models/            →  Daten, Beziehungen, kleine Ableitungen (initials(), isValid()). Keine Regeln.
```

Wenn in `app/Services/` ein `Request`, `request()`, `response()`, `view()`,
`redirect()` oder `session()` auftaucht, ist es an der falschen Stelle.

**Validierung gehört in den Service**, nicht in den Controller und nicht in eine
FormRequest-Klasse. `Validator::make(...)->validate()` wirft eine
`ValidationException`; Laravel macht daraus automatisch

* im Browser: Weiterleitung zurück mit `$errors` und `old()`,
* bei htmx: eine Meldung (siehe `bootstrap/app.php`),
* in der API: `422 {"error": {"code": "validation_failed", …}}`.

Eine Regel wird so einmal geschrieben, nicht zweimal leicht unterschiedlich.

---

## Autorisierung steht an der Route, nie im Controller

Nie `if ($user->role === …)` oder `abort_unless($user->isAdmin())` in einen
Controller schreiben. Stattdessen die Route in die richtige Gruppe in
`routes/web.php` legen:

```php
Route::middleware('auth')->group(…);                    // angemeldet
Route::middleware(['auth', 'role:moderator'])->group(…); // ab Moderator
Route::middleware(['auth', 'role:admin'])->group(…);     // nur Admin
```

In der JSON-Schnittstelle (`routes/api.php`) ausschließlich Scopes:

```php
->middleware('api.token')         // irgendein gültiges Token
->middleware('scope:notes:read')  // Token mit diesem Scope
```

**`tests/Feature/RouteProtectionTest.php` schlägt fehl**, sobald eine Route ohne
Schutz dasteht, die nicht ausdrücklich in dessen Liste `PUBLIC_ROUTES` steht.
Eine neue öffentliche Route also bewusst dort eintragen — nie den Test lockern.

**Eigentumsprüfung gehört in den Service.** Für die Weboberfläche filtern alle
Abfragen nach dem Besitzer (`NoteService::getOwned`). Fremde Datensätze ergeben
404, nicht 403 — sonst verrät die Antwort, welche IDs existieren.

---

## App-Tokens sind Maschinen, keine Menschen

Ein App-Token steht für ein **fremdes System**, nicht für eine Person:

* Es gehört keinem Benutzer und erbt **keine Rolle**. `$request->user()` ist in
  der API immer `null`.
* Was es darf, steht ausschließlich in seinen **Scopes** (`app/Enums/Scope.php`).
* Das Token der Anfrage: `ApiToken::fromRequest($request)`.
* Ausstellen dürfen nur Administratoren, unter `/admin/tokens`.
* Wo ein Besitzer gebraucht wird, gibt die Schnittstelle ihn **ausdrücklich mit**
  (`{"user_id": "…", …}`), statt ihn aus dem Token abzuleiten.

Daraus folgt: Die Service-Funktionen gibt es in zwei Ausführungen, und der
Unterschied ist sicherheitsrelevant.

| Fassung | Für | Verhalten |
|---|---|---|
| `listForUser`, `getOwned`, `updateOwned`, `deleteOwned` | Web-Controller | filtert nach Besitzer |
| `listAll`, `get`, `update`, `delete` | API-Controller | systemweit, ohne Filter |

**Wer in einem Web-Controller versehentlich die systemweite Fassung benutzt,
öffnet fremde Daten.** Im Zweifel die `Owned`-Fassung nehmen. Genauso wenig
`Note::find($id)` direkt im Web-Controller — immer über den Service oder
`$request->user()->notes()->findOrFail($id)`.

---

## Anmeldung ist gebremst

Drei Fehlversuche sind frei, danach greift eine Sperre, die mit jedem weiteren
Fehlversuch wächst (5 s → 15 s → 45 s → … → 15 min). Gezählt wird gleichzeitig
pro Konto und pro IP-Adresse; siehe `app/Services/LoginThrottle.php`.

Zwei Dinge dabei nicht kaputtmachen:

* Die Prüfung läuft **vor** `UserService::findByCredentials`. Ein gesperrter
  Versuch soll keine Argon2-Rechenzeit kosten.
* Ein gesperrter Versuch erhöht den Zähler **nicht**, sonst verlängert stures
  Weiterklicken die eigene Sperre endlos.

Hinter einem Reverse-Proxy muss `TRUST_PROXY=true` gesetzt sein, sonst zählen
alle Anfragen auf die Adresse des Proxys. Ohne Proxy muss es `false` bleiben —
sonst kann sich jeder Client per `X-Forwarded-For` eine neue Identität geben.

---

## Selbstregistrierung ist geschlossen

`/register` funktioniert nur, solange die Installation **kein einziges Konto**
hat — das ist die Ersteinrichtung, und dieses Konto wird Administrator. Danach
legt der Administrator alle weiteren Konten unter `/admin/users` an.

Die Prüfung steht im **Controller**, nicht nur im Template. Ein ausgeblendeter
Link hält niemanden davon ab, das Formular von Hand abzuschicken.

---

## Laravel-Fallstricke, die hier schon einmal zugeschlagen haben

1. **`env()` nur in `config/*.php`.** Im Container läuft `php artisan config:cache`;
   danach liefert `env()` überall sonst `null`. Im Code immer `config('app.name')`.
   Neue Einstellung: [docs/RECIPES.md](docs/RECIPES.md#11-eine-neue-umgebungsvariable).

2. **`#` in `.env` braucht Anführungszeichen.** `PWA_THEME_COLOR=#3370c7` ist für
   phpdotenv ein leerer Wert mit Kommentar. Richtig: `PWA_THEME_COLOR="#3370c7"`.
   Dasselbe gilt für Werte mit Leerzeichen. Die Farben werden beim Start geprüft
   (`App\Support\Branding::assertValid`) — deshalb fällt es sofort auf.

3. **Mehrfachauswahl braucht `[]` im Namen.** Mehrere Kontrollkästchen mit
   `name="scopes"` senden den Wert wiederholt, und PHP behält **still nur den
   letzten**. Richtig: `name="scopes[]"`. Beispiel: `resources/views/admin/tokens.blade.php`,
   abgesichert durch `nimmt mehrere angekreuzte Berechtigungen entgegen`.

4. **Strict Mode ist an** (`Model::shouldBeStrict()` außerhalb der Produktion).
   Drei Fehler, die sonst still passieren, werfen dann eine Exception:
   * Feld nicht in `#[Fillable([...])]` → `MassAssignmentException`. Feld ergänzen
     — oder bei Feldern, die Benutzer nie setzen dürfen (`revoked_at`),
     `$model->forceFill([...])->save()`.
   * Beziehung in einer Schleife nachgeladen (N+1) → `LazyLoadingViolationException`.
     Mit `->with('user')` vorladen.
   * Tippfehler im Attributnamen → `MissingAttributeException`.

5. **Keine ungeprüften IDs an Postgres.** Eine UUID-Spalte mit `'abc'` abzufragen
   ist in Postgres ein **Datenbankfehler** (500), nicht „nicht gefunden".
   Routen mit `->whereUuid('note')` versehen, im Service `Str::isUuid()` prüfen
   (siehe `NoteService::ensureUuid`).

6. **Blade maskiert nur `{{ }}`.** `{!! $x !!}` ist verboten, außer für HTML, das
   der Server selbst vollständig erzeugt hat. In `onclick="…"`- oder
   `<script>`-Blöcken genügt `{{ }}` nicht — Werte über `data-*`-Attribute
   übergeben (Beispiel: Löschen-Rückfrage in `admin/user.blade.php`) oder `@js($wert)`.

7. **`@csrf` in jedes `<form method="post">`.** Moderne Browser bestehen die
   Prüfung zwar schon über `Sec-Fetch-Site`, ältere nicht. htmx-Anfragen schicken
   das Token automatisch mit (`hx-headers` am `<body>`). **Achtung:** In Tests ist
   die CSRF-Prüfung abgeschaltet — ein vergessenes `@csrf` fällt dort nicht auf.

8. **htmx-Antworten sind Fragmente.** Ein Controller, der auf `hx-post` antwortet,
   gibt eine Partial-View zurück (`partials/…`), nie `redirect()` und nie eine
   ganze Seite. Validierungsfehler erledigt `bootstrap/app.php` von selbst.

9. **Validierungsmeldungen auf Deutsch** stehen in `lang/de/validation.php`.
   Neue Regel benutzt, die dort fehlt? Ergänzen — sonst erscheint sie englisch.
   Neues Feld? Unter `attributes` den deutschen Namen eintragen.

---

## Changelog ist die Version

`CHANGELOG.md` wird von Hand gepflegt, und die **oberste Überschrift ist die
Version**, die die Anwendung von sich meldet. Es gibt keine Versionsnummer in
`composer.json`, die nachgezogen werden müsste.

```markdown
## 2026.10.11
- Suche in der Benutzerliste
- Migration: 2026_10_11_000000_create_projects_table.php
```

* Format `JJJJ.MM.TT`, bei mehreren Veröffentlichungen am selben Tag `.2`, `.3`.
* Einträge sind für **Betreiber** geschrieben, nicht für Entwickler. Nicht der
  Commit-Titel, sondern was sich für die Benutzung ändert.
* Zeilen mit `Migration:` werden getrennt hervorgehoben.
* **Jede nennenswerte Änderung bekommt einen Eintrag** — am besten im selben
  Commit. Eine neue Version anlegen heißt: eine neue Überschrift mit dem Datum.

Gelesen wird die Datei zur Laufzeit (`App\Support\Changelog`). Sie muss
deshalb im Abbild liegen — `.dockerignore` darf sie nicht ausschließen.

Mit gesetztem `UPDATE_CHECK_URL` vergleicht die Anwendung ihre Version
höchstens täglich mit der Rohdatei im Repository und zeigt Administratoren
neuere Einträge (`App\Services\UpdateCheck`). Ohne die Variable passiert
nichts, auch kein Netzzugriff.

---

## E-Mail

```php
Mail::to($user->email)->send(new WelcomeMail($user));                  // wartet
defer(fn () => Mail::to($user->email)->send(new WelcomeMail($user)));   // nach der Antwort
```

* Jede Mail ist eine Klasse unter `app/Mail/` mit **HTML- und Textfassung**
  (`view:` und `text:` in `content()`).
* HTML-Mails erben von `mail/layout.blade.php`, **nur Inline-Styles** — keine
  Tailwind-Klassen.
* In Tests `Mail::fake()` und `Mail::assertSent(...)`, nie echten SMTP.
* Rezept: [docs/RECIPES.md](docs/RECIPES.md#12-eine-e-mail-verschicken).

---

## PWA: Der Service Worker speichert keine Seiten

`PwaController` liefert Manifest, Service Worker (`resources/views/pwa/service-worker.blade.php`)
und Offline-Seite. Der Worker speichert nur `/css/`, `/js/`, `/icons/` zwischen.
**Keine Seiten, keine API, keine htmx-Fragmente in den Cache aufnehmen** — die
Anwendung ist angemeldet und serverseitig gerendert. Eine gecachte Seite zeigt
veraltete Daten oder nach dem Abmelden die Inhalte des vorherigen Benutzers.

Name, Kurzname und Farben kommen aus `APP_NAME`, `APP_SHORT_NAME`,
`PWA_THEME_COLOR`, `PWA_BACKGROUND_COLOR`. In Templates als `$branding`.

---

## Datenbank

* **Migrationen nie ändern, die schon gelaufen sein könnten.** Immer eine neue
  Datei: `php artisan make:migration add_done_to_notes_table`.
* UUIDs als Primärschlüssel (`$table->uuid('id')->primary()` + `HasUuids` im Modell),
  Fremdschlüssel mit `foreignUuid(...)->constrained()->cascadeOnDelete()`.
* Zeitstempel mit Zeitzone: `timestampsTz()`, `timestampTz()`. Die Anwendung
  rechnet in UTC.
* **Nie Benutzereingaben in SQL-Strings einsetzen.** Query Builder oder
  Platzhalter (`DB::select('… WHERE x = ?', [$wert])`).
* Tests laufen gegen die echte Postgres-Datenbank `starter_test`, nicht gegen SQLite.

---

## Aussehen ändern

Nur semantische Tokens verwenden:

```html
<div class="bg-surface-raised text-content border-border">   ✅
<div class="bg-white text-gray-900 border-gray-200">         ❌ bricht im Dunkelmodus
```

Verfügbar sind `surface`, `surface-raised`, `surface-sunken`, `border`,
`border-strong`, `content`, `muted`, `subtle`, `brand`, `brand-subtle`,
`brand-content`, `success`, `warning`, `danger` — jeweils als `bg-`, `text-`
und `border-`. Dazu `rounded-ui` für die einheitliche Eckenrundung.

Wiederkehrendes Markup wird zu einer Klasse in `resources/css/components.css`
oder zu einer Blade-Komponente unter `resources/views/components/`, nicht zu
einer wiederholten Utility-Kette. Details: [docs/DESIGN.md](docs/DESIGN.md).

Was auf schmalen Bildschirmen regelmäßig schiefgeht:

* **Grid-Spalten und Flex-Zeilen brauchen `min-w-0`** (und Flex-Zeilen mit
  langem Inhalt `flex-wrap`), sonst schrumpfen sie nie unter die Breite ihres
  Inhalts und drücken das Layout über den Rand. `.card` bringt `min-w-0` mit.
* **Menüpunkte nur in `resources/views/components/nav-links.blade.php`**
  ergänzen. Die Komponente erscheint in der Kopfzeile (breit) und im Blatt hinter
  „Mehr" (schmal). Die untere Leiste auf dem Telefon ist eine handverlesene
  Abkürzung in `layouts/app.blade.php` und bleibt bewusst separat.
* **Zeitstempel nur über `<x-datetime :value="…" />` / `<x-date :value="…" />`**
  ausgeben. `{{ $note->created_at->format(…) }}` direkt im Template liefert UTC
  statt Ortszeit — im Sommer zwei Stunden daneben.

---

## Nach jeder Änderung

```bash
just check     # Pint (Formatierung), Larastan (statische Analyse), Blade kompilieren, alle Tests
```

**Es gibt keine CI.** `just check` ist die einzige Prüfung, die Fehler vor dem
Commit abfängt. Deshalb:

* Vor jeder Meldung „fertig" `just check` ausführen und das Ergebnis nennen.
* Ein roter Lauf heißt: nicht fertig. Nicht committen, nicht als erledigt melden.
* Ein Test, der nur durch Anpassen der Erwartung grün wird, ist verdächtig —
  erst klären, ob der Code oder der Test falsch ist.
* Larastan-Fehler beheben, nicht mit `@phpstan-ignore` oder einer Baseline zudecken.

Formatierung automatisch richten: `just fmt`.
Bei Template- oder CSS-Änderungen zusätzlich `just css`.
Nach einer Änderung an `resources/icons/*.svg` zusätzlich `./scripts/icons.sh`.
Wer `--brand-hue` in `theme.css` ändert, zieht `PWA_THEME_COLOR` und die
Icon-Farbe nach — siehe [docs/DESIGN.md](docs/DESIGN.md#1-der-schnellste-weg-eine-zahl).

---

## Werkzeuge in diesem Repository

Unter `.claude/` liegt ein festes Vorgehen, das in jedem Projekt aus diesem
Kit sofort da ist:

| Was | Wofür |
|---|---|
| `/spec <Beschreibung>` | Neue Funktion: erst Fragen klären, Spezifikation nach `docs/specs/` schreiben, auf Freigabe warten. Kein Code vorher. |
| `/deploy <Ziel>` | Ausrollen nach festem Ablauf: prüfen, sichern, bauen, Rauchtest. Startet nur auf ausdrückliche Anweisung. |
| Subagent `code-reviewer` | Prüft den Diff gegen diese Regeln. Vor dem Commit. |
| Subagent `security-reviewer` | Prüft Berechtigungen, Datentrennung, Eingaben, Geheimnisse. Nach Änderungen an Anmeldung, Rollen, Scopes, SQL. |

Dazu ein Hook (`.claude/hooks/pre-bash.sh`), der vor jedem `git commit`
`just check` ausführt und bei rotem Ergebnis abbricht. Er weist außerdem
Befehle ab, die Daten verwerfen würden: `just db-reset`, `docker compose
down -v` und das Zurücksetzen der Datenbank per `artisan`. Ein Commit lässt
sich damit nicht an der Prüfung vorbeischmuggeln — auch nicht versehentlich.

---

## Wiederkehrende Aufgaben

Für „wie füge ich eine Seite / eine Ressource / ein Feld hinzu" gibt es
fertige Schrittfolgen in **[docs/RECIPES.md](docs/RECIPES.md)**. Diese zuerst
lesen, statt ein eigenes Muster zu erfinden.

---

## Was hier nicht passieren soll

- Ein Frontend-Framework, Livewire, Inertia oder Alpine einführen.
- Node, npm oder Vite einführen. Tailwind läuft als Standalone-Binary.
- Fachlogik oder Validierung in Controller schreiben, statt sie in `app/Services/` zu legen.
- Rollen- oder Scope-Prüfungen im Controller statt an der Route.
- Einem Token eine Benutzeridentität andichten. Es gehört der Installation.
- `/register` wieder öffnen, ohne die Prüfung im Controller mitzudenken.
- Rohe Tailwind-Farben (`bg-blue-500`) statt semantischer Tokens.
- Geheimnisse in den Quellcode. Alles kommt aus `.env` über `config/*.php`.
- `env()` außerhalb von `config/`.
- `{!! !!}` mit Benutzereingaben.
- In `.env` Werte mit `#`, Leerzeichen oder `< >` ohne Anführungszeichen.
- Einträge aus `PUBLIC_ROUTES` im Routentest nur hinzufügen, damit er grün wird.
