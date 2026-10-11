# Rezepte

Fertige Schrittfolgen für die Dinge, die man immer wieder braucht.
Jedes Rezept ist vollständig — abtippen und anpassen. Danach immer `just check`.

---

## Inhalt

1. [Eine neue Seite](#1-eine-neue-seite)
2. [Eine neue Ressource mit CRUD](#2-eine-neue-ressource-mit-crud)
3. [Ein Feld zu einer bestehenden Tabelle](#3-ein-feld-zu-einer-bestehenden-tabelle)
4. [Eine Seite auf eine Rolle beschränken](#4-eine-seite-auf-eine-rolle-beschränken)
5. [Etwas per htmx nachladen](#5-etwas-per-htmx-nachladen)
6. [Eine Meldung anzeigen](#6-eine-meldung-anzeigen)
7. [Ein Formular mit Fehlern zurückgeben](#7-ein-formular-mit-fehlern-zurückgeben)
8. [Einen Endpunkt zur JSON-API](#8-einen-endpunkt-zur-json-api)
9. [Einen neuen Scope](#9-einen-neuen-scope)
10. [Eine vierte Rolle](#10-eine-vierte-rolle)
11. [Eine neue Umgebungsvariable](#11-eine-neue-umgebungsvariable)
12. [Eine E-Mail verschicken](#12-eine-e-mail-verschicken)
13. [Einen Test schreiben](#13-einen-test-schreiben)

---

## 1. Eine neue Seite

**Beispiel: `/reports`**, eine Seite „Berichte" für angemeldete Benutzer.

**a) Template** — `resources/views/pages/reports.blade.php`

```blade
@extends('layouts.app')
@section('title', 'Berichte')

@section('main')
<h1 class="text-2xl font-semibold tracking-tight">Berichte</h1>
<p class="mt-1 text-sm text-muted">{{ $count }} Einträge</p>
@endsection
```

**b) Controller** — `app/Http/Controllers/ReportController.php`

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        return view('pages.reports', ['count' => 0]);
    }
}
```

**c) Route** — in `routes/web.php`, **in der passenden Gruppe**:

```php
Route::middleware('auth')->group(function () {
    // …
    Route::get('/reports', [ReportController::class, 'index']);
});
```

**d) Navigation** (falls gewünscht) — in `resources/views/components/nav-links.blade.php`:

```blade
<a href="/reports" class="{{ $linkClass }} {{ request()->is('reports*') ? 'nav-link-active' : '' }}">Berichte</a>
```

In der Komponente, nicht direkt im Layout: Dann erscheint der Punkt in der
Kopfzeile und im Menü auf dem Telefon.

`just check` — `just views` findet Syntaxfehler im Template, der Routentest
meldet eine Route außerhalb jeder Gruppe.

Bezeichner englisch (`ReportController`, `count`), sichtbarer Text deutsch
(„Berichte", „Einträge") — siehe [CLAUDE.md](../CLAUDE.md#sprache-im-code).

---

## 2. Eine neue Ressource mit CRUD

**Der schnellste Weg: `Note` kopieren und umbenennen.** Die Beispielressource
ist genau dafür da. Am Beispiel „Projekt" (`Project`):

```bash
cp app/Models/Note.php                          app/Models/Project.php
cp app/Services/NoteService.php                 app/Services/ProjectService.php
cp app/Http/Controllers/NoteController.php      app/Http/Controllers/ProjectController.php
cp app/Http/Controllers/Api/V1/NoteController.php app/Http/Controllers/Api/V1/ProjectController.php
cp app/Http/Resources/NoteResource.php          app/Http/Resources/ProjectResource.php
cp database/factories/NoteFactory.php           database/factories/ProjectFactory.php
cp resources/views/pages/notes.blade.php        resources/views/pages/projects.blade.php
cp resources/views/components/note-item.blade.php resources/views/components/project-item.blade.php
cp resources/views/partials/note-created.blade.php resources/views/partials/project-created.blade.php
cp tests/Feature/NotesTest.php                  tests/Feature/ProjectsTest.php
```

Dann in den Kopien `note`→`project`, `Note`→`Project`, `notes`→`projects`
ersetzen. In `app/Models/User.php` die Beziehung ergänzen:

```php
/** @return HasMany<Project, $this> */
public function projects(): HasMany
{
    return $this->hasMany(Project::class);
}
```

Routen in `routes/web.php` (Gruppe `auth`) und `routes/api.php` (mit Scope,
siehe [Rezept 9](#9-einen-neuen-scope)) eintragen.

**Migration:**

```bash
php artisan make:migration create_projects_table
```

```php
Schema::create('projects', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
    $table->string('name', 200);
    $table->text('description')->default('');
    $table->timestampsTz();

    $table->index(['user_id', 'created_at']);
});
```

Dann `php artisan migrate`.

> **Immer mitnehmen:** `uuid('id')` plus `HasUuids` im Modell, die
> `user_id`-Spalte mit `cascadeOnDelete()`, den Index auf `(user_id, created_at)`
> und `timestampsTz()`.
>
> **Und die zwei Fassungen im Service:** Funktionen für die Weboberfläche
> filtern nach Besitzer (`listForUser`, `getOwned`, `updateOwned`, `deleteOwned`)
> — das ist die Datentrennung zwischen Benutzern. Funktionen für die
> Maschinen-Schnittstelle arbeiten systemweit (`listAll`, `get`, `update`,
> `delete`). Wer die falsche benutzt, öffnet fremde Daten.
>
> **Neue Felder** müssen in `#[Fillable([...])]` am Modell stehen, sonst wirft
> `create()` eine `MassAssignmentException`.

---

## 3. Ein Feld zu einer bestehenden Tabelle

**Nie eine bestehende Migration ändern** — sie ist auf anderen Installationen
schon gelaufen. Immer eine neue:

```bash
php artisan make:migration add_done_to_notes_table
```

```php
public function up(): void
{
    Schema::table('notes', function (Blueprint $table) {
        $table->boolean('done')->default(false);
    });
}

public function down(): void
{
    Schema::table('notes', function (Blueprint $table) {
        $table->dropColumn('done');
    });
}
```

Dann an vier Stellen nachziehen:

1. **Modell** `app/Models/Note.php` — `'done'` in `#[Fillable([...])]`, Cast
   `'done' => 'boolean'` in `casts()`, `@property bool $done` im Kommentar.
2. **Service** — Regel in `validate()`: `'done' => ['boolean']`.
3. **Resource** `app/Http/Resources/NoteResource.php` — `'done' => $this->done`.
   Ohne diese Zeile erscheint das Feld **nicht** in der API. Das ist Absicht.
4. **Deutscher Feldname** in `lang/de/validation.php` unter `attributes`.

Checkboxen senden nichts, wenn sie nicht angekreuzt sind. Im Controller deshalb
`$request->boolean('done')` statt `$request->input('done')`.

---

## 4. Eine Seite auf eine Rolle beschränken

Nur die Gruppe in `routes/web.php` ändert sich:

```php
Route::middleware(['auth', 'role:moderator'])->group(function () {
    Route::get('/moderation', [PageController::class, 'moderation']);   // ab Moderator
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/reports', [ReportController::class, 'index']);         // nur Admin
});
```

Wer die Rolle nicht hat, bekommt automatisch eine 403-Seite; wer nicht
angemeldet ist, wird zum Login umgeleitet. **Keine `if`-Abfrage im Controller.**

Navigationspunkt passend ausblenden, in `components/nav-links.blade.php`:

```blade
@if ($user?->hasRole(\App\Enums\Role::Moderator))
  <a href="/moderation" class="{{ $linkClass }}">Moderation</a>
@endif
```

> Das Ausblenden ist reine Anzeige. Die Absicherung ist die Routengruppe.

---

## 5. Etwas per htmx nachladen

Das Muster hat drei Teile: Auslöser, Ziel, Fragment.

**Markup:**

```blade
<input class="input" type="search" name="q"
       hx-get="/notes/search"
       hx-trigger="keyup changed delay:300ms"
       hx-target="#note-list"
       placeholder="Suchen">

<div id="note-list">…</div>
```

**Controller** — gibt **nur das Fragment** zurück, keine ganze Seite:

```php
public function search(Request $request): View
{
    $notes = $this->notes->listForUser($request->user());   // Owned-Fassung!

    return view('partials.note-list', ['notes' => $notes]);
}
```

**Fragment** — `resources/views/partials/note-list.blade.php`:

```blade
@foreach ($notes as $note)
  <x-note-item :note="$note" />
@endforeach
```

Die wichtigsten `hx-swap`-Werte:

| Wert | Wirkung |
|---|---|
| `innerHTML` | Inhalt des Ziels ersetzen (Vorgabe) |
| `outerHTML` | Ziel selbst ersetzen — leere Antwort = Element verschwindet |
| `afterbegin` | oben einfügen (neuer Eintrag in einer Liste) |
| `beforeend` | unten anhängen |

Weitere nützliche Attribute: `hx-confirm="Sicher?"`,
`hx-on::after-request="if (event.detail.successful) this.reset()"`,
`<span class="htmx-indicator spinner"></span>` im Knopf als Ladeanzeige.

POST, PUT und DELETE brauchen kein `@csrf` — `hx-headers` am `<body>` schickt
das Token bei jeder htmx-Anfrage mit.

---

## 6. Eine Meldung anzeigen

**Nach einer Weiterleitung** (normales Formular):

```php
return redirect('/notes')->with('toast', ['kind' => 'success', 'message' => 'Gespeichert.']);
```

**In einer htmx-Antwort** — die Meldung reist als Out-of-Band-Fragment mit.
Das Template braucht eine Zeile:

```blade
@include('partials.toast-oob')
```

Und der Controller übergibt `toast`:

```php
return view('partials.note-created', [
    'note' => $note,
    'toast' => ['kind' => 'success', 'message' => 'Notiz gespeichert.'],   // oder 'danger'
]);
```

htmx setzt das Fragment nach `#toasts`, unabhängig vom eigentlichen `hx-target`.
Die Meldung blendet sich nach vier Sekunden selbst aus.

Fehler bei htmx-Anfragen (403, 404, 500, keine Verbindung) erscheinen ohne
eigenes Zutun als Meldung — siehe `htmx:responseError` in `layouts/base.blade.php`.

---

## 7. Ein Formular mit Fehlern zurückgeben

Das erledigt Laravel. Der Service wirft eine `ValidationException`, Laravel
leitet zurück, legt die Fehler in `$errors` und die Eingaben in `old()`:

```php
// Controller — kein try/catch nötig
public function store(Request $request): RedirectResponse
{
    $this->projects->create($request->user()->id, $request->only('name', 'description'));

    return redirect('/projects')->with('toast', ['kind' => 'success', 'message' => 'Angelegt.']);
}
```

```blade
<form method="post" action="/projects" class="card-body stack">
  @csrf
  <x-form-error />

  <div class="field">
    <label class="label" for="name">Name</label>
    <input class="input @error('name') input-error @enderror"
           id="name" name="name" value="{{ old('name') }}" required>
    @error('name') <p class="error">{{ $message }}</p> @enderror
  </div>
</form>
```

**Eine Regel, die kein Feld betrifft** (etwa „letzter Administrator"), wirft
der Service genauso — mit einem passenden Schlüssel:

```php
throw ValidationException::withMessages(['role' => 'Das ist der letzte aktive Administrator.']);
```

**Zwei Formulare auf einer Seite** brauchen getrennte Fehler-Taschen, sonst
erscheint ein Fehler bei beiden:

```php
try {
    $this->users->changePassword($user, $password);
} catch (ValidationException $e) {
    throw $e->errorBag('password');
}
```

```blade
<x-form-error bag="password" />
```

Vollständiges Beispiel: `AccountController` und `pages/account.blade.php`.

---

## 8. Einen Endpunkt zur JSON-API

Controller in `app/Http/Controllers/Api/V1/`, Berechtigung über einen **Scope**
an der Route. Nicht über eine Rolle — hinter einem Token steht kein Benutzer.

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NoteService;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    public function __invoke(NoteService $notes): JsonResponse
    {
        return response()->json(['data' => ['notes' => $notes->listAll()->count()]]);
    }
}
```

Route in `routes/api.php`:

```php
Route::get('/statistics', StatisticsController::class)->middleware('scope:notes:read');
```

Beachten:

- Erfolgreiche Antworten immer unter `data`. Mit einer Resource
  (`new NoteResource($note)`, `NoteResource::collection($notes)`) passiert das
  von selbst.
- Die **systemweite** Fassung der Service-Funktionen benutzen (`listAll`, `get`),
  nicht die `Owned`-Fassung. Ein Maschinen-Client arbeitet nicht aus der Sicht
  einer Person. `$request->user()` ist hier immer `null`.
- Wird ein Besitzer gebraucht, kommt er aus dem Anfragekörper — siehe `store`
  in `Api/V1/NoteController.php`.
- Fehler nicht selbst formatieren: Exceptions werden in `bootstrap/app.php` zu
  `{"error": {"code": …}}`.
- Endpunkt samt nötigem Scope in [docs/API.md](API.md) ergänzen.

Braucht der Endpunkt nur ein gültiges Token ohne bestimmte Berechtigung:

```php
Route::get('/info', InfoController::class)->middleware('api.token');

// im Controller:
$token = ApiToken::fromRequest($request);
```

---

## 9. Einen neuen Scope

Am Beispiel `reports:read`. Zwei Stellen:

**a) `app/Enums/Scope.php`** — Fall, Beschriftung und Beschreibung:

```php
case ReportsRead = 'reports:read';

// in label():
self::ReportsRead => 'Berichte lesen',

// in description():
self::ReportsRead => 'Auswertungen abrufen',
```

PHP meldet eine fehlende Stelle in `match`, sobald der Fall benutzt wird;
Larastan schon vorher. Das Kontrollkästchen im Token-Formular erscheint
automatisch.

**b) Routen** mit `->middleware('scope:reports:read')` versehen und in
[docs/API.md](API.md) beschreiben.

Ein Tippfehler an der Route (`scope:reports:reed`) scheitert laut mit einem
Fehler, nicht still mit 403.

> Bestehende Tokens bekommen den neuen Scope **nicht** nachträglich. Das ist
> Absicht: Eine neue Berechtigung soll ausdrücklich vergeben werden. Wer sie
> braucht, stellt ein neues Token aus.

---

## 10. Eine vierte Rolle

Drei Stellen, in dieser Reihenfolge:

**a) Migration** — die Spalte ist ein Postgres-`CHECK` über die erlaubten Werte:

```bash
php artisan make:migration add_editor_role
```

```php
public function up(): void
{
    DB::statement('ALTER TABLE users DROP CONSTRAINT users_role_check');
    DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('user', 'editor', 'moderator', 'admin'))");
}
```

**b) `app/Enums/Role.php`** — Fall ergänzen, `rank()` und `label()` anpassen.
Die Reihenfolge der Ränge bestimmt, wer wohin darf:

```php
case Editor = 'editor';

// rank():  User => 1, Editor => 2, Moderator => 3, Admin => 4
// label(): self::Editor => 'Redaktion',
```

**c) Routengruppe** in `routes/web.php`:

```php
Route::middleware(['auth', 'role:editor'])->group(…);
```

Die Auswahl im Benutzerformular zeigt die neue Rolle von selbst
(`Role::cases()`).

---

## 11. Eine neue Umgebungsvariable

Am Beispiel `UPLOAD_MAX_MB`, der größten erlaubten Dateigröße.

**a) `config/`** — in eine passende Datei oder eine neue, z. B. `config/uploads.php`:

```php
<?php

return [
    // Größte erlaubte Upload-Größe in Megabyte.
    'max_mb' => (int) env('UPLOAD_MAX_MB', 10),
];
```

**b) Benutzung** — überall sonst nur über `config()`:

```php
$max = config('uploads.max_mb');
```

> **Nie `env()` außerhalb von `config/`.** Nach `php artisan config:cache`
> (im Container bei jedem Start) liefert `env()` dort `null`. Lokal fällt das
> nicht auf — erst in Produktion.

**c) `.env.example`** — mit Kommentar eintragen. Werte mit `#`, Leerzeichen
oder `< >` **in Anführungszeichen**, sonst schneidet phpdotenv sie ab.

**d) `compose.yaml`** — unter `app.environment` ergänzen, falls im Container nötig.

**e) Systemseite** — betriebsrelevante Werte in `resources/views/admin/system.blade.php`
anzeigen, samt Variablennamen. Geheimnisse (Passwörter, Schlüssel) **nie**
anzeigen, höchstens „gesetzt / nicht gesetzt".

**f) Prüfen beim Start** (optional) — muss ein Wert ein bestimmtes Format haben,
in `AppServiceProvider::boot()` prüfen und mit einer klaren Meldung abbrechen.
Vorbild: `Branding::assertValid()`.

---

## 12. Eine E-Mail verschicken

**a) Mail-Klasse**:

```bash
php artisan make:mail WelcomeMail
```

```php
class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Willkommen bei '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome',
            text: 'mail.welcome-text',
            with: ['brand' => Branding::current()],
        );
    }
}
```

**b) HTML-Fassung** — `resources/views/mail/welcome.blade.php`:

```blade
@extends('mail.layout')
@section('subject', 'Willkommen')

@section('content')
<p style="margin:0 0 16px;">Hallo {{ $user->name }},</p>
<p style="margin:0;">dein Konto bei {{ $brand->name }} ist eingerichtet.</p>
@endsection
```

In Mails **nur Inline-Styles** — Mailprogramme laden kein Stylesheet, und
Tailwind-Klassen bedeuten dort nichts.

**c) Textfassung** — `resources/views/mail/welcome-text.blade.php`:

```blade
Hallo {{ $user->name }},

dein Konto bei {{ $brand->name }} ist eingerichtet.
```

Die Textfassung **immer** mitgeben. Manche Programme zeigen nur Text, und
Spamfilter werten reine HTML-Mails ab.

**d) Versenden:**

```php
// Wartet auf den Mailserver — Fehler kommen im Controller an.
Mail::to($user->email)->send(new WelcomeMail($user));

// Nach der Antwort — ein langsamer Mailserver hält keine Seite auf.
defer(fn () => Mail::to($user->email)->send(new WelcomeMail($user)));
```

| | wartet | Fehler | richtig für |
|---|---|---|---|
| `send(…)` | ja | kommen im Controller an | Der Benutzer muss wissen, ob es geklappt hat |
| `defer(fn () => …send(…))` | nein | nur im Log | Benachrichtigungen |

**Ansehen beim Entwickeln:** `just mail-up` startet Mailpit. Es nimmt jede Mail
an, verschickt nichts und zeigt alles unter <http://localhost:59025>.

**Testen:** `Mail::fake()`, dann `Mail::assertSent(WelcomeMail::class)` — Beispiel
in `tests/Feature/SystemTest.php`.

---

## 13. Einen Test schreiben

Tests stehen unter `tests/Feature/` (mit Datenbank und HTTP) und `tests/Unit/`
(reine Logik). Pest, Namen als deutscher Satz:

```php
<?php

use App\Models\Project;

it('zeigt nur die eigenen Projekte', function () {
    $user = actingAsUser();                       // Helfer aus tests/Pest.php
    Project::factory()->for($user)->create(['name' => 'Meins']);
    Project::factory()->create(['name' => 'Fremd']);

    $this->get('/projects')
        ->assertOk()
        ->assertSee('Meins')
        ->assertDontSee('Fremd');
});

it('antwortet per htmx nur mit dem Fragment', function () {
    actingAsUser();

    $this->withHeader('HX-Request', 'true')
        ->post('/projects', ['name' => 'Neu'])
        ->assertOk()
        ->assertDontSee('<html', false);
});
```

Jeder Feature-Test beginnt mit einer leeren Datenbank (`RefreshDatabase`).
Helfer: `actingAsUser()`, `actingAsAdmin()`, Factories mit `->admin()`,
`->moderator()`, `->inactive()`.

Einzelne Datei laufen lassen: `php artisan test tests/Feature/ProjectsTest.php`,
einzelnen Test: `php artisan test --filter="eigenen Projekte"`.
