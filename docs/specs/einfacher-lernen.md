# Einfacher lernen

Status: **umgesetzt** (15.10.2026)

Anlass: Ein 11-Jähriger versteht die App nicht. Er weiß nicht, **wo und wie
man lernt**, und die Wege **Folgen / Kopieren / Übernehmen** verwirren ihn.
Seine Vokabeln bekommt er meist als **geteilte Stapel** von Eltern oder
Lehrkraft.

Entscheidungen (Rückfragen vom 15.10.2026):

- Die Vereinfachung gilt **für alle**. Es gibt keine eigene „einfache
  Ansicht“.
- **„Heute“ ist der Hauptweg** zum Lernen und bringt jetzt auch neue Karten.
  Im Stapel bleibt ein zweiter Knopf „Nur diesen Stapel lernen“ (für den
  Vokabeltest).
- **Richtung:** Gelernt wird vorne → hinten. Rückwärts kommt nur dazu, wenn
  man es je Stapel einschaltet („Auch rückwärts lernen“).
- **Neue Karten:** **20 je Tag** über „Heute“, so viele wie eine Runde fasst.
  Mehr gibt es jederzeit mit einem Tipp.
- **Für Prüfungen:** „Für den Test üben“ im Stapel geht alle Karten durch,
  bis jede einmal gewusst ist (Variante a, Rückfrage vom 15.10.2026). Ein
  Testdatum mit schnellerem Plan (Variante b) ist nicht dabei.

## Ziel

Wer die App öffnet, hat genau einen Weg: **„Heute“ → „Los geht's“**. Dort
kommen fällige und neue Karten aus allen Stapeln. Um einen geteilten Stapel
zu lernen, genügt ein Knopf **„Lernen“**. Lernplan, Richtung, Durchgänge und
Kopien stehen nicht mehr im Weg.

## Nicht dabei

- Lernplan, Fächer und Leitner-Rechnung bleiben, wie sie sind. Geändert wird
  nur, **wo** sie gewählt werden.
- Bestehende Daten werden nicht gelöscht. Lernstände, Auswahl, Durchgänge,
  Kopien und Vorschläge bleiben.
- Funktionen für Erwachsene fallen nicht weg: Kopieren, Übernehmen, Liste
  einfügen, Export/Import, Karten auswählen, „Original geändert“. Sie
  rücken nur ins „⋯“-Menü bzw. bleiben dort.
- Keine Klassen- oder Gruppenfunktion und kein Teilen per Code. Ein
  geteilter Stapel lässt sich wie bisher über den Link (`/decks/{id}`)
  weitergeben.
- Keine Punkte, Abzeichen oder Ranglisten (Leitlinie 6).
- Kein Testdatum und kein automatischer „Schnell“-Plan vor Prüfungen.
- Keine Änderungen an der JSON-Schnittstelle.

---

## Verhalten

### A. „Heute“ bringt fällige und neue Karten

**Was in einer Runde „Los geht's“ steckt** (höchstens 20 Karten, wie
bisher):

1. **Fällige Karten** aus allen Stapeln, älteste zuerst. Das sind wie bisher
   alle Durchgänge, aber nur in Richtungen, die man lernt (siehe C).
2. Ist noch Platz: **neue Karten**. Davon gibt es höchstens 20 je Tag. Als
   „heute begonnen“ zählt eine Karte, wenn heute zum ersten Mal ein Lernstand
   für sie entstand.
   - Neue Karten kommen **abwechselnd aus allen Stapeln**: erst die ältesten
     Karten jedes Stapels, dann die nächsten. Wer drei Stapel lernt, bekommt
     also nicht nur Karten aus dem ersten.
   - Neu heißt: aktiv (nicht pausiert, Issue #10), und für die Person gibt es
     in dieser Richtung noch keinen Lernstand.
   - Gelernt wird im Durchgang, den die Stapelseite zeigt (dem ältesten). Gibt
     es noch keinen, wird einer mit dem eigenen Lernplan angelegt, wie heute
     beim „Lernen“ im Stapel.
   - Richtung: vorne → hinten. Bei Stapeln mit „Auch rückwärts lernen“ kommt
     danach auch hinten → vorne als eigene neue Karte dazu.

**Die Karte auf „Heute“** zeigt:

- „**12 Karten** warten auf dich“ und klein darunter „davon 5 neu“ (nur wenn
  neue dabei sind). „Los geht's“ bleibt die eine Hauptaktion.
- Ist nichts mehr fällig und das Tageskontingent an neuen Karten
  verbraucht: „Für heute alles geschafft.“ Gibt es noch ungelernte Karten,
  steht darunter ein schlichter Knopf **„Noch 20 neue Karten“**. Er startet
  eine Runde mit bis zu 20 weiteren neuen Karten über das Kontingent hinaus
  (beliebig oft). Sonst steht dort „Morgen sind 8 Karten dran.“
- Ist alles gelernt: „Für heute alles geschafft.“, wie bisher.

**Leere Zustände:**

- Ohne Stapel ist die Hauptaktion jetzt **„Stapel finden“** (führt zu
  „Entdecken“), zweitrangig „Eigenen Stapel anlegen“. Der Grund: Die meisten
  Kinder lernen geteilte Stapel.
- Bisher stand hier „Neue Karten lernst du in einem Stapel.“ Das fällt weg,
  weil es nicht mehr stimmt.

### B. Ein Wort: „Lernen“

**Fremder geteilter Stapel, den man noch nicht lernt:**

- Die Hauptaktion heißt **„Lernen“** statt „Folgen“. Darunter steht klein:
  „Die Karten kommen ab jetzt unter Heute dran.“ Technisch ist das weiter
  Folgen (`deck_follows`), mit dem Durchgang des eigenen Lernplans.
- **Kopieren** steht nur noch im „⋯“-Menü, als „Eigene Fassung anlegen“ mit
  dem Satz „Du bekommst eine Kopie, die du selbst ändern kannst.“ Bisher
  stand es als zweiter Knopf unter „Folgen“.

**Stapel, den man lernt, aber nicht besitzt:**

- Kennzeichen „**lernst du**“ statt „du folgst“.
- Im „⋯“-Menü „**Nicht mehr lernen**“ statt „Nicht mehr folgen“, mit der
  Rückfrage „Nicht mehr lernen? Dein Lernstand bleibt, falls du ihn wieder
  lernst.“

**Anzeigen für andere:** Zahlen über Folgende lauten „3 lernen mit“ statt
„3 folgen“. Das gilt für Stapelliste, Stapelseite, Moderation und
Löschrückfragen.

**Wörterbuch:**

- „Folgen und diese Karte lernen“ und „Diese Karte lernen“ werden zu
  **„Lernen“**. Darunter steht klein, nur wenn man dem Stapel dafür folgt:
  „Kommt unter Heute dran. Aus „Englisch Unit 3“ lernst du erst einmal nur
  dieses Wort.“ Das Verhalten bleibt wie in Issue #10.
- „Als Kopie in eigenen Stapel“ und „Bearbeiten“ / „Fehler melden“ wandern in
  ein aufklappbares **„Mehr …“** unter dem Treffer. Je Treffer bleibt so eine
  sichtbare Aktion.

**„Aus anderen Stapeln übernehmen“** bleibt im „⋯“-Menü eigener Stapel.

### C. Stapelseite: weniger Schalter

**Weg von der Stapelseite:**

- **Lernplan-Anzeige und „Mit anderem Lernplan starten“.** Neue Durchgänge
  mit anderem Plan lassen sich nicht mehr anlegen; die Route entfällt.
  Bestehende zusätzliche Durchgänge laufen weiter (Heute, Fächer). Der
  Umschalter zwischen Durchgängen erscheint nur noch, wenn es schon mehr als
  einen gibt.
- **Richtungsschalter** (Deutsch → Englisch / Englisch → Deutsch) oben im
  Lernbereich.

**Bleibt bzw. neu:**

- **Fächer-Kacheln** (Neu · Fach 1 … · Kann ich) für vorne → hinten. Mit
  „Auch rückwärts lernen“ erscheint darüber ein kleiner Umschalter, der nur
  die Ansicht der Kacheln wechselt.
- **„Nur diesen Stapel lernen (17)“** als **zweitrangiger** Knopf
  (`btn-secondary`); die Zahl sind fällige und neue Karten aller gelernten
  Richtungen. Er lernt wie bisher fällige und neue Karten dieses Stapels und
  ist **nicht** durch die 20 neuen je Tag begrenzt. Was er beginnt, zählt
  aber zum Tageskontingent von „Heute“ (gezählt wird jede heute begonnene
  Karte). Die Richtung: vorne → hinten, mit Rückwärts-Schalter auch hinten →
  vorne. Nur bei eigenen und gelernten Stapeln.
- Die **Hauptaktion** der Stapelseite ist:
  - eigener Stapel ohne Karten: „Karte hinzufügen“, wie bisher;
  - eigener oder gelernter Stapel mit Karten: kein großer farbiger Knopf.
    Oben steht „Kommt jeden Tag unter **Heute** dran“ mit Link. Darunter
    stehen die Kacheln und „Nur diesen Stapel lernen“;
  - fremder geteilter Stapel: „Lernen“ (siehe B).
- **„Für den Test üben“** als schlichter Knopf darunter (siehe F). Er
  ersetzt das Blatt „Freies Üben“ mit seiner Richtungswahl. Ein einzelnes
  Fach üben geht weiter über die Fächer-Kacheln, wie bisher.
- Im **„⋯“-Menü** neu: Schalter **„Auch rückwärts lernen“** (an/aus) mit dem
  Satz „Dann fragt die App auch Englisch → Deutsch.“ Er gilt für die Person
  und diesen Stapel, für eigene und gelernte Stapel.

**Rückwärts ausschalten:** Rückwärts-Karten kommen nicht mehr dran, weder
fällig noch neu. Ihr Lernstand bleibt und gilt wieder beim Einschalten. Das
ist wie beim Pausieren aus Issue #10.

### D. Lernplan wandert ins Konto

- Der Menüpunkt „Lernplan“ fällt weg. Auf **Mein Konto** gibt es den
  Abschnitt „Lernplan“ mit dem aktuellen Plan („Normal – für jeden Tag“) und
  „Ändern“. Das führt auf die bekannte Seite (`/learn/settings`, Route
  bleibt).
- Die Seite selbst bleibt. Zurück führt sie nach „Mein Konto“.

### E. Hilfe und Texte

- Hilfe: Der Abschnitt „Folgen oder kopieren?“ wird zu **„Stapel von anderen
  lernen“**: „Lernen“ tippen, fertig. Wer etwas ändern will, legt über „⋯“
  eine eigene Fassung an.
- Neuer erster Abschnitt **„So lernst du“** mit drei Schritten: Stapel finden
  → „Lernen“ → jeden Tag „Heute“ → „Los geht's“.
- Die Produktseite (`site/index.html`) und die Landingpage werden
  gegengelesen: „Einem Stapel folgen …“ wird zu „Stapel von anderen
  lernen …“.

### F. Für den Test üben

Für den Abend vor dem Vokabeltest: alle Wörter eines Stapels durchgehen, bis
jedes einmal gewusst ist.

- Der Knopf **„Für den Test üben“** steht auf der Stapelseite (eigene und
  gelernte Stapel, auch auf fremden geteilten vor dem „Lernen“). Darunter
  steht klein: „Alle Karten, bis du jede einmal gewusst hast. Zählt nicht für
  den Karteikasten.“
- Die Runde enthält **alle aktiven Karten** des Stapels in zufälliger
  Reihenfolge, vorne → hinten. Mit „Auch rückwärts lernen“ kommt jede Karte
  zusätzlich hinten → vorne. Es sind höchstens 200 Abfragen
  (`learning.test_max`); bei größeren Stapeln eine zufällige Auswahl, schon in
  der Datenbank gekürzt.
- **„Nochmal“** stellt die Karte ans Ende der Runde, **ohne Grenze**, bis sie
  einmal gewusst ist. Das unterscheidet die Runde von „Lernen“ und vom Fach
  üben, wo eine Karte höchstens dreimal wiederkommt.
- Oben steht das Kennzeichen „Test üben – zählt nicht“, der Fortschritt
  zählt die gewussten Karten („12 / 40“).
- Es wird **nichts gespeichert**: kein Fach, kein Lernstand, keine Lernserie.
- Am Ende steht „Alles gewusst!“ und darunter „32 von 40 gleich beim ersten
  Mal.“ (gezählt werden Abfragen, mit Rückwärts zwei je Karte). Knöpfe:
  „Nochmal üben“ (neue Runde) und „Fertig“.
- Abbrechen (✕) geht jederzeit, auch dabei geht nichts verloren.

### Fehlerfälle

- „Auch rückwärts lernen“ für einen Stapel, den man weder besitzt noch lernt,
  oder für eine ungültige ID ergibt **404**.
- Gleichzeitiges zweimaliges „Los geht's“ (Doppeltipp) legt wegen
  `createOrFirst` und dem Unique-Index keine doppelten Durchgänge oder
  Lernstände an.
- Ein Stapel ohne aktive Karten liefert keine neuen Karten und keinen
  Fehler.

---

## Berechtigungen

Alles bleibt in der Gruppe `auth` und betrifft nur eigene Daten.

| Route | Gruppe | Service |
|---|---|---|
| `POST /learn/round` (ohne Stapel = „Heute“, mit `more=1` = „Noch 20 neue Karten“) | `auth`, bestehend | `StudyRoundService::startToday` – jetzt mit neuen Karten aus `mineQuery` |
| `POST /learn/round` mit `mode=test` und Stapel | `auth`, bestehend | `StudyRoundService::startTest` – nur sichtbare Stapel (`getVisible`) |
| `POST /decks/{deck}/reverse` (neu) | `auth` | `DeckPreferenceService::setReverse` – nur eigene oder gelernte Stapel (`getVisible` + Besitz oder Folgen), sonst 404 |
| `POST /decks/{deck}/runs` | **entfällt** | – |

Keine Rollenprüfungen im Controller. Keine neue öffentliche Route.

## Datenmodell

Neue Tabelle **`deck_preferences`**, Migration
`2026_10_15_000000_create_deck_preferences_table.php`:

| Spalte | Typ |
|---|---|
| `user_id` | `foreignUuid` → `users`, `cascadeOnDelete` |
| `deck_id` | `foreignUuid` → `decks`, `cascadeOnDelete` |
| `with_reverse` | `boolean`, Vorgabe `false` |
| `created_at`, `updated_at` | `timestampsTz` |

Primärschlüssel `(user_id, deck_id)`. Fehlt eine Zeile, gilt
`with_reverse = false`.

**Bestehende Daten:** Die Migration legt für jedes Paar (Person, Stapel), das
schon Lernstand in Richtung `backward` hat, eine Zeile mit
`with_reverse = true` an. So verschwinden niemandem fällige
Rückwärts-Karten.

Für „höchstens 20 neue je Tag“ ist keine Spalte nötig. Gezählt werden
`card_progress`-Zeilen der Person mit `created_at` ab Mitternacht in der
Lern-Zeitzone (`learning.timezone`).

Neu in `config/learning.php`: `new_per_day => 20` und `test_max => 200`.
Das sind feste Produktentscheidungen und keine `.env`-Variablen. Der
Test-Modus braucht keine Daten, er lebt wie jede Runde in der Session.

## Betroffene Stellen

- **app/Services/**
  - `StudyRoundService`:
    - `startToday` füllt nach den fälligen mit neuen Karten auf (Kontingent,
      Wechsel zwischen Stapeln, Richtungen nach `deck_preferences`).
    - `dueQuery` lässt Rückwärts-Karten weg, wenn ausgeschaltet.
    - `startForDeck` nimmt keine Richtung mehr von außen, sondern vorne →
      hinten plus, falls eingeschaltet, hinten → vorne.
    - neu `startTest`, und `answer` wiederholt im Modus `test` ohne
      Grenze.
  - `LearningOverviewService::today`: `new` (heute noch möglich) und
    `more_new` (weitere für „Noch 20 neue Karten“) zusätzlich zu `due` und
    `tomorrow`. Gezählt wird höchstens bis `new_per_day + round_size`.
    `boxes` bleibt je Richtung.
  - `DeckService::copy`: Lernstände behalten ihr `created_at` (zählen also
    nicht als heute begonnen), und „Auch rückwärts lernen“ geht mit.
  - neu `DeckPreferenceService` mit `withReverse(User, Deck)`,
    `setReverse(User, deckId, bool)` und `directionCondition(User)` (SQL-
    Bedingung für die Lernabfragen, wie `CardSelectionService::activeCondition`).
  - `LeitnerService`: `card_progress.created_at` kommt aus der Uhr der
    Anwendung statt aus `now()` der Datenbank, damit „heute begonnen“ stimmt.
  - `DeckRunService::start` und `CHOICES` entfallen. `forStandard` bleibt.
- **app/Http/Controllers/**
  - `DeckController::show`: Richtung nur noch für die Kachel-Ansicht.
  - `DeckController::reverse` (dünn).
  - `DeckRunController::store` entfällt, `reset` bleibt.
  - `StudyRoundController::start`: Richtung aus der Anfrage entfällt beim
    Lernen (beim Freien Üben bleibt sie).
- **routes/web.php**: `POST /decks/{deck}/reverse` hinzu,
  `POST /decks/{deck}/runs` weg.
- **resources/views/**
  - `learn/today.blade.php`: Zählung mit „davon N neu“, neue Texte für
    „geschafft“ und den leeren Zustand.
  - `decks/show.blade.php` und `decks/partials/learn-panel.blade.php`:
    Aufbau wie in C, „Lernen“ statt „Folgen“, Kopieren ins „⋯“-Menü,
    Rückwärts-Schalter.
  - `decks/index.blade.php`, `pages/moderation.blade.php`,
    `partials/card-edit.blade.php`, `admin/user.blade.php`: Wortlaut
    „lernst du“ und „N lernen mit“.
  - `partials/dictionary-results.blade.php`: „Lernen“, Rest in „Mehr …“.
  - `components/nav-links.blade.php`: „Lernplan“ raus.
  - `account.blade.php`: Abschnitt „Lernplan“.
  - `learn/settings.blade.php`: Zurück zu „Mein Konto“.
  - `learn/partials/round-body.blade.php`: Kennzeichen, Fortschritt und
    Ende für den Modus `test`.
  - `pages/help.blade.php`, `pages/landing.blade.php`: Texte.
- **site/index.html**: Texte gegenlesen.
- **config/learning.php**: `new_per_day`, `test_max`.
- **database/migrations/**: siehe Datenmodell.
- **docs/specs/vokabelkarten.md**: Fachbegriff-Tabelle: „abonnieren /
  Live-Verweis → **Lernen**“ (statt „Folgen“). Dazu ein Verweis auf diese
  Datei. Ebenso Hinweise in `lernplaene-und-karten.md` („Mit anderem Lernplan
  starten“ entfällt).
- **CHANGELOG.md**: Eintrag mit `Migration:`-Zeile.

## Tests

**Heute:**
- Ein neu gelernter Stapel ohne Lernstand: „Heute“ zeigt die Karten als
  wartend („davon N neu“), und „Los geht's“ bringt sie.
- Es kommen höchstens 20 neue je Tag: Nach 20 begonnenen Karten zählt die
  nächste Runde nur fällige; am nächsten Tag (`travelTo`) kommen wieder neue.
- Fällige Karten kommen vor neuen, und eine Runde bleibt bei 20.
- Neue Karten wechseln zwischen den Stapeln (2 Stapel × 15 Karten → beide
  vertreten).
- Pausierte Karten (Auswahl) kommen nie als neu.
- Mit Rückwärts aus kommen keine Rückwärts-Karten, weder fällig noch neu.
  Mit Rückwärts an kommen beide Richtungen als eigene neue Karten.
- Bestehende zusätzliche Durchgänge zählen weiter für fällige Karten.

**Für den Test üben:**
- Die Runde enthält alle aktiven Karten (pausierte nicht), mit Rückwärts an
  doppelt.
- Nicht gewusste Karten kommen wieder, auch öfter als dreimal, bis sie
  gewusst sind. Danach endet die Runde mit „Alle N Wörter gewusst“.
- Es entstehen weder `card_progress` noch Lernserie.
- Fremder privater Stapel → 404.

**Stapelseite:**
- Fremder geteilter Stapel: Knopf „Lernen“, kein „Folgen“, Kopieren nur im
  „⋯“-Menü. Nach „Lernen“ erscheint der Stapel unter „Meine Stapel“, und
  „Heute“ zählt seine Karten.
- Kein „Mit anderem Lernplan starten“ und kein Richtungsschalter. Der
  Durchgangs-Umschalter erscheint nur bei mehr als einem Durchgang.
- „Nur diesen Stapel lernen“ lernt den Stapel über das Tageskontingent
  hinaus.
- `POST /decks/{deck}/runs` gibt es nicht mehr (404).

**Rückwärts-Schalter:**
- An/aus für einen eigenen oder gelernten Stapel. Fremder privater Stapel,
  geteilter Stapel, den man nicht lernt, oder ungültige ID → 404.
- Die Migration setzt `with_reverse` für Paare mit Rückwärts-Lernstand
  (Migrationstest wie `UsernameMigrationTest`).

**Wortlaut:**
- Wörterbuch: Der Knopf heißt „Lernen“, Kopie und Bearbeiten stehen in
  „Mehr …“.
- Navigation ohne „Lernplan“, Konto mit Abschnitt „Lernplan“.
- `ProductSiteTest` bleibt grün.

## Annahmen und Risiken

- **20 neue je Tag** von selbst, mehr jederzeit mit einem Tipp („Noch 20
  neue Karten“, Rückmeldung 15.10.2026). Der Knopf bleibt zweitrangig, damit
  „Los geht's“ die eine Hauptaktion bleibt.
- **Viele Stapel auf einmal:** Wer zehn Stapel neu lernt, bekommt trotzdem
  nur 20 neue Karten am Tag. Das ist gewollt, damit am nächsten Tag kein Berg
  fälliger Karten wartet.
- **Bisherige Rückwärts-Lerner** behalten über die Migration ihre
  Rückwärts-Karten. Wer bisher nur in der Stapelansicht auf „Englisch →
  Deutsch“ geschaltet, aber nie gelernt hat, verliert nichts.
- **Durchgänge mit anderem Plan** lassen sich nicht mehr neu anlegen. Für
  Erwachsene, die das nutzten, ist das ein Verlust. Die Zielgruppe hat es
  nicht verstanden. Bestehende laufen weiter, zurücksetzen geht weiter.
- **„Folgen“ heißt in der Oberfläche jetzt „Lernen“,** im Code bleibt
  `follow`/`deck_follows`. Bezeichner bleiben englisch, das passt zu
  CLAUDE.md.
- **Leitlinie 3 (eine Hauptaktion):** Auf der Stapelseite eines gelernten
  Stapels gibt es bewusst **keinen** großen farbigen Knopf. Die Hauptaktion
  liegt auf „Heute“, und ein zweiter „Lernen“-Knopf in Primärfarbe würde
  wieder zwei Orte zum Lernen nahelegen. Das weicht vom Wortlaut der
  Leitlinie ab und ist hier begründet.
- **Offen für später:** Ein Stapel-Link, der ohne Suche direkt „Lernen“
  anbietet, funktioniert schon heute über `/decks/{id}`. Ein „Teilen“-Knopf,
  der den Link kopiert, wäre die nächste kleine Verbesserung für Eltern und
  Lehrkräfte.
