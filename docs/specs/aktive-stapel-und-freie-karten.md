# Stapel pausieren und Karten ohne Sprache

Status: **umgesetzt** (19.10.2026)

Aus den Issues #15 (Aktive Lernstapel) und #14 (Generische Karten).
Entscheidungen (Rückfragen vom 19.10.2026):

- **Pausiert wird über einen Schalter im Stapel.** Er steht im ⋯-Menü, wie
  „Antworten eintippen“. Neue und gefolgte Stapel sind automatisch aktiv.
- **Pausiert heißt nur: nicht auf „Heute“.** Auf der Stapelseite bleibt der
  Stapel lern- und übbar.
- **Karten ohne Sprache heißen fest „Vorderseite → Rückseite“.**
- **Sie werden eingetippt wie Sprachen geprüft**, aber nicht vorgelesen.

## Nicht dabei

- Eine Auswahlliste mit Häkchen auf „Heute“.
- Frei benennbare Seiten (z. B. „Land → Hauptstadt“).
- Pausieren für eine bestimmte Zeit („bis nach den Ferien“).

---

## A. Stapel pausieren (#15)

### Bedienung

- Im ⋯-Menü eigener und gefolgter Stapel steht der Schalter **„Gerade nicht
  lernen“**. Daneben ist ein Kennzeichen „pausiert“ oder „aus“ zu sehen.
- Darunter steht die Erklärung: „Der Stapel kommt nicht auf ‚Heute‘. Hier
  kannst du ihn trotzdem lernen.“
- Ist ein Stapel pausiert, zeigt die Stapelseite oben den Hinweis
  „Pausiert: Dieser Stapel kommt nicht auf ‚Heute‘.“ mit dem Knopf
  **„Wieder lernen“**.
- Unter „Stapel“ trägt ein pausierter Stapel das Kennzeichen **„pausiert“**.
- Die Einstellung gilt **je Person**. Wer einen gefolgten Stapel pausiert,
  ändert nichts für die Besitzerin.
- Wer einem Stapel neu folgt (auch nach Entfolgen), hat ihn wieder aktiv.
  Eine Kopie startet ebenfalls aktiv.

### Was ein pausierter Stapel nicht mehr tut

- Er erscheint nicht unter „Meine Stapel“ auf **„Heute“**. Darunter steht
  stattdessen „1 Stapel ist pausiert und kommt hier nicht dran.“ mit einem
  Link zu „Stapel“.
- Er zählt nicht zu den wartenden Karten, nicht zu „Los geht's“ und nicht
  zu „Noch N neue Karten“.
- Für die **Erinnerung** gelten nur die Karten aktiver Stapel.
- Bleibt in den aktiven Stapeln nichts zu tun, zählt der Tag als geschafft,
  auch wenn ein pausierter Stapel noch fällige Karten hat. Das ist dieselbe
  Regel wie bisher, nur ohne die pausierten Stapel. **Ohne einen einzigen
  aktiven Stapel gilt das nicht**: Sonst könnte man alles pausieren und mit
  einer Übungskarte jeden Tag grün machen.
- In der **Eltern-Ansicht** fehlen der Stapel, seine kniffligen Wörter und
  seine zuletzt gelernten Wörter. Ein Satz nennt, **wie viele** Stapel
  pausiert sind (ohne Namen), damit nichts unbemerkt verschwindet.

### Was bleibt

- Der Lernstand bleibt erhalten. Fällige Karten warten, bis der Stapel
  wieder aktiv ist.
- Auf der Stapelseite funktionieren „Nur diesen Stapel lernen“, „Für den Test
  üben“ und „Knifflige Wörter üben“ weiter. Antworten dort zählen auch fürs
  Tagesziel, denn gelernt ist gelernt.
- Der **Wortschatz** („Du kannst schon N Wörter“) zählt weiter alle Stapel,
  weil gelernte Wörter beim Pausieren nicht vergessen werden.
- Sind alle Stapel pausiert, zeigt „Heute“ den Text „Alle deine Stapel sind
  pausiert“ und den Knopf „Zu meinen Stapeln“.

### Berechtigungen

`POST /decks/{deck}/pause` gehört in die Gruppe `auth`. Die Route gilt nur
für eigene und gefolgte Stapel, alle anderen ergeben 404. Das prüft
`DeckPreferenceService::set`, wie bei den übrigen Schaltern.

### Datenmodell

Migration `2026_10_19_000000_add_paused_to_deck_preferences.php`: Die Tabelle
`deck_preferences` bekommt die Spalte `paused` (boolean, Vorgabe `false`).

### Betroffene Stellen

- **Services:**
  - `DeckService`: `activeQuery` und `active` (die Stapel für „Heute“) sowie
    `pausedIds`.
  - `DeckPreferenceService`: `paused` und `setPaused`.
  - `LearningOverviewService`: `today`, `myDecksWithStats`, `hardWords` und
    `recentlyMastered` nehmen nur aktive Stapel. `vocabulary` nimmt weiter
    alle.
  - `StudyRoundService`: `startToday` und `finish` nehmen nur aktive Stapel.
- **Controller:** `DeckController::pause`, `LearnController` (`pausedCount`).
- **Ansichten:**
  - Stapelseite: Schalter und Hinweis.
  - `deck-row`: Kennzeichen „pausiert“.
  - „Heute“: Link auf die pausierten Stapel und Leerzustand.
  - Hilfe.

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

- **Pausieren:**
  - Ein pausierter Stapel fehlt auf „Heute“ und in „Los geht's“, bleibt aber
    auf der Stapelseite lernbar und hat in der Liste das Kennzeichen.
  - Er lässt sich wieder aktivieren.
  - „Heute“ zeigt einen eigenen Leerzustand, wenn alle Stapel pausiert sind.
  - Ein leerer aktiver Rest gilt als geschafft.
  - Die Eltern-Ansicht lässt pausierte Stapel weg.
  - Fremde Stapel ergeben 404.
  - Das Pausieren gilt nur für die eigene Person.
- **Vorderseite und Rückseite:**
  - Anlegen, Beschriftung, kein Vorlesen.
  - Eintippen wird tolerant geprüft.
