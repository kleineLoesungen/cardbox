# „Lerne ich“, pausiert und Karten ohne Sprache

Status: **umgesetzt** (19.10.2026), überarbeitet am selben Tag

Aus den Issues #15 (Aktive Lernstapel) und #14 (Generische Karten).

Entscheidungen (Rückfragen vom 19.10.2026, zweite Runde: „Das System muss
einfach sein“):

- **Drei Zustände je Person und Stapel:** **„Lerne ich“**, **„pausiert“** und
  ohne Kennzeichen. Nur „Lerne ich“ kommt auf „Heute“ und in die
  Eltern-Ansicht.
- **Umschalten direkt auf der Stapelseite**, ohne Menü.
- **Neu angelegte eigene Stapel sind gleich „Lerne ich“.**
- **Es gibt kein Folgen, nur Lernen:** „Lernen“ auf einem geteilten Stapel
  macht ihn zu „Lerne ich“.
- **Lernen und Üben:** Lernen mit dem Karteikasten heißt „Lerne ich“. Üben
  („Für den Test üben“, „Knifflige Wörter üben“, „Fach üben“) ändert den
  Zustand nicht.
- **„Heute“** zeigt nur „Lerne ich“. **„Stapel“** zeigt weiter „Meine“ und
  „Entdecken“ mit passenden Kennzeichen; „Meine“ getrennt nach „Lerne ich“,
  „Pausiert“ und „Weitere Stapel“.
- **Karten ohne Sprache heißen fest „Vorderseite → Rückseite“**, werden beim
  Eintippen wie Sprachen geprüft und nicht vorgelesen.

## Nicht dabei

- Eine Auswahlliste mit Häkchen auf „Heute“.
- Frei benennbare Seiten (z. B. „Land → Hauptstadt“).
- Pausieren für eine bestimmte Zeit („bis nach den Ferien“).

---

## A. „Lerne ich“ und pausiert (#15)

### Die drei Zustände

| Zustand | Kennzeichen | Auf „Heute“ und bei den Eltern | Knopf auf der Stapelseite |
|---|---|---|---|
| Lerne ich | „lerne ich“ (grün) | ja | „Pausieren“ |
| Pausiert | „pausiert“ | nein | „Wieder lernen“ |
| Ohne Kennzeichen | – | nein | „Lernen“ |

- **Ohne Kennzeichen** sind nur eigene Stapel, die man noch nie gelernt hat:
  nach dem Update die eigenen Stapel ohne Lernstand. Neue Stapel starten als
  „Lerne ich“; wer einen nicht lernen will (z. B. eine Lehrkraft für die
  Klasse), pausiert ihn. Geteilte Stapel anderer sind entweder „Lerne ich“
  oder „pausiert“ (lernt man sie nicht mehr, verschwinden sie mit „Nicht mehr
  lernen“ aus „Meine“); das gilt auch, wenn ein Admin einen geteilten Stapel
  an jemand anderen überträgt und die bisherige Besitzerin ihn weiterlernt.
- **Wörterbuch:** „lerne ich“ steht nur an Wörtern, die wirklich auf „Heute“
  kommen. Bei pausierten oder nicht gelernten Stapeln steht „Lernen“; das
  macht den Stapel zu „Lerne ich“.
- Der Zustand gilt **je Person**. Pausiert jemand einen geteilten Stapel,
  ändert das nichts für die Besitzerin.

### Wie ein Stapel zu „Lerne ich“ wird

- Er wird angelegt (auch als Kopie).
- „Lernen“ auf einem geteilten Stapel, auch im Wörterbuch.
- „Lernen“ oder „Wieder lernen“ auf der Stapelseite.
- Eine Lernrunde mit dem Karteikasten („Nur diesen Stapel lernen“). Den
  Knopf gibt es nur bei „Lerne ich“; die Regel greift auch für Wege von
  außerhalb.
- **Üben** ändert nichts.

### Stapelseite

- Ein Kasten über dem Lernbereich zeigt den Zustand und den einen Knopf
  dazu (Tabelle oben).
- „Nur diesen Stapel lernen“ gibt es nur bei „Lerne ich“. Üben geht immer.
- Der Schalter im ⋯-Menü entfällt.

### Stapelliste und Heute

- **Stapel → Meine:** Abschnitte **„Lerne ich (N)“**, **„Pausiert (N)“** und
  **„Weitere Stapel (N)“**, jeweils nur, wenn es welche gibt. Pausierte tragen
  zusätzlich das Kennzeichen „pausiert“.
- **Stapel → Entdecken:** „lerne ich“ oder „pausiert“ bei Stapeln, die man
  lernt.
- **Heute:** die Liste heißt „Lerne ich“. Ist kein Stapel „Lerne ich“, aber
  es gibt welche: „Du lernst gerade keinen Stapel“ mit „Zu meinen Stapeln“.
- Auch das Kennzeichen im Wörterbuch heißt jetzt „lerne ich“.

### Was nur für „Lerne ich“ gilt

- „Heute“: wartende Karten, „Los geht's“, „Noch N neue Karten“.
- Die **Erinnerung**.
- Bleibt in den „Lerne ich“-Stapeln nichts zu tun, zählt der Tag als
  geschafft. **Ohne einen einzigen „Lerne ich“-Stapel gilt das nicht.**
- **Eltern-Ansicht:** Stapel, knifflige Wörter, zuletzt gelernte Wörter.
  Andere Stapel werden dort weder gelistet noch gezählt.

### Was bleibt

- Der Lernstand bleibt in jedem Zustand erhalten.
- Antworten beim Üben zählen fürs Tagesziel, egal in welchem Zustand.
- Der **Wortschatz** („Du kannst schon N Wörter“) zählt alle Stapel.

### Berechtigungen

`POST /decks/{deck}/learning` (`on=1` Lernen, `on=0` Pausieren) liegt in der
Gruppe `auth`. Die Route gilt nur für eigene und gelernte Stapel, sonst 404
(`DeckPreferenceService::setLearning`). Geteilte Stapel anderer starten mit
`POST /decks/{deck}/follow` („Lernen“).

### Datenmodell

- Migration `2026_10_19_000000_add_paused_to_deck_preferences.php`:
  `deck_preferences.paused` (boolean, Vorgabe `false`).
- Migration `2026_10_19_100000_add_learning_to_deck_preferences.php`:
  `deck_preferences.learning` (boolean, **Vorgabe `true`**). Ohne Zeile gilt
  „Lerne ich“; so sind neue und gelernte Stapel ohne weiteres dabei.
- Die Migration setzt eigene Stapel, in denen die Besitzerin **keinen
  Lernstand** hat, auf „ohne Kennzeichen“. Eigene Stapel mit Karten in den
  Fächern und gelernte geteilte Stapel bleiben „Lerne ich“, pausierte bleiben
  pausiert.

### Betroffene Stellen

- **Services:**
  - `DeckService`: `activeQuery` und `active` (nur „Lerne ich“),
    `learningStates`, `startLearning`, `setLearningState`; `follow` nutzt
    `startLearning`.
  - `DeckPreferenceService`: `learningState`, `setLearning`.
  - `LearningOverviewService`: `today`, `myDecksWithStats`, `hardWords`,
    `recentlyMastered` nur „Lerne ich“; `vocabulary` alle.
  - `StudyRoundService`: `startToday` und `finish` nur „Lerne ich“;
    `startForDeck` macht den Stapel zu „Lerne ich“.
- **Controller:** `DeckController::learning`, `index` (`states`), `show`
  (`learningState`); `LearnController` (`hasOtherDecks`).
- **Ansichten:** Stapelseite (Zustandskasten), Lernbereich, Stapelliste
  (Abschnitte, Kennzeichen), „Heute“, Eltern-Ansicht, Wörterbuch, Hilfe.

## B. Vorderseite und Rückseite (#14)

- Als Seiten eines Stapels gibt es zusätzlich **„Vorderseite“** (`front`) und
  **„Rückseite“** (`back`), neben den Sprachen und „Aufgabe“/„Ergebnis“.
- Beim Anlegen und Ändern eines Stapels stehen sie in denselben Auswahllisten.
  Eine Migration braucht es nicht: `Language` ist ein Enum, und Karten speichern
  reinen Text.
- **Vorlesen:** Keine der beiden Seiten hat ein Sprachkürzel (`tag()` ist
  `null`), deshalb erscheint der Knopf nicht.
- **Eintippen:** Die Prüfung ist so tolerant wie bei Sprachen: Groß/Klein ist
  egal, und kleine Tippfehler ergeben „Fast!“. Genau geprüft werden weiter
  nur „Aufgabe“ und „Ergebnis“.
- **CSV und ZIP** nutzen die Kürzel `front` und `back`.
- Die Startseite und die Produktseite nennen die Karten unter „Auch Mathe und
  mehr“.

## Tests

`tests/Feature/ActiveDecksAndFreeCardsTest.php`:

- **Zustände:**
  - Pausieren direkt auf der Stapelseite: weg von „Heute“ und „Los geht's“,
    Üben bleibt, Abschnitte in der Stapelliste, wieder lernen.
  - Ohne Kennzeichen: nicht auf „Heute“, „Lernen“ holt den Stapel dorthin.
  - Neue eigene Stapel sind „Lerne ich“.
  - Geteilte Stapel: „Lernen“ statt Folgen, Kennzeichen unter „Entdecken“,
    nach „Nicht mehr lernen“ und erneutem „Lernen“ wieder dabei.
  - Lernen mit dem Karteikasten macht „Lerne ich“, Üben nicht.
  - Tag geschafft nur mit „Lerne ich“-Stapeln.
  - Eltern sehen nur „Lerne ich“.
  - Fremde Stapel ergeben 404; der Zustand gilt je Person.
  - Die Migration setzt die Zustände wie beschrieben.
- **Vorderseite und Rückseite:** Anlegen, Beschriftung, kein Vorlesen,
  CSV, tolerantes Eintippen.
