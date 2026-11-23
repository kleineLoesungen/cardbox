# Geführte Touren

Status: **umgesetzt** (23.11.2026, Version 2026.11.23).

Entscheidungen vom 23.11.2026:

- **Alle Konten** bekommen die Touren, auch bestehende.
- Eine Tour **startet automatisch** beim ersten Besuch einer Seite;
  „Überspringen“ ist immer da.
- **Alle Hauptseiten** in der ersten Ausbaustufe (Liste unten).
- **Neue Funktionen** zeigen später nur ihre neuen Schritte („Neu: …“).
- Technik: **Driver.js 1.10.2** als feste Datei, ohne npm. Die neueren
  1.11–1.14 waren beim Bauen keine zwei Wochen alt; das Paket wurde mit der
  Prüfsumme aus der npm-Registry geprüft und enthält keine Netzzugriffe.

## Ziel

Jede Hauptseite erklärt beim ersten Besuch in höchstens drei kurzen Schritten
die Funktionen, die man **nicht von selbst entdeckt** – das Offensichtliche
(„+ Karte“, „Suchen“) bleibt weg. Wer eine Seite kennt, sieht nichts mehr;
kommt später eine Funktion dazu, erscheint nur ihr Schritt.

## Nicht dabei

- Touren für Gäste ohne Konto (Duell-Gäste), Anmeldung, Registrierung,
  Produktseite.
- Moderation, Tokens, System (wenige Personen, die die Verwaltung kennen).
- Eine durchgehende Tour über mehrere Seiten („Jetzt tippe auf Stapel …“).
  Jede Seite hat ihre eigene kurze Tour.
- Videos, Animationen, Belohnungen für abgeschlossene Touren.
- Touren über die JSON-API oder MCP.
- Eigene Texte je Rolle auf derselben Seite. Unterschieden wird über die
  Seiten, die jemand überhaupt sieht (siehe Berechtigungen).

## Verhalten

### Ablauf

- Beim Aufruf einer Seite gibt der Server die **noch nicht gesehenen
  Schritte** dieser Seite mit. Sind welche da, startet die Tour nach dem
  Laden automatisch.
- Jeder Schritt hebt ein Element hervor (Rest abgedunkelt) und zeigt eine
  Sprechblase: Überschrift, ein bis zwei Sätze, „Zurück“ / „Weiter“ bzw.
  „Fertig“, dazu „1 von 3“ und ein ✕ zum Überspringen.
- **Nur sichtbare Ziele:** Ein Schritt, dessen Element auf der Seite gerade
  fehlt (z. B. „Lerne ich“-Liste bei einem neuen Konto ohne Stapel), wird
  ausgelassen und bleibt **offen** – er kommt, sobald es das Element gibt.
  Fehlen alle, startet keine Tour.
- **Gesehen** sind beim Schließen der Tour (Fertig oder ✕) **alle**
  Schritte dieser Tour – wer überspringt, will sie nicht noch einmal.
  Gemeldet wird im Hintergrund. Schlägt das fehl, kommt die Tour beim nächsten Mal wieder;
  eine Fehlermeldung gibt es nicht.
- **Neue Schritte:** Hat die Person auf dieser Seite schon Schritte gesehen,
  beginnt die Überschrift neuer Schritte mit „Neu: “.
- **Nicht stören:** Ist beim Laden ein Blatt offen (z. B. Fehler im
  Formular, `?new=1`, nach dem Teilen), wartet die Tour bis zum nächsten
  Seitenaufruf bzw. bis ein Blatt per Tipp geöffnet wird.
  Höchstens eine Tour gleichzeitig.
- **Nachgeladene Inhalte** (htmx, z. B. Suchergebnisse im Wörterbuch, die
  Rückseite in der Lernrunde): Schritte, deren Ziel erst danach erscheint,
  starten nach dem Nachladen als eigene kleine Tour.
- **Blätter** (Teilen-Fenster): Die Tour startet beim ersten Öffnen des
  Blatts, erst wenn es fertig hereingefahren ist. Ein offenes `<dialog>`
  liegt über allem; Abdunklung und Blase von Driver.js werden deshalb in
  das Blatt verschoben und neu platziert.
- **Zurücksetzen:** „Touren noch einmal zeigen“ auf der Hilfeseite und
  unter „Mein Konto“ – setzt alle Schritte auf ungesehen und führt zu
  „Heute“, wo die erste Tour gleich startet (Meldung: „Die Touren kommen
  jetzt wieder.“). Gilt nur für das eigene Konto; der Fortschritt beim
  Lernen bleibt unberührt.
- **Telefon:** Die Sprechblase steht in voller Breite mit 16 px Rand (unter
  40rem) und mittig zum Ziel; „Zurück“ fehlt im ersten Schritt; Knöpfe in
  voller Höhe der App-Knöpfe, Texte höchstens etwa 120 Zeichen. Die Höhe
  wählt Driver.js am Ziel (über oder unter ihm), deshalb kein eigener
  Abstand zur Wischleiste. Bei `prefers-reduced-motion` ohne Animation.
- **Aussehen:** über die Tokens (`bg-surface-raised`, `text-content`,
  `rounded-ui`, `btn`-Klassen), damit auch der Dunkelmodus stimmt.
- **Beschriftung:** „Weiter“, „Zurück“, „Fertig“, „1 von 3“,
  ✕ mit `aria-label="Tour überspringen"`. Mit der Tastatur bedienbar
  (Pfeiltasten, Esc).

### Die Schritte

Die Kennung (z. B. `heute.serie`) ist dauerhaft; sie wird gespeichert.
Ziel ist ein `data-tour="…"`-Attribut am Element, nie eine CSS-Klasse.
Texte sind Entwürfe in einfacher Sprache.

**Heute** (`/learn`, alle)

| Kennung | Ziel | Text |
|---|---|---|
| `heute.serie` | Wochenpunkte | **Deine Woche** – Grün heißt: Tagesziel geschafft. Die Zahl zeigt, wie oft. |
| `heute.los` | „Los geht's“ | **Alle Stapel gemischt** – Hier kommen Karten aus allen Stapeln, die du gerade lernst. |
| `heute.lerne-ich` | Liste „Lerne ich“ | **Nur diese Stapel kommen dran** – Einen Stapel pausierst du im Stapel selbst. |

**Lernrunde** (`/learn/round`, alle)

| Kennung | Ziel | Text |
|---|---|---|
| `runde.vorlesen` | Lautsprecher | **Vorlesen** – Tippe hier, dann hörst du, wie man das Wort spricht. |
| `runde.ehrlich` | „Gewusst“ / „Nochmal“ (nach dem Umdrehen) | **Sei ehrlich** – „Nochmal“ holt die Karte bald wieder. So merkst du sie dir. |
| `runde.aufhoeren` | ✕ | **Jederzeit aufhören** – Was du gelernt hast, bleibt gespeichert. |

**Stapel** (`/decks`, alle)

| Kennung | Ziel | Text |
|---|---|---|
| `stapel.entdecken` | Reiter „Entdecken“ | **Stapel von anderen** – Hier findest du geteilte Stapel, auch nach Schulbuch (ISBN). |
| `stapel.gruppen` | Überschrift „Lerne ich“ | **Lerne ich und Pausiert** – Pausierte Stapel kommen nicht unter „Heute“. |
| `stapel.neu` | „+ Neuer Stapel“ | **Eigener Stapel** – Zum Beispiel für die Vokabeln dieser Woche. |

**Eigener Stapel** (`/decks/{id}`, Besitzerin oder Eltern)

| Kennung | Ziel | Text |
|---|---|---|
| `stapel-eigen.menue` | ⋯ | **Mehr im Menü** – Ganze Liste einfügen, Antworten eintippen, auch rückwärts lernen. |
| `stapel-eigen.test` | „Für den Test üben“ | **Vor der Arbeit** – Alle Karten, bis du jede einmal gewusst hast. |
| `stapel-eigen.duell` | „Duell starten“ | **Freunde herausfordern** – Alle bekommen dieselben Wörter. |

**Stapel von anderen** (`/decks/{id}`, geteilt)

| Kennung | Ziel | Text |
|---|---|---|
| `stapel-fremd.lernen` | „Lernen“ | **Mitlernen** – Die Karten kommen unter „Heute“, Korrekturen kommen von selbst. |
| `stapel-fremd.fehler` | erste Karte | **Fehler entdeckt?** – Karte antippen und melden. |
| `stapel-fremd.fassung` | ⋯ | **Selbst ändern** – Unter ⋯ legst du eine eigene Fassung an. |

**Wörterbuch** (`/dictionary`, alle)

| Kennung | Ziel | Text |
|---|---|---|
| `woerterbuch.suche` | Suchfeld | **Ohne Akzente** – „eleve“ findet „élève“, „Strasse“ findet „Straße“. |
| `woerterbuch.lernen` | „Lernen“ beim ersten Ergebnis (nach der Suche) | **Nur dieses Wort lernen** – Es kommt unter „Heute“ dran, ohne den ganzen Stapel. |
| `woerterbuch.mehr` | „Mehr …“ beim ersten Ergebnis | **Kopieren oder melden** – Unter „Mehr …“ kopierst du das Wort in einen eigenen Stapel oder meldest einen Fehler. |

**Duelle** (`/duels`, alle)

| Kennung | Ziel | Text |
|---|---|---|
| `duelle.code` | Code-Feld und „Mitmachen“ | **Code bekommen?** – Hier eingeben und mitspielen. |
| `duelle.gaeste` | ohne Ziel (Blase in der Mitte) | **Auch ohne Konto** – Freunde ohne Konto spielen mit dem Code als Gast mit. |
| `duelle.liste` | „Meine Duelle“ | **Rangliste** – Hier siehst du laufende und fertige Duelle. |

**Mein Konto** (`/account`, alle)

| Kennung | Ziel | Text |
|---|---|---|
| `konto.lernplan` | Karte „Lernplan“ | **Lernplan** – Wie oft Karten wiederkommen. |
| `konto.runde` | „Karten je Runde“ | **Kürzere Runden** – Weniger Karten pro Runde sind schneller geschafft. |
| `konto.erinnerung` | Karte „Erinnerung“ | **Erinnerung** – Das Handy erinnert dich, wenn Karten warten. |

**Kind** (`/children/{id}`, Eltern)

| Kennung | Ziel | Text |
|---|---|---|
| `kind.stapel` | „Stapel von … bearbeiten“ | **Stapel bearbeiten** – Wie dein Kind. Teilst du einen mit dir selbst, lernst du mit. |
| `kind.geteilt` | „Mit … geteilt“ | **Was andere schicken** – Stapel für dein Kind kannst du ablehnen. |
| `kind.knifflig` | knifflige Wörter | **Zum Abfragen** – Ganz unten: Wörter, die oft falsch waren. |

**Teilen-Fenster** (Blatt auf der Stapelseite, nur wer teilen darf)

| Kennung | Ziel | Text |
|---|---|---|
| `teilen.alle` | „Mit allen teilen“ | **Für alle** – Alle finden den Stapel unter „Entdecken“. |
| `teilen.personen` | Benutzername | **Nur für bestimmte** – Zum Beispiel deine Klasse. |
| `teilen.rechte` | Hinweis Urheberrecht | **Nur Eigenes** – Karten von anderen lassen sich nicht weiterteilen. |

**Benutzer** (`/admin/users`, Admin)

| Kennung | Ziel | Text |
|---|---|---|
| `admin.freigabe` | erste wartende Registrierung | **Freigeben** – Alter wählen, bei unter 18 Eltern eintragen. |
| `admin.eltern-fehlen` | Hinweis „Elternteil fehlt“ | **Eltern fehlen** – Ohne Eltern kann niemand für das Kind teilen. |
| `admin.konto` | erste Zeile der Liste | **Konto öffnen** – Dort änderst du Alter und Eltern und bestätigst „nur zuschauen“. |

## Berechtigungen

- Alle Routen in der Gruppe `auth`; nur das **eigene** Konto
  (`$request->user()`), keine IDs in der URL.
- Wer welche Tour sieht, ergibt sich aus den Seiten, die die Person ohnehin
  erreicht: „Kind“ nur Eltern, „Benutzer“ nur Admins, Teilen-Fenster nur
  wer teilen darf (eltern-konten.md). Keine zusätzliche Rollenprüfung in der
  Tour.
- Gemeldete Kennungen werden gegen die Liste der Schritte geprüft;
  unbekannte werden still verworfen. Höchstens 20 je Meldung.

## Datenmodell

Migration `2026_11_23_000000_add_tour_steps_seen_to_users.php`:

| Tabelle | Spalte | |
|---|---|---|
| `users` | `tour_steps_seen` jsonb, nicht null, Vorgabe `'[]'` | Kennungen der gesehenen Schritte |

- Bestehende Konten bekommen `[]` und sehen damit alle Touren.
- Nicht fillable; gesetzt im Service per `forceFill`.

## Betroffene Stellen

- **public/js/driver-1.10.2.js, public/css/driver-1.10.2.css** (Lizenz in
  `driver-1.10.2.LICENSE.txt`): Driver.js, Version im Dateinamen wegen des
  Service Workers. **CLAUDE.md**: Zeile in „Festgenagelte Versionen“.
- **app/Support/Tours.php:** die Schritte (Kennung, Ziel, Titel, Text) je
  Seite als Konstante, wie `Navigation::SECTIONS`.
- **app/Services/TourService.php:** `pending(User, pages)` (offene Schritte
  mehrerer Seiten, mit „Neu“-Kennzeichen), `markSeen(User, ids)` (geprüft,
  ohne Doppelte, gesperrte Zeile), `reset(User)` – beide ohne `updated_at`
  zu ändern.
- **app/View/Components/Tour.php** + **resources/views/components/tour.blade.php:**
  `<x-tour :pages="['heute']" />` gibt die offenen Schritte als
  `data-*`-Attribut aus und bindet Driver.js und das Start-Skript (inline,
  über `@push('scripts')`) nur dann ein. Neustart nach `htmx:afterSettle`,
  nach dem Umdrehen einer Karte und nach dem Öffnen eines Blatts.
- **app/Http/Controllers/TourController.php:** `seen` (204, für fetch),
  `reset` (Weiterleitung zu „Heute“).
- **routes/web.php:** `POST /tours/seen` (`throttle:60,1,tours`),
  `POST /tours/reset`.
- **app/Models/User.php:** `tour_steps_seen` (Cast `array`, Vorgabe `[]`
  auch im Modell).
- **resources/css/components.css:** `.driver-popover.tour-popover`
  (Tokens, bewusst außerhalb von `@layer`, weil `driver-1.10.2.css`
  ungeschichtet später lädt), `just css`.
- **Views mit `data-tour`:** `learn/today`, `learn/partials/round-body`,
  `decks/index`, `decks/show`, `decks/partials/learn-panel`,
  `components/card-row` (Parameter `tour`), `pages/dictionary`,
  `partials/dictionary-results`, `duels/index`, `pages/account`,
  `children/show`, `admin/users`; `pages/help` und `pages/account` mit
  „Touren noch einmal zeigen“.
- **database/factories/UserFactory.php:** Vorgabe „alles gesehen“,
  `withTours()`; der Seeder gibt seinen Konten die Touren.
- **Doku:** Hilfe-Abschnitt, CHANGELOG mit Migration.

## Tests

- **Service:** offene Schritte je Seite; gesehene fallen weg; „Neu“ nur,
  wenn auf der Seite schon etwas gesehen ist; unbekannte Kennungen werden
  verworfen; doppelte nur einmal gespeichert; höchstens 20.
- **Routen:** `/tours/seen` und `/tours/reset` nur angemeldet
  (RouteProtectionTest), 204 bzw. Weiterleitung; nur das eigene Konto ändert
  sich.
- **Ziele existieren:** Jedes `data-tour`-Ziel aus `App\Support\Tours`
  steht in einer View; dazu werden Heute, eigener Stapel, Wörterbuch-Treffer
  und die Lernrunde (nach der Vorstellung neuer Wörter) in passendem Zustand
  aufgerufen und ihre Ziele im HTML geprüft. So fällt eine umbenannte Stelle
  sofort auf.
- **Factory:** Konten aus der Factory haben alle Schritte gesehen (sonst
  stünden Tour-Texte in jedem Test); `withTours()` für Tests der Touren.
  Die Konten aus dem Seeder sehen die Touren.
- **Einbindung:** Mit offenen Schritten stehen Driver.js und die Schritte
  in der Seite, ohne offene nicht. Ohne Konto (Duell-Gast) keine Tour.
- **Sichtbarkeit nach Rolle:** Ein Kind unter 18 bekommt nie das
  Teilen-Fenster und damit dessen Schritte nicht; Nicht-Admins nie die
  Admin-Schritte.
- **Zurücksetzen:** danach sind alle Schritte wieder offen, nur beim
  eigenen Konto; Lernstand unverändert; Knopf auf Hilfe und Mein Konto.

## Annahmen und Risiken

- **Drei Schritte je Seite** sind eine Obergrenze; mehr stören Kinder.
  Neue Funktionen kommen als zusätzlicher Schritt dazu, ältere können dafür
  wegfallen (gesehene Kennungen bleiben gespeichert, schaden nicht).
- **Bestehende Konten** sehen nach dem Update auf jeder Seite einmal die
  Tour. Das ist gewollt, kann aber am ersten Tag als viel wirken.
- **Driver.js** wird nicht über npm aktualisiert; eine neue Version ist
  eine bewusste Änderung mit Blick auf die Startlogik. Driver.js arbeitet
  mit Elementen, die beim Start da sind – nachgeladene Inhalte (htmx) und
  Blätter brauchen den Neustart, der beim Bauen geprüft werden muss.
- **Melden per fetch** braucht das CSRF-Token (wie htmx über `hx-headers`
  am `<body>`).
- **Service Worker:** `/js/` und `/css/` werden zwischengespeichert; ein
  neuer Dateiname bzw. ein Versions-Parameter beim Wechsel der Version
  verhindert veraltete Dateien.
- **Texte** sind Entwürfe und werden beim Bauen am Telefon gegengelesen.
  Wo ein Ziel je nach Zustand fehlt (z. B. „Duell starten“ ohne Karten),
  kommt der Schritt später.
- **Lernrunde:** Die Tour unterbricht die erste Runde kurz. Bei
  „Antworten eintippen“ gibt es statt „Gewusst / Nochmal“ das Eingabefeld;
  dort entfällt `runde.ehrlich` bis zur ersten Runde ohne Eintippen.
