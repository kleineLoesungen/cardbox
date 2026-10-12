# Lernpläne je Stapel und bessere Karten

Status: **freigegeben** · Stufe 1 (Durchgänge) ✅ · Stufe 2 (Hauptbedeutung, Hinweis) ✅ · Stufe 3 (Herkunft, „geändert“) ✅ · Stufe 4 offen

Fasst zwei Wünsche zusammen:

- **A – Lernpläne je Stapel:** ein Stapel kann mit einem eigenen Lernplan
  gestartet werden, auch mehrmals gleichzeitig; Zurücksetzen bleibt möglich.
- **B – Karten (Issue #2):** Hauptbedeutung, Hinweisfeld, Herkunft kopierter
  Karten, Vorschläge an die Besitzerin.

Grundlage bleibt [vokabelkarten.md](vokabelkarten.md) mit ihren Leitlinien
für Oberfläche und Bedienung (Daumenzone, eine Hauptaktion je Bildschirm,
einfache Sprache).

## Ziel

Wer vor einer Prüfung steht, lernt einen Stapel „Schnell“, ohne dafür den
Lernplan aller anderen Stapel umzustellen – und kann denselben Stapel
daneben weiter „Normal“ lernen. Karten werden eindeutiger (Hauptbedeutung,
Hinweis), Kopien erfahren von Korrekturen am Original, und wer einem Stapel
folgt, kann der Besitzerin Fehler melden.

## Nicht dabei

- Den Lernplan eines **begonnenen** Stapels ändern. Wer wechseln will, setzt
  zurück oder startet den Stapel zusätzlich mit dem anderen Plan.
- Ein **eigener Plan** (frei gewählte Fächer und Abstände) am Stapel. Den
  gibt es weiter nur als Standard unter **Mehr → Lernplan**.
- Karten als Verweise in mehreren Stapeln, volle Versionierung, Up-/Downvotes
  (Begründung in Issue #2).
- Benachrichtigungen per Mail oder Push über neue Vorschläge oder geänderte
  Originale. Hinweise erscheinen nur in der App.
- Moderation von Vorschlägen.
- Schnittstelle (API) für irgendetwas davon.

## Begriffe

| Begriff in der Oberfläche | Bedeutung | im Code |
|---|---|---|
| **Lernplan** | Anzahl Fächer und Abstände in Tagen | `StudyPlan`, `box_count`, `day_gaps` |
| **Standard-Lernplan** | unter Mehr → Lernplan, gilt für neu gestartete Stapel | `learning_settings` |
| **Durchgang** | ein Stapel, gelernt mit einem bestimmten Lernplan. In der Oberfläche erscheint er nur mit dem Namen des Plans („Schnell“) | `deck_runs`, `DeckRun` |
| **Hauptbedeutung** | der erste Eintrag einer Kartenseite | `front[0]`, `back[0]` |
| **Hinweis** | kurzer Zusatz zur Karte, z. B. „(Zug)“ | `cards.hint` |
| **Original** | die Karte, von der eine Kopie stammt | `cards.source_card_id` |
| **Vorschlag** | Fehlermeldung oder Änderungswunsch an die Besitzerin | `card_suggestions` |

---

## Verhalten

### A1. Stapel starten

Heute entsteht Lernstand einfach beim ersten Lernen. Künftig gehört jeder
Lernstand zu einem **Durchgang**: Stapel + Lernplan, mit beiden Richtungen.

- **Noch nie gelernt:** Die Stapelansicht zeigt wie bisher die Knöpfe zum
  Lernen. Ein Tipp startet den Stapel mit dem **Standard-Lernplan** – ohne
  Rückfrage, damit sich für die meisten nichts ändert.
- Darunter ein kleiner Link **„Mit anderem Lernplan starten“**. Er öffnet ein
  Blatt mit den drei Vorgaben **Normal**, **Schnell**, **Gründlich** (große
  Auswahlkarten mit dem bekannten Satz zur Erklärung). Der Standard ist
  markiert. Auswahl → Durchgang angelegt, die Stapelansicht zeigt ihn.
- Ist der Standard ein eigener Plan, heißt er am Stapel **„Mein Plan“**. Er
  wird über den normalen Lernen-Knopf gestartet. Im Blatt stehen trotzdem
  nur die drei Vorgaben.

### A2. Mehrere Durchgänge

- Ein Stapel kann **mit jedem Plan höchstens einmal** laufen: Normal,
  Schnell, Gründlich, Mein Plan. Das sind höchstens vier Durchgänge.
- Läuft ein Stapel schon, bleibt „Mit anderem Lernplan starten“ sichtbar.
  Im Blatt sind die laufenden Pläne ausgegraut („läuft schon“).
- Bei mehr als einem Durchgang zeigt die Stapelansicht oben eine
  **Umschaltleiste** mit den Plannamen („Normal · Schnell“). Die
  Karteikasten-Leisten, die Fächer-Blätter und die Lernen-Knöpfe gelten für
  den gewählten Durchgang. Die Auswahl steht in der Adresse
  (`?run=<id>`), damit Zurück und Neu-Laden stimmen.
- Bei genau einem Durchgang gibt es keine Leiste, nur eine kleine Zeile
  „Lernplan: Schnell“.
- Die Durchgänge sind **unabhängig**: Jede Karte hat je Durchgang und
  Richtung ihr eigenes Fach und Fälligkeitsdatum.

### A3. Lernplan ist fest

- Ein Durchgang merkt sich seine Fächer und Abstände beim Start. Ändert man
  später den Standard unter Mehr → Lernplan, gilt das **nur für neu
  gestartete** Durchgänge. Die Seite sagt das in einem Satz.
- Es gibt keine Möglichkeit, den Plan eines Durchgangs zu ändern: kein
  Knopf, keine Route.

### A4. Zurücksetzen

- Im Stapel-Menü heißt die Aktion weiterhin **„Fortschritt zurücksetzen“**.
  Sie wirkt auf den **gewählten Durchgang** und löscht ihn mit seinem ganzen
  Lernstand. Die Rückfrage nennt den Plan: „Fortschritt mit Lernplan Schnell
  löschen? Die Karten bleiben.“
- Danach ist der Stapel mit diesem Plan nicht mehr gestartet. Wer neu
  anfangen will, startet ihn wieder, mit demselben oder einem anderen Plan.
  So ersetzt Zurücksetzen und neu Starten den Planwechsel.
- Andere Durchgänge desselben Stapels und der Lernstand anderer Personen
  bleiben unberührt.

### A5. Heute und Lernrunden

- **„Los geht's“** nimmt fällige Karten aus allen Durchgängen aller eigenen
  und gefolgten Stapel, die ältesten zuerst.
- Ist **dieselbe Karte in derselben Richtung in zwei Durchgängen** heute
  fällig, kommt sie in der Runde **nur einmal** dran. Die Antwort zählt dann
  für jeden dieser Durchgänge, denn ob man das Wort kann, ist dieselbe
  Tatsache. Jeder Durchgang rückt nach seinem eigenen Plan weiter.
- **Lernen in der Stapelansicht** nutzt nur den gewählten Durchgang: erst
  fällige, dann neue Karten, wie bisher.
- „Fällig heute“ in Heute und auf der Stapelkarte zählt jede Karte und
  Richtung **einmal**, auch wenn sie in zwei Durchgängen fällig ist.
- **„Kann ich“-Anteil** auf der Stapelkarte: der höchste Wert der Durchgänge
  des Stapels.
- Lernserie und Lerntage zählen eine Antwort einmal, egal für wie viele
  Durchgänge sie gilt.
- **Freies Üben** bleibt unabhängig vom Lernplan. Der Filter nach Fach
  bezieht sich auf den gewählten Durchgang.

### A6. Kopieren, Folgen, Löschen

- **Kopieren** nimmt alle eigenen Durchgänge mit Lernstand mit, wie heute
  den Lernstand.
- **Nicht mehr folgen** behält die Durchgänge, wie heute den Lernstand.
  Wer wieder folgt, macht weiter.
- Löscht die Besitzerin einen Stapel oder eine Karte, verschwinden die
  zugehörigen Durchgänge bzw. Einträge bei allen, wie bisher.

### A7. Hilfe und Lernplan-Seite

- Die Hilfe bekommt einen Abschnitt „Einen Stapel anders lernen“, zwei bis
  drei Sätze mit dem Prüfungsbeispiel.
- Die Lernplan-Seite unter Mehr sagt: „Gilt für Stapel, die du neu startest.
  Einen einzelnen Stapel kannst du in der Stapelansicht mit einem anderen
  Plan starten.“

---

### B1. Hauptbedeutung (Issue #2, Punkt 1)

Der **erste Eintrag** einer Seite ist die Hauptbedeutung. Das Schema bleibt.

- **Lernkarte, Frage:** nur die Hauptbedeutung. Das ist schon so.
- **Lernkarte, Antwort:** die Hauptbedeutung groß, weitere klein darunter
  mit „auch: quick, rapid“. Heute stehen alle gleich groß untereinander.
- **Vorlesen:** nur die Hauptbedeutung, auch bei der Antwort. Heute werden
  alle vorgelesen.
- **Kartenliste:** die Hauptbedeutung fett, weitere gedämpft, wie bisher.
- **Karte bearbeiten:** Neben jeder weiteren Bedeutung ein Knopf
  **„Als Hauptbedeutung“**. Er rückt sie nach vorn, ohne Speichern; das
  Speichern bleibt ein Schritt.
- **Liste einfügen:** unverändert, eine Bedeutung je Seite.

### B2. Hinweis (Issue #2, Punkt 2)

- **Ein Hinweis je Karte**, höchstens 100 Zeichen, freiwillig. Die Frage aus
  Issue #2 (je Karte oder je Seite) ist damit beantwortet. Begründung: Der
  typische Hinweis („(Zug)“, „ugs.“, „Plural“) gilt für das Wortpaar, nicht
  für eine Seite. Ein Feld ist für Kinder einfacher.
- Auf der Lernkarte steht er klein und gedämpft **unter der Frage, schon vor
  dem Umdrehen**: Er soll unterscheiden helfen („miss (Zug)“ ≠ „miss
  (vermissen)“), nicht die Antwort verraten. Im Formular steht deshalb:
  „Ist vor dem Umdrehen zu sehen – nicht die Lösung hineinschreiben.“
- Kartenliste: Hinweis klein hinter der Vorderseite.
- Liste einfügen und Kopieren übernehmen den Hinweis. Das Einfügeformat
  bekommt **keine** dritte Spalte (bleibt einfach).

### B3. Herkunft kopierter Karten (Issue #2, Punkt 3)

- Beim **Stapel kopieren** und beim **Karte übernehmen** merkt sich die Kopie
  ihr Original und dessen Stand.
- Ist ein Original **neuer** als die Kopie und für die Person noch
  **sichtbar** (eigener oder geteilter Stapel), zeigt die Stapelansicht der
  Kopie einen Hinweis: **„3 Karten wurden im Original geändert –
  ansehen“**. Nur für die Besitzerin der Kopie.
- Das Blatt „Änderungen im Original“ zeigt je Karte alt und neu, mit
  **„Übernehmen“** und **„Behalten“**, oben **„Alle übernehmen“**.
  - Übernehmen ersetzt Vorderseite, Rückseite und Hinweis. Der Lernstand
    bleibt.
  - Behalten lässt die Karte, wie sie ist. Der Hinweis verschwindet, bis
    sich das Original wieder ändert.
- Ist das Original gelöscht, privat oder nicht mehr sichtbar, gibt es keinen
  Hinweis. Der gespeicherte Bezug bleibt aber erhalten (kein Fremdschlüssel).
- Eine Kopie einer Kopie verweist auf ihre direkte Vorlage.

### B4. „Geändert“ für Folgende (Issue #2, Punkt 3)

- Hat die Besitzerin eine Karte geändert, **nachdem** die Person sie zuletzt
  gelernt hat, zeigt die Lernrunde ein kleines Abzeichen **„geändert“**,
  neben „Wiederholung“. Der Lernstand bleibt.
- Gilt für alle Karten, auch eigene. Bei eigenen kommt es aber nur vor, wenn
  man selbst nach dem Lernen geändert hat. Das ist harmlos und braucht keine
  Ausnahme.

### B5. Vorschlag an die Besitzerin (Issue #2, Punkt 4)

**Absender (folgt einem Stapel oder sieht ihn in Entdecken):**

- In der Kartenliste eines **fremden geteilten** Stapels hat jede Karte im
  Menü **„Fehler melden“**. Er öffnet ein Blatt mit:
  - Art: **Tippfehler**, **Falsche Übersetzung**, **Bedeutung fehlt**,
    **Sonstiges** (große Auswahlknöpfe)
  - optional neue Vorderseite und Rückseite, vorbefüllt mit dem jetzigen
    Text, gleiche Regeln wie beim Bearbeiten
  - optional Kommentar, höchstens 300 Zeichen
  - Es muss mindestens ein neuer Text oder ein Kommentar dabei sein.
- Pro Person und Karte gibt es **höchstens einen offenen** Vorschlag. Ist
  einer offen, zeigt die Karte „Vorschlag offen“ statt des Knopfs.
- Nach der Entscheidung sieht der Absender an der Karte „Vorschlag
  übernommen“ bzw. „Vorschlag abgelehnt“, bis er einen neuen macht.
- Die Route ist gedrosselt, 20 Vorschläge pro Stunde.

**Besitzerin:**

- In der Stapelansicht ein Hinweis **„2 Vorschläge“**. Ein Zähler steht auch
  auf der Stapelkarte unter Meine Stapel.
- Die Liste zeigt je Vorschlag die Karte jetzt, den Vorschlag (Änderungen
  hervorgehoben), Art, Kommentar und den **Benutzernamen** des Absenders.
  Dazu **„Übernehmen“** und **„Ablehnen“**.
  - Übernehmen schreibt die vorgeschlagenen Seiten in die Karte, geprüft wie
    beim Bearbeiten. Ein reiner Kommentar ohne Text ist nur „Erledigt“.
  - Wurde die Karte inzwischen anders geändert, steht das dabei
    („Karte wurde seit dem Vorschlag geändert“). Übernehmen ist trotzdem
    möglich.
- Wird ein Stapel privat, bleiben die Vorschläge für die Besitzerin
  sichtbar. Absender können keine neuen machen.

**Löschen:** Vorschläge verschwinden mit ihrer Karte und mit dem Konto des
Absenders.

### Fehlerfälle

- Fremder oder unbekannter Durchgang, Vorschlag, Stapel oder fremde Karte
  ergibt **404**, nie 403, nie 500 (UUID-Prüfung wie gehabt).
- Zweiter Durchgang mit gleichem Plan → Meldung „Dieser Stapel läuft schon
  mit diesem Plan.“, nichts wird angelegt.
- Ungültiger Plan-Name → Validierungsfehler.
- Lernrunde mit einem Durchgang, der während der Runde zurückgesetzt wurde:
  Seine Karten werden still übersprungen, wie heute gelöschte Karten.
- Vorschlag auf eigene Karte, auf private fremde Karte oder als zweiter
  offener → 404 bzw. Meldung „Du hast schon einen Vorschlag zu dieser
  Karte.“
- Übernehmen eines Vorschlags, dessen Karte inzwischen gelöscht ist → 404.

---

## Berechtigungen

Alles in der Gruppe **`auth`** (`routes/web.php`), keine neue Rolle, kein
Scope, keine neuen `PUBLIC_ROUTES`.

| Was | Wer | Prüfung im Service |
|---|---|---|
| Durchgang starten, wählen, zurücksetzen | jede angemeldete Person für **sichtbare** Stapel | Durchgang immer über `user_id` gefiltert (`…Owned`) |
| Lernstand lesen und schreiben | nur eigener | wie bisher |
| Änderungen im Original ansehen und übernehmen | Besitzerin der Kopie | `DeckService::getOwned` |
| Vorschlag machen | angemeldet, Karte in **fremdem geteiltem** Stapel | sichtbar und nicht eigen, sonst 404 |
| Vorschläge sehen, übernehmen, ablehnen | **Besitzerin** des Stapels | `getOwned`, fremde → 404 |
| Status des eigenen Vorschlags sehen | Absender | nach `user_id` gefiltert |

Moderatoren und Admins haben **keinen** Sonderzugriff auf Vorschläge oder
Durchgänge anderer.

---

## Datenmodell

### Migration `2026_10_13_000000_create_deck_runs_table.php` (Stufe 1) ✅

Neue Tabelle `deck_runs`:

| Spalte | Typ | |
|---|---|---|
| `id` | uuid, Primärschlüssel | |
| `user_id` | foreignUuid → users, cascade | |
| `deck_id` | foreignUuid → decks, cascade | |
| `plan` | string(10) | `normal` · `quick` · `thorough` · `custom` |
| `box_count` | smallInteger | beim Start festgeschrieben |
| `day_gaps` | jsonb | beim Start festgeschrieben |
| `created_at`, `updated_at` | timestampsTz | |

Eindeutig: `(user_id, deck_id, plan)`. Index: `(user_id)`.

`card_progress` bekommt `deck_run_id` (foreignUuid → deck_runs, cascade).
Die Eindeutigkeit wechselt von `(user_id, card_id, direction)` zu
`(deck_run_id, card_id, direction)`. `user_id` bleibt in der Tabelle
(schnelle Abfragen, Löschen mit dem Konto).

**Datenübernahme in derselben Migration:** Für jedes Paar (Person, Stapel)
mit Lernstand entsteht ein Durchgang. Er bekommt den **jetzigen**
Standard-Lernplan der Person (oder die Vorgabe Normal). `plan` ist der Name
der passenden Vorgabe, sonst `custom`. Danach bekommt jede Zeile in
`card_progress` ihren Durchgang, und `deck_run_id` wird `NOT NULL`. Für
Benutzer sieht alles aus wie vorher.

`down()` führt Durchgänge wieder zusammen. Gibt es mehrere je Stapel,
gewinnt der zuletzt gelernte. Das geht verlustbehaftet, ist aber
dokumentiert.

### Migration `2026_10_13_100000_add_hint_to_cards_table.php` (Stufe 2) ✅

`cards.hint`: string(100), nullable (B2).

### Migration `2026_10_13_150000_add_source_to_cards_table.php` (Stufe 3) ✅

Getrennt von Stufe 2, damit jeder Commit nur Spalten anlegt, die er
benutzt. `cards` bekommt:

| Spalte | Typ | |
|---|---|---|
| `source_card_id` | uuid, nullable, **kein** Fremdschlüssel, Index | B3 |
| `source_updated_at` | timestampTz, nullable | B3, Stand des Originals beim Kopieren bzw. Übernehmen |

### Migration `2026_10_13_200000_create_card_suggestions_table.php` (Stufe 4)

| Spalte | Typ | |
|---|---|---|
| `id` | uuid, Primärschlüssel | |
| `card_id` | foreignUuid → cards, cascade | |
| `user_id` | foreignUuid → users, cascade | Absender |
| `type` | string(20) | `typo` · `wrong` · `missing` · `other` |
| `proposed_front`, `proposed_back` | jsonb, nullable | |
| `comment` | string(300), nullable | |
| `status` | string(10) | `open` · `accepted` · `declined` |
| `card_updated_at` | timestampTz | Stand der Karte beim Vorschlag, für den Hinweis „seither geändert“ |
| `decided_at` | timestampTz, nullable | |
| `created_at`, `updated_at` | timestampsTz | |

Eindeutiger Teilindex: `(card_id, user_id) WHERE status = 'open'`. Index
`(card_id, status)`.

---

## Betroffene Stellen

**app/Enums/**
- `StudyPlan`: Schlüssel `custom` bzw. Methode für „Plan aus Werten“.
- Neu: `SuggestionType`, `SuggestionStatus`.

**app/Models/**
- Neu: `DeckRun` (Beziehungen, `plan()` als Enum-Ableitung), `CardSuggestion`.
- `CardProgress`: `deck_run_id`, Beziehung `run()`.
- `Card`: `hint`, `source_card_id`, `source_updated_at` in `#[Fillable]`
  (bzw. `source_*` per `forceFill`, da nie Benutzereingabe); `alsoMeanings()`.

**app/Services/**
- Neu: `DeckRunService`: `listOwned(user, deck)`, `getOwned`, `start(user,
  deck, plan|standard)`, `resetOwned` (löscht den Durchgang). Validierung des
  Plans dort.
- `LeitnerService`: `plan()` liest den Plan aus dem Durchgang statt aus der
  Person. `review()` nimmt einen Durchgang. Die Lernserie bleibt bei der
  Person.
- `StudyRoundService`: Rundeneinträge tragen den Durchgang bzw. bei „Los
  geht's“ die Liste der Durchgänge (A5). `dueQuery` verbindet `deck_runs`
  und prüft `box` gegen `deck_runs.box_count`.
- `LearningOverviewService`: Zahlen je Durchgang. Heute und Stapelkarten
  zählen eindeutig (A5). `resetDeck` wird zu `DeckRunService::resetOwned`.
- `LearningSettingsService`: unverändert in der Logik, nur der Text der Seite.
- `DeckService::copy`: Durchgänge mitkopieren, `source_*` setzen.
- `CardService`: `hint` validieren. `adopt` setzt `source_*`. Neu:
  `pendingOriginalUpdates(user, deck)`, `takeOriginal`, `keepMine`,
  `takeAllOriginals`. Hauptbedeutung festlegen geschieht über die
  Reihenfolge beim Speichern (kein eigener Endpunkt nötig).
- Neu: `CardSuggestionService`: `createFor(user, deck, card, input)`,
  `listForOwnedDeck`, `accept`, `decline`, `statusForUser`.

**app/Http/Controllers/**
- `DeckController`: `show` mit `?run=`. `reset` wird zu „Durchgang
  zurücksetzen“.
- Neu: `DeckRunController` (start, reset), `CardOriginController`,
  `CardSuggestionController`.
- `StudyRoundController`: nimmt `run` bzw. `plan` entgegen.

**routes/web.php** (alle in `auth`, unter `/decks/{deck}` mit `whereUuid`)
- `POST /decks/{deck}/runs` – starten (`plan=standard|normal|quick|thorough`)
- `POST /decks/{deck}/runs/{run}/reset` – ersetzt `POST /decks/{deck}/reset`
- `GET /decks/{deck}/box/{box}` – zusätzlich `?run=`
- `GET /decks/{deck}/origin` · `POST /decks/{deck}/origin/take-all` ·
  `POST /decks/{deck}/cards/{card}/origin/take` · `…/origin/keep`
- `GET|POST /decks/{deck}/cards/{card}/suggest` (POST mit `throttle:20,60`)
- `GET /decks/{deck}/suggestions` ·
  `POST /decks/{deck}/suggestions/{suggestion}/accept` · `…/decline`

**resources/views/**
- `decks/show`, `decks/partials/learn-panel`, `reset-action`, `box-cards`:
  Umschaltleiste, Startblatt, Plan-Zeile.
- Neu: `decks/partials/start-sheet`, `decks/origin`, `decks/suggestions`,
  `decks/partials/suggest-sheet`.
- `learn/partials/round-body`: „auch:“, Hinweis, „geändert“, Vorlesen nur
  Hauptbedeutung.
- `learn/settings`, `help`, `learn/today` (Zähler „Vorschläge“).
- Kartenformular: Hinweisfeld, „Als Hauptbedeutung“.

**config/**
- `learning.php`: `max_hint_length` (100), `max_suggestion_comment` (300).

**lang/de/validation.php**: Attribute `hint`, `plan`, `type`, `comment`.

**docs/**: `vokabelkarten.md` (Begriffe, Datenmodell, Nicht dabei),
`CHANGELOG.md` mit `Migration:`-Zeilen, Hilfe-Seite.

---

## Tests

**Durchgänge (A)**
- Erstes Lernen ohne Auswahl legt genau einen Durchgang mit dem Standard an.
  Ein eigener Standard ergibt `custom` mit seinen Werten.
- Start mit „Schnell“ legt einen Durchgang mit 4 Fächern und 1·1·2 an.
- Zweiter Start mit gleichem Plan → Meldung, kein zweiter Durchgang.
  Anderer Plan → zweiter Durchgang.
- Standard ändern lässt laufende Durchgänge unverändert (Fächer, Abstände,
  Fälligkeit).
- Es gibt keine Route, die den Plan eines Durchgangs ändert. Der
  Routentest listet nur die erwarteten Routen.
- Antwort im Durchgang „Schnell“ setzt Fälligkeit nach dessen Abständen und
  lässt „Normal“ unberührt.
- „Los geht's“: Eine Karte, die in zwei Durchgängen fällig ist, kommt
  einmal. Die Antwort bewegt beide, jede nach ihrem Plan. Die Lernserie zählt
  einmal.
- „Fällig heute“ zählt solche Karten einmal.
- Zurücksetzen löscht nur den gewählten Durchgang der Person. Andere
  Durchgänge und andere Personen bleiben.
- Fremder Durchgang (andere Person, anderer Stapel, keine UUID) → 404 beim
  Wählen, Zurücksetzen und im Fach-Blatt.
- Kopieren nimmt alle eigenen Durchgänge samt Lernstand mit, keine fremden.
- Migration: Vorhandener Lernstand landet in je einem Durchgang pro
  (Person, Stapel) mit dem damaligen Standard. Die Fälligkeiten bleiben.
- Bestehende Tests zu Leitner, Runde, Heute und Zurücksetzen werden
  angepasst, nicht gelockert.

**Karten (B)**
- Antwortseite zeigt „auch:“. `data-answer` beim Vorlesen enthält nur die
  Hauptbedeutung.
- Bearbeiten mit geänderter Reihenfolge speichert die neue Hauptbedeutung.
- Hinweis: höchstens 100 Zeichen, leer → `null`. Er wird bei Kopie und
  Übernahme mitgenommen und erscheint auf der Lernkarte.
- Herkunft: Kopie und Übernahme setzen `source_*`. Eine Änderung am Original
  erzeugt den Hinweis, für fremde Personen keinen. Übernehmen ersetzt den
  Inhalt und behält den Lernstand. Behalten blendet aus, bis zur nächsten
  Änderung. Originale, die privat oder gelöscht sind, erzeugen keinen
  Hinweis und keinen Fehler.
- „geändert“: erscheint nach Änderung durch die Besitzerin, nicht vorher.
- Vorschläge:
  - auf eigene Karte → 404, auf private fremde → 404, zweiter offener →
    Meldung
  - ohne Text und Kommentar → Validierungsfehler
  - Übernehmen ändert die Karte mit Prüfung, Ablehnen nicht. Beides nur
    durch die Besitzerin, andere → 404
  - der Absender sieht den Status, andere nicht
  - die Drosselung greift
  - Karte gelöscht → Vorschläge weg
- Keine N+1 in Stapelansicht mit mehreren Durchgängen, Vorschlagsliste,
  Herkunftsliste.
- Ansicht bei 360 px ohne waagerechtes Scrollen (manuell im Browser).

---

## Umsetzung in Stufen

Jede Stufe ist für sich nutzbar, wird einzeln committet und bekommt einen
Changelog-Eintrag.

1. **Durchgänge** (A1–A7) ✅ – die größte Stufe, mit Datenübernahme.
   Umgesetzt wie beschrieben, mit diesen Präzisierungen:
   - „Lernen“ ohne gewählten Durchgang nimmt den **ältesten** Durchgang des
     Stapels (den die Stapelansicht zuerst zeigt). Nur ein Stapel ganz ohne
     Durchgang startet einen mit dem Standard.
   - Ein unbekanntes oder fremdes `?run=` in der Stapelansicht fällt still
     auf den ältesten Durchgang zurück. Im Fach-Blatt, beim Zurücksetzen
     und beim Start einer Runde ergibt es 404.
   - Startblatt: Auswahlkarten mit einem Knopf „Starten“. Laufende Pläne
     sind ausgegraut („läuft schon“), der Standard trägt „Standard“.
   - Tests: `tests/Feature/DeckRunTest.php`. Die Datenübernahme ist mit
     `down()` und `up()` gegen Altdaten geprüft.
2. **Hauptbedeutung und Hinweis** (B1, B2) ✅. Umgesetzt wie beschrieben:
   - „Als Hauptbedeutung“ ist ein ☆-Knopf neben jeder weiteren Bedeutung
     im Bearbeiten-Blatt. Er rückt die Zeile nach oben, gespeichert wird
     mit „Speichern“. Ohne JavaScript bleibt die Reihenfolge der Felder
     maßgeblich.
   - Der Hinweis ist im Anlegen- und Bearbeiten-Blatt unter „+ Hinweis“
     eingeklappt (offen, wenn einer gesetzt ist). So bleibt das schnelle
     Anlegen vieler Karten unverändert.
   - Auf der Rückseite der Lernkarte steht der Hinweis klein hinter der
     Frage.
   - Tests: `tests/Feature/CardMeaningTest.php`.
3. **Herkunft und „geändert“** (B3, B4) ✅. Umgesetzt wie beschrieben, mit
   diesen Präzisierungen:
   - „Änderungen im Original“ ist eine eigene Seite
     (`/decks/{deck}/origin`) statt eines Blatts. Je Karte stehen zwei
     Zeilen untereinander, „Deine“ und „Neu“, mit zwei Knöpfen. Das passt
     auf dem Telefon besser als in ein Blatt. Sind alle erledigt, führt die
     Seite zurück zum Stapel.
   - „Behalten“ setzt nur die Stand-Marke; die eigene Karte gilt danach
     nicht als geändert (kein neues `updated_at`).
   - „geändert“ vergleicht `cards.updated_at` mit dem letzten
     `last_reviewed_at` der Karte in den Durchgängen der Runde. Neue Karten
     und Freies Üben zeigen es nie.
   - Tests: `tests/Feature/CardOriginTest.php`.
4. **Vorschläge** (B5).

Issue #2 wird mit dem Commit der Stufe 4 geschlossen („Closes #2“). Stufen 2
und 3 verweisen mit „Teil von #2“ darauf.

---

## Annahmen und Risiken

- **Eine Antwort zählt für alle fälligen Durchgänge (A5).** Die Alternative –
  dieselbe Karte zweimal in einer Runde – wirkt auf Kinder wie ein Fehler.
  Nachteil: Die Durchgänge sind nicht völlig getrennt. Wer das will, müsste
  sie einzeln über die Stapelansicht lernen; dort gilt die Antwort nur für
  den gewählten.
- **Zurücksetzen löscht den Durchgang** statt nur seinen Lernstand. Eine
  Aktion statt zwei. „Von vorn mit demselben Plan“ braucht dann zwei Tipps
  (Zurücksetzen, Starten).
- **„Mein Plan“ am Stapel**: Er entsteht nur, wenn der Standard ein eigener
  Plan ist. Am Stapel wählbar sind wie gewünscht nur die drei Vorgaben.
- **Plan fest ab Start** folgt aus „kein Planwechsel bei begonnenem Stapel“.
  Heute wirkt eine Standard-Änderung sofort auf allen Lernstand. Das ändert
  sich: Wer bisher den Standard umgestellt hat, um alles schneller zu
  lernen, muss künftig neu starten. Die Lernplan-Seite sagt das.
- **Datenübernahme**: Nach der Migration hat jede Person je begonnenem Stapel
  einen Durchgang mit ihrem derzeitigen Standard. Der Fall „Fächer über N“
  (Plan früher verkleinert) bleibt wie bisher „Kann ich“.
- **Abfragen werden breiter** (Join auf `deck_runs`). Die Indizes oben
  reichen für die erwarteten Größen (Hunderte Karten je Person). Nach
  Stufe 1 die Laufzeit von Heute und Stapelansicht mit einigen tausend
  Einträgen prüfen.
- **Hinweis vor dem Umdrehen** kann die Antwort verraten, wenn jemand die
  Lösung hineinschreibt. Das fängt nur der Formulartext ab.
- **Vorschläge zeigen den Benutzernamen** des Absenders, sonst nichts von
  ihm. Das ist neu: Bisher erfährt eine Besitzerin nicht, wer ihrem Stapel
  folgt. Die Alternative wäre ein anonymer Vorschlag. Dann fehlt aber jede
  Hemmschwelle gegen Unfug. Eine Meldung von Missbrauch gibt es bewusst
  nicht.
- **`source_card_id` ohne Fremdschlüssel** ist gewollt (Issue #2): Das
  Original darf verschwinden. Der Service prüft beim Lesen die
  Sichtbarkeit.
- **Offen:** Der Begriff „Durchgang“ erscheint in der Oberfläche fast nie,
  nur in der Hilfe. Passt ein anderes Wort besser, ist es eine
  Ein-Zeilen-Änderung.
