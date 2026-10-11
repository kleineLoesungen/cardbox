# Konten ohne E-Mail, Registrierung mit Freigabe

## Ziel

Konten bestehen nur aus Name, **Benutzername** und Passwort. Eine
E-Mail-Adresse gibt es nicht mehr. Jede Person kann sich unter `/register`
selbst ein Konto anlegen. Nutzbar wird es aber erst, wenn eine
Administratorin es **freigibt**. Administratorinnen können Konten auch
direkt anlegen; diese sind sofort nutzbar.

Voraussetzung für [vokabelkarten.md](vokabelkarten.md): Viele Schülerinnen
und Schüler ab 10 Jahren haben keine eigene E-Mail-Adresse.

## Nicht dabei

- **E-Mail in irgendeiner Form am Konto**, also auch kein „Passwort
  vergessen". Ein vergessenes Passwort setzt die Administratorin neu.
- **Benachrichtigung der Administratorin** über neue Registrierungen per
  Mail. Sie sieht wartende Konten in der Oberfläche (siehe unten).
- **Sammelanlage** vieler Konten (z. B. eine ganze Klasse aus einer Liste).
- **Einladungslinks oder Codes**, mit denen ein Konto ohne Freigabe startet.
- **CAPTCHA.** Gegen massenhafte Registrierung helfen hier Ratenbegrenzung
  und das automatische Aufräumen.
- **Selbst den Benutzernamen ändern.** Nur die Administratorin darf das.
- Die Testmail unter `/admin/system` bleibt erhalten. Der Empfänger wird
  dort von Hand eingetragen, da kein Konto eine Adresse hat.

## Verhalten

### Benutzername

- 3 bis 30 Zeichen, nur Kleinbuchstaben `a–z`, Ziffern, Punkt, Bindestrich
  und Unterstrich; beginnt mit einem Buchstaben.
- Eingaben werden vor der Prüfung getrimmt und kleingeschrieben
  („Lena.M " → „lena.m"). Umlaute und ß werden nicht still umgewandelt;
  die Meldung nennt die erlaubten Zeichen und ein Beispiel.
- Eindeutig im ganzen System.
- Nur die Person selbst und Administratorinnen sehen ihn. Anderen zeigt die
  App den Namen (z. B. „von Lena M." bei geteilten Stapeln).

### Registrieren (`/register`)

- Öffentlich erreichbar, solange man nicht angemeldet ist. Link „Konto
  anlegen" auf der Anmeldeseite.
- Felder: **Name** („So sehen dich andere"), **Benutzername** („Damit
  meldest du dich an"), **Passwort**, **Passwort wiederholen**. Unter dem
  Passwort ein Knopf **„Passwort vorschlagen"** (siehe unten).
- **Erstes Konto der Installation:** wird Administrator, ist sofort
  freigegeben und angemeldet. Das ist die bisherige Ersteinrichtung.
- **Jedes weitere Konto:** Rolle Benutzer, **wartet auf Freigabe**. Es gibt
  keine automatische Anmeldung. Die Seite zeigt „Fertig! Sobald eine
  Administratorin dein Konto freigibt, kannst du dich anmelden. Merk dir
  deinen Benutzernamen: **lena.m**".
- Benutzername vergeben → Meldung am Feld („Diesen Namen gibt es schon.
  Versuch zum Beispiel lena.m2."). Der Vorschlag entsteht nach derselben
  Regel wie in der Verwaltung.
- **Ratenbegrenzung:** höchstens 5 Registrierungen pro IP-Adresse und
  Stunde (Laravel-`throttle`-Middleware an der POST-Route). Darüber hinaus
  kommt die bestehende 429-Seite. Hinter einem Proxy gilt wie bei der
  Anmeldung `TRUST_PROXY`.

### Anmelden (`/login`)

- Felder **Benutzername** (`autocapitalize="none"`, `autocorrect="off"`,
  `spellcheck="false"`, `autocomplete="username"`) und Passwort.
- Unbekanntes Konto oder falsches Passwort: „Benutzername oder Passwort
  stimmt nicht." (verrät nicht, ob das Konto existiert).
- Erst **nach** richtigem Passwort unterscheidet die Meldung:
  - wartet auf Freigabe: „Dein Konto ist noch nicht freigegeben. Frag eine
    Administratorin."
  - gesperrt: „Dieses Konto ist gesperrt." (wie bisher)
  Beides zählt nicht als Fehlversuch und meldet nicht an.
- **Anmeldebremse** unverändert, Schlüssel jetzt `user:<normalisierter
  Benutzername>` statt `email:…`, dazu wie bisher `ip:<adresse>`. Die
  Prüfung läuft weiterhin vor der Passwortprüfung. Alte Zeilen `email:…` in
  `login_attempts` laufen von selbst aus.

### Freigeben (`/admin/users`)

- Oben ein eigener Abschnitt **„Warten auf Freigabe (2)"**, nur wenn es
  welche gibt. Je Zeile: Name, Benutzername, registriert am, Knöpfe
  **„Freigeben"** und **„Ablehnen"**.
  - Freigeben: Konto wird nutzbar (`approved_at` = jetzt, `approved_by` =
    Administratorin), Meldung „lena.m ist freigegeben".
  - Ablehnen: Rückfrage, dann wird das Konto gelöscht.
- In der Navigation hängt am Eintrag „Benutzer" eine Plakette mit der Zahl
  wartender Konten, ebenso am Tab „Benutzer" der unteren Leiste. (Wandert
  „Benutzer" mit den Vokabelkarten ins „Mehr"-Blatt, zieht die Plakette mit.)
- **Aufräumen:** Nicht freigegebene Konten werden nach 30 Tagen gelöscht.
  Das läuft beim Aufruf von `/admin/users` mit (kein Cron, kein Worker im
  Container). Der Hinweis steht im Abschnitt: „Wird nach 30 Tagen
  automatisch gelöscht".
- Wartende Konten zählen nicht als aktive Administratoren und erscheinen
  nicht in der normalen Benutzerliste.

### Konten anlegen und bearbeiten (Administratorin)

- **Neues Konto:** Name, Benutzername, Rolle, Passwort. Ist sofort
  freigegeben.
- **Vorschlag für den Benutzernamen** aus dem Namen: Kleinbuchstaben,
  Umlaute umschreiben (ä→ae, ö→oe, ü→ue, ß→ss), Leerzeichen → Punkt,
  andere Zeichen entfernen; ist er vergeben, wird eine Zahl angehängt
  („lena.mueller2"). Kommt per htmx beim Verlassen des Namensfelds
  (`UserService::suggestUsername`), nur für Administratorinnen.
- **„Passwort vorschlagen"** (Verwaltung und Registrierung): erzeugt ein
  merkbares Passwort aus drei deutschen Wörtern und einer Zahl, z. B.
  „Wolke-Tiger-Apfel-47" (mind. 12 Zeichen, erfüllt die bestehende
  Mindestlänge). Die Wortliste mit rund 300 kurzen, kindgerechten Wörtern
  liegt im Code. Erzeugt wird es serverseitig mit `random_int`, geliefert
  als htmx-Fragment, das das Feld befüllt und sichtbar macht.
- **Bearbeiten:** Name, Benutzername, Rolle, gesperrt ja/nein, neues
  Passwort. Wie bisher.
- Benutzerliste: Spalte „Benutzername" statt „E-Mail"; Suche über Name und
  Benutzername.

### Eigenes Konto (`/account`)

- Name änderbar, Passwort änderbar (wie bisher).
- Benutzername nur angezeigt („Ändern kann ihn eine Administratorin").

### JSON-API

- `GET /api/v1/users` liefert `username` statt `email`. Das ist eine
  **inkompatible Änderung**: Sie wird in `docs/API.md` und im Changelog
  genannt.

## Berechtigungen

| Route | Gruppe |
|---|---|
| `GET/POST /register` | öffentlich (`guest`), POST zusätzlich `throttle:5,60`; steht bereits in `PUBLIC_ROUTES` |
| `POST /password-suggestion` | öffentlich (`throttle:30,1`), für Registrierung **und** Verwaltung; **neu in `PUBLIC_ROUTES`**, bewusst: liefert nur ein Zufallspasswort, keine Daten |
| `POST /admin/users/{user}/approve`, `POST /admin/users/{user}/reject` | `role:admin` |
| `GET /admin/users/suggest-username` | `role:admin` |

Die Unterscheidung „erstes Konto → Administrator, sonst wartend" steht im
**Service** (`UserService::register`), nicht im Template. Ein von Hand
abgeschicktes Formular landet also genauso in der Warteschlange.

## Datenmodell

**Migration `2026_10_11_090000_replace_email_with_username_on_users_table.php`**

1. Spalten `username` (string 30, zunächst nullable), `approved_at`
   (timestampTz, nullable) und `approved_by` (foreignUuid auf `users`,
   nullable, `nullOnDelete`) anlegen.
2. Bestehende Konten:
   - `username` aus dem Teil der E-Mail vor dem `@`, nach den Regeln oben
     bereinigt, bei Kollision mit Zahl ergänzt, notfalls `user` + Zahl.
     Läuft in PHP in der Migration und hängt nicht am Model, damit spätere
     Model-Änderungen die Migration nicht brechen.
   - `approved_at` = `created_at`. Bestehende Konten gelten als freigegeben.
3. `username` auf `NOT NULL` + `unique`.
4. Spalte `email` samt Unique-Index entfernen.

`down()`: `email` als nullable Spalte zurück, `username`, `approved_at` und
`approved_by` entfernen. Die alten Adressen sind dabei verloren; das steht
im Kommentar der Migration und im Changelog.

`approved_by` mit `nullOnDelete` statt `cascadeOnDelete`: Wird die
freigebende Administratorin gelöscht, darf das freigegebene Konto nicht
mitverschwinden. Das ist eine **bewusste Abweichung** von der CLAUDE.md-Regel.

Changelog mit `Migration:`-Zeile und Hinweis für Betreiber: „Bestehende
Konten melden sich ab jetzt mit dem Benutzernamen an. Er wurde aus der
E-Mail gebildet und steht in der Benutzerverwaltung. Bitte vor dem Update
den Benutzern mitteilen."

## Betroffene Stellen

- `app/Services/UserService.php`: `register()` (erstes Konto vs. wartend),
  `create()` (Admin, freigegeben), `approve()`, `reject()`,
  `pruneUnapproved()`, `pendingCount()`, `findByCredentials()` per
  Benutzername, Validierung `username`, `suggestUsername()`,
  `suggestPassword()`, Suche; E-Mail-Logik entfällt.
- `app/Services/LoginThrottle.php`: `keys()` mit Benutzername.
- `app/Models/User.php`: `username`, `approved_at`, `approved_by` statt
  `email`; `isApproved()`; Normalisierung des Benutzernamens.
- `app/Http/Middleware/LogoutInactiveUsers.php`: meldet auch nicht
  freigegebene Konten ab (z. B. falls eine Freigabe zurückgenommen wird).
- `app/Http/Controllers/AuthController.php`: Feld `username`, Registrierung
  für alle, Seite „wartet auf Freigabe", Passwortvorschlag.
- `app/Http/Controllers/Admin/UserController.php`: Freigeben, Ablehnen,
  Vorschläge.
- `app/Http/Controllers/AccountController.php`,
  `Admin/SystemController.php` (Empfänger nicht mehr vorbelegt).
- `app/Http/Resources/UserResource.php`: `username` statt `email`.
- `resources/views/pages/login.blade.php`, `register.blade.php`, neue
  `registered.blade.php`, `account.blade.php`, `admin/users.blade.php`,
  `admin/user.blade.php`, `admin/system.blade.php`,
  `components/nav-links.blade.php`, `layouts/app.blade.php` (Plakette).
- `routes/web.php`, `tests/Feature/RouteProtectionTest.php` (ein neuer
  öffentlicher Eintrag, begründet).
- `database/factories/UserFactory.php` (+ Zustand `pending()`),
  `database/seeders/DatabaseSeeder.php` (`anna`, `moritz`, `bea`, dazu ein
  wartendes Konto `neu.nutzer`).
- `lang/de/validation.php`: Attribut `username` („Benutzername"),
  Meldung für das Format.
- `docs/API.md`, `docs/DEPLOY.md` (Checkliste: „Erstes Konto über /register
  anlegen"), `README.md`, `CHANGELOG.md`.
- **`CLAUDE.md`**: Abschnitt „Selbstregistrierung ist geschlossen" wird zu
  „Registrierung braucht Freigabe". Er beschreibt die Regel im Service,
  die Ratenbegrenzung und das Aufräumen. Ebenso der Punkt unter „Was hier
  nicht passieren soll" und der Abschnitt zur Anmeldebremse (Schlüssel).

## Tests

**Registrieren**
- erstes Konto wird Administrator, ist freigegeben und angemeldet
- weiteres Konto ist Benutzer, wartet, ist **nicht** angemeldet
- Formular mit `role=admin` von Hand abgeschickt → trotzdem Benutzer und
  wartend
- doppelter Benutzername („Lena" vs. „lena") wird abgewiesen, mit Vorschlag
- ungültige Benutzernamen: zu kurz, zu lang, `@`, Umlaut, Leerzeichen,
  beginnt mit Ziffer
- Passwörter stimmen nicht überein → Fehler
- sechste Registrierung von derselben IP innerhalb einer Stunde → 429
- angemeldete Person ruft `/register` auf → Weiterleitung

**Anmelden**
- mit Benutzername, Groß-/Kleinschreibung und Rand-Leerzeichen egal
- wartendes Konto mit richtigem Passwort: eigene Meldung, nicht angemeldet,
  kein Fehlversuch gezählt
- wartendes Konto mit falschem Passwort: neutrale Meldung, Fehlversuch
  gezählt
- unbekanntes Konto und falsches Passwort ergeben dieselbe Meldung
- gesperrter Versuch erhöht den Zähler nicht und prüft kein Passwort
  (bestehende Tests auf Benutzername umgestellt)

**Freigabe**
- Administratorin gibt frei → Anmeldung möglich, `approved_by` gesetzt
- Ablehnen löscht das Konto
- Freigeben/Ablehnen als Moderatorin oder Benutzer → 403
- Konten älter als 30 Tage ohne Freigabe werden beim Aufruf der
  Verwaltung gelöscht, jüngere und freigegebene nicht
- wartende Konten zählen nicht als aktive Administratoren
- Plakette zeigt die Zahl wartender Konten nur Administratorinnen

**Verwaltung und Konto**
- Administratorin legt Konto an → sofort freigegeben
- Vorschlag: Umlaute umgeschrieben, Kollision bekommt Zahl; nur für Admins
- Passwortvorschlag erfüllt die Mindestlänge und unterscheidet sich bei
  jedem Aufruf
- Benutzer kann eigenen Benutzernamen über `/account` nicht ändern
- Testmail ohne Empfänger → Validierungsfehler

**Migration und API**
- bestehende Konten erhalten eindeutige Benutzernamen aus der E-Mail, auch
  bei `anna@a.de` und `anna@b.de`, und gelten als freigegeben
- API liefert `username`, kein `email`

## Annahmen und Risiken

- **Mindestlänge des Passworts bleibt 12.** Für Kinder lang, deshalb der
  Passwortvorschlag aus Wörtern. Ohne ausdrücklichen Wunsch nicht kürzer.
- **Benutzernamen lassen sich über `/register` erraten** („gibt es schon").
  Das ist bei Registrierungen unvermeidbar und hier vertretbar: Der Name
  allein öffnet nichts, die Anmeldebremse schützt das Passwort.
- **Offene Registrierung zieht Spam an**, wenn die Installation öffentlich
  im Netz steht. Gegenmittel: Ratenbegrenzung, Freigabe, Aufräumen nach 30
  Tagen. Reicht das nicht, wäre ein Schalter `REGISTRATION_ENABLED` der
  nächste Schritt (nicht Teil dieser Spezifikation).
- **Bestehende Installationen** müssen ihren Benutzern die neuen
  Benutzernamen mitteilen. Steht im Changelog.
- **Das Aufräumen** hängt am Besuch der Benutzerverwaltung. Besucht sie
  niemand, bleiben wartende Konten liegen. Sie stören aber nicht, weil sie
  sich nicht anmelden können.
