# Aufbau

Warum das Projekt so geschnitten ist, wie es geschnitten ist.

---

## Die Grundentscheidung: HTML vom Server

Es gibt kein Frontend-Framework. Der Server erzeugt HTML, htmx tauscht bei
Bedarf einzelne Ausschnitte aus.

**Was das bringt:**

- Eine Sprache, eine Werkzeugkette, ein Prozess. Kein `node_modules`, kein
  zweiter Build, kein Vertragsbruch zwischen Frontend und Backend.
- Ein Feature lebt an einem Ort statt verteilt über API-Endpunkt, Typdefinition,
  Client-Funktion und Komponente.
- KI-Assistenten müssen nur ein Muster kennen, nicht zwei.

**Was das kostet:**

Stark interaktive Oberflächen — Drag-and-drop, Live-Zeichenflächen,
Offline-Betrieb — sind mühsamer als mit einem Framework. Für Formulare,
Listen, Übersichten und Verwaltungsoberflächen ist der Tausch gut.

---

## Warum Laravel — und warum nicht alles davon

Laravel kennen Sprachmodelle so gut wie kaum ein anderes Web-Framework.
Migrationen, Validierung, Sitzungen, Mail, Tests — alles folgt Konventionen,
die ein Assistent richtig rät. Das ist der Hauptgrund.

Das Ökosystem drängt aber Richtung Node (Vite), Livewire und Starter-Kits wie
Breeze, die hunderte Dateien erzeugen. Das Kit nimmt davon bewusst nur den Kern:
Routing, Eloquent, Blade, Validierung, Mail. Was fehlt, steht in
[CLAUDE.md](../CLAUDE.md#laravel-werkzeuge-die-hier-nicht-benutzt-werden)
samt Begründung.

---

## Die Schichten

```
                  HTTP-Anfrage
                       │
         ┌─────────────┴─────────────┐
         │                           │
app/Http/Controllers/     app/Http/Controllers/Api/V1/    ← dünn: Ein- und Ausgabe
HTML + htmx               JSON (Resources)
Session-Cookie            Bearer-Token
routes/web.php: Rollen    routes/api.php: Scopes
         │                           │
         └─────────────┬─────────────┘
                       │
                 app/Services/                            ← Fachlogik + Validierung
      NoteService · UserService · ApiTokenService            Eigentumsprüfung, Regeln
                       │
              app/Models/ (Eloquent)
                       │
                  PostgreSQL
```

**Die Regel:** In `app/Services/` gibt es kein `Request`, keine `response()`,
kein `view()`, kein `redirect()`.

Der Nutzen ist unmittelbar: `POST /notes` (HTML) und `POST /api/v1/notes` (JSON)
rufen dieselbe Funktion `NoteService::create` auf. Die Validierung existiert
einmal. Ohne diese Trennung driften die beiden Wege auseinander, und einer von
beiden bekommt irgendwann eine Prüfung nicht mit.

### Warum Validierung im Service und nicht in FormRequests?

FormRequests sind in Laravel üblich — aber sie hängen am HTTP-Request. Die
JSON-Schnittstelle bräuchte eine zweite Klasse mit denselben Regeln, oder die
Web-Seite müsste die API-Klasse mitbenutzen. Beides lädt dazu ein, dass die
Regeln auseinanderlaufen.

`Validator::make(...)->validate()` im Service wirft dieselbe
`ValidationException` wie ein FormRequest. Laravel behandelt sie überall
richtig: Weiterleitung mit Fehlern im Browser, 422 in der API, eine Meldung bei
htmx (`bootstrap/app.php`).

### Warum Models ohne Regeln?

Eloquent-Modelle können alles — Validierung in `saving`-Events, Geschäftsregeln
in Methoden, Abfragen in Scopes. Genau deshalb landet dort sonst alles. Im Kit
beschreibt ein Modell Daten und kleine Ableitungen (`initials()`, `isValid()`);
Regeln wie „der letzte Administrator bleibt" stehen im Service, wo man sie sucht.

---

## Autorisierung an der Route

Statt einer Prüfung im Controller steht die Anforderung an der Routengruppe:

```php
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(…);
```

Die Middleware lädt den Benutzer, prüft die Rolle und weist ab, bevor der
Controller läuft. Eine Prüfung im Controller ließe sich vergessen — und niemand
merkt es, weil die Seite ja funktioniert.

PHP hat kein Typsystem, das eine vergessene Gruppe verhindern könnte (das
Rust-Kit erzwingt es über Extractor-Typen). Den Ersatz liefert
`tests/Feature/RouteProtectionTest.php`: Er geht **alle** Routen durch und
schlägt fehl, sobald eine ohne `auth`, `api.token` oder `scope:` dasteht, die
nicht ausdrücklich als öffentlich gelistet ist. Zusätzlich muss alles unter
`/admin` die Rolle `admin` verlangen, und keine API-Route darf Sitzung oder Rolle
benutzen.

Rollen sind hierarchisch (`Role::rank()`): Ein Administrator kommt überall
hin, wo ein Moderator hindarf.

---

## Zwei Zugänge, zwei Berechtigungsmodelle

|  | HTML (`routes/web.php`) | JSON (`routes/api.php`) |
|---|---|---|
| Wer | ein **Mensch** | ein **fremdes System** |
| Nachweis | Session-Cookie | `Authorization: Bearer sk_…` |
| Berechtigung | **Rolle** (`user`/`moderator`/`admin`) | **Scopes** (`notes:read`, …) |
| Sichtbare Daten | nur die eigenen | systemweit |
| Zustand | Sitzungszeile in Postgres | zustandslos, ein Lookup |
| Beenden | Abmelden, sofort wirksam | Token widerrufen |
| CSRF | Schutz nötig | kein Ziel — Browser senden den Header nie von selbst |

Der entscheidende Unterschied: **Hinter einem Token steht kein Benutzer.** Es
gehört der Installation, nicht einer Person, und erbt deshalb keine Rolle. Wo
ein Besitzer gebraucht wird, gibt die Schnittstelle ihn ausdrücklich mit.

Deshalb auch kein Laravel Sanctum: Sanctum-Tokens gehören immer einem Modell
(`tokenable`) und handeln in dessen Namen. Das ist genau das Modell, das hier
vermieden werden soll.

Die Folge im Code: Die Service-Funktionen gibt es in zwei Ausführungen — eine,
die nach Besitzer filtert (`getOwned`), und eine systemweite (`get`). Die
Weboberfläche darf nur die erste benutzen.

---

## CSRF

Laravel 13 prüft verändernde Anfragen zweistufig:

1. `Sec-Fetch-Site: same-origin` — moderne Browser sagen selbst, woher eine
   Anfrage kommt. Das genügt.
2. Fehlt der Kopf (ältere Browser), muss das Token aus `@csrf` bzw. dem
   `X-CSRF-TOKEN`-Kopf passen.

Dazu kommt das Session-Cookie mit `SameSite=Lax`: Browser senden es bei
seitenfremden POST-Anfragen gar nicht erst mit.

htmx schickt das Token über `hx-headers` am `<body>` bei jeder Anfrage mit —
auch für Formulare, an die beim Schreiben niemand gedacht hat.

---

## Fehler

Ein Fehler, drei Darstellungen — gewählt in `bootstrap/app.php`:

| Anfrage | `ValidationException` | 404 / 403 | Serverfehler |
|---|---|---|---|
| Browser | zurück mit `$errors` und `old()` | Fehlerseite `errors/404` | `errors/5xx`, Details nur im Log |
| htmx | 422 + Meldung als OOB-Fragment | Meldung per `htmx:responseError` | Meldung |
| API | `422 {"error": {"code": "validation_failed", …}}` | `{"error": {"code": "not_found"}}` | `{"error": {"code": "internal_error"}}` |

Nach außen geht bei 5xx nie mehr als „Es ist ein interner Fehler aufgetreten."
Datenbankfehler und Stacktraces sind nichts für Fremde — vorausgesetzt,
`APP_DEBUG=false` in Produktion.

---

## Konfiguration

Alles kommt aus Umgebungsvariablen und wird **ausschließlich** in `config/*.php`
gelesen. Im Code steht `config('app.name')`, nie `env('APP_NAME')`. Grund:
Im Container läuft `php artisan config:cache`, danach liefert `env()` außerhalb
von `config/` schlicht `null`.

Zwei Entscheidungen dazu:

**Fehler beim Start, nicht im Betrieb.** Farben werden beim Hochfahren auf ihr
Format geprüft (`Branding::assertValid`). Das hat seinen Grund: phpdotenv liest
`PWA_THEME_COLOR=#3370c7` ohne Anführungszeichen als leeren Wert — das `#`
beginnt einen Kommentar. Ohne Prüfung bliebe die installierte App still grau.

**Kein Einstellungsmenü.** Name, Farben und Mailserver lassen sich in der
Oberfläche nur ansehen, nicht ändern (`/admin/system`). Es sind
Betriebsentscheidungen, die mit dem Deployment versioniert gehören. Außerdem
wäre die Kontrolle vorgetäuscht: Eine installierte App übernimmt einen neuen
Namen oder ein neues Icon je nach Gerät erst spät oder gar nicht.

---

## Strenge statt Überraschungen

PHP verzeiht viel — für einen Assistenten, der Code schreibt, ohne ihn
auszuführen, ist das eine Falle. Deshalb:

- **Eloquent Strict Mode** außerhalb der Produktion: Nachladen in Schleifen,
  nicht freigegebene Felder und Tippfehler in Attributnamen werfen eine
  Exception statt still `null` zu liefern.
- **Larastan Stufe 6** findet falsche Methodennamen, Typen und fehlende
  Rückgaben vor dem ersten Aufruf.
- **`just views`** kompiliert jedes Blade-Template — Syntaxfehler fallen im
  Check auf, nicht erst beim Klick auf die Seite.
- **Tests gegen echte Postgres** statt SQLite: `ILIKE`, JSONB, Check-Constraints
  und UUID-Fehler verhalten sich dort anders.

---

## E-Mail

Laravels `Mail` mit einer Klasse je Nachricht unter `app/Mail/`:

```php
Mail::to($adresse)->send(new TestMail(triggeredBy: $admin->name));
```

| Versandweg | wann | Wirkung |
|---|---|---|
| SMTP | `MAIL_MAILER=smtp` | echter Versand (lokal an Mailpit) |
| Log | `MAIL_MAILER=log` | Mail steht im Log, nichts wird verschickt |
| Array | Tests (`Mail::fake()`) | Mails werden gesammelt und lassen sich prüfen |

**Kein Queue-Worker.** Für „nicht auf den Mailserver warten" gibt es
`defer(fn () => Mail::…->send(…))`: Laravel verschickt nach dem Absenden der
Antwort. Das spart einen zweiten Prozess im Container. Wer später echte
Warteschlangen braucht (Wiederholungen, viele Mails), stellt
`QUEUE_CONNECTION=database` ein und startet `php artisan queue:work` als eigenen
Container.

---

## Installierbare App

`PwaController` liefert Manifest, Service Worker und Offline-Seite.

Der Service Worker liegt unter `/sw.js` und nicht unter `/js/`: Ein Worker
steuert nur Seiten unterhalb seines eigenen Pfads.

**Er speichert keine Seiten zwischen.** Nur Stylesheet, htmx und Icons werden
vorgehalten, ohne Netz erscheint eine Offline-Seite. Bei einer angemeldeten,
serverseitig gerenderten Anwendung hieße Seiten-Caching: veraltete Daten, oder
nach dem Abmelden die Inhalte des vorherigen Benutzers.

Die Cache-Version leitet sich aus dem Änderungsdatum von `app.css` und htmx ab.
Ändert sich eines davon, erkennt der Browser einen neuen Worker und verwirft den
alten Zwischenspeicher.

---

## Ausliefern

Ein mehrstufiges Dockerfile: Tailwind baut das Stylesheet in einer eigenen
Stufe, das Laufzeit-Abbild enthält FrankenPHP (PHP und Webserver in einem
Prozess), die Anwendung und die Abhängigkeiten ohne Entwicklungspakete. Kein
Node, kein Tailwind, kein nginx daneben.

Beim Start (`docker/entrypoint.sh`) werden Konfiguration, Routen und Templates
vorkompiliert und Migrationen ausgeführt — mit Sperre, damit mehrere Instanzen
gleichzeitig starten können.

Die Datenbank ist **extern** — der Container hält keinen Zustand und lässt sich
beliebig oft parallel starten. Sitzungen und Cache liegen in Postgres, nicht im
Dateisystem, also braucht es keine klebrigen Sitzungen im Loadbalancer.
