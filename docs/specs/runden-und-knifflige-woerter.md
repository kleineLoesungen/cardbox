# Karten je Runde und knifflige Wörter

Status: **umgesetzt** (18.10.2026), direkt nach Auftrag ohne Rückfragen;
die Entscheidungen stehen hier.

Aus der Vorschlagsliste vom 16.10.2026, Punkte 3 und 4.

## Ziel

Kinder können kürzere Runden wählen, die schneller geschafft sind. Wörter,
die immer wieder falsch sind, fallen auf, lassen sich gezielt üben und
bekommen einen Anstoß zur Eselsbrücke.

## Nicht dabei

- Eine eigene Liste „Knifflige Wörter“ über alle Stapel.
- Eselsbrücken als eigenes Feld. Dafür gibt es den Hinweis der Karte.
- Rundengröße je Stapel.

## Verhalten

### A. Karten je Runde

- Unter **Mein Konto → Karten je Runde**: 5 · 10 · 20 (Vorgabe 20, wie
  bisher), mit „Speichern“. Gilt für „Los geht's“, „Nur diesen Stapel
  lernen“ und „Fach üben“. „Für den Test üben“ nimmt weiter alle Karten.
- „Noch N neue Karten“ auf „Heute“ bietet so viele an, wie eine Runde fasst.
- Tagesziel und „20 neue am Tag“ bleiben unabhängig davon.
- Andere Werte → Meldung „Bitte 5, 10 oder 20 wählen.“

### B. Knifflige Wörter

- **Knifflig** ist eine aktive Karte, die die Person in einer gelernten
  Richtung und einem Durchgang mindestens **3-mal falsch** beantwortet hat
  (`review_count − correct_count`, `learning.hard_wrong`) und die dort noch
  nicht in „Kann ich“ liegt. Es braucht keine neue Spalte.
- Auf der Stapelseite (eigene und gelernte Stapel):
  - Kennzeichen **„knifflig“** an der Karte in der Liste.
  - Knopf **„Knifflige Wörter üben (N)“** unter „Für den Test üben“: eine
    Test-Runde nur mit diesen Karten (zählt nicht für den Karteikasten).
    „Nochmal üben“ am Ende bleibt bei dieser Auswahl.
- Am Ende einer Lernrunde: „**Knifflig:** Hund – dog, …“ für Karten, die in
  dieser Runde falsch waren und jetzt knifflig sind (höchstens 5 genannt),
  darunter „Denk dir eine Eselsbrücke aus – zum Beispiel ein Bild im Kopf
  oder einen Reim.“

## Berechtigungen

`POST /account/round-size` in der Gruppe `auth`, eigenes Konto.
„Knifflige Wörter üben“ läuft über `POST /learn/round` (`mode=test`,
`only=hard`), nur sichtbare Stapel.

## Datenmodell

Migration `2026_10_18_000000_add_round_size_to_learning_settings.php`:
`learning_settings.round_size` (`smallInteger`, Vorgabe 20).

## Betroffene Stellen

- `LeitnerService::roundSize`, `LearningSettingsService::setRoundSize`,
  `LearningOverviewService::hardCardIds`, `StudyRoundService` (Rundengröße,
  `startTest(…, hardOnly)`, `missed` in der Runde, `hardWords`).
- `AccountController::updateRoundSize`, `DeckController` (`hardIds`),
  `StudyRoundController` (`only=hard`, Endbildschirm).
- Ansichten: Konto, Stapelseite, Kartenzeile, Lernrunde, Hilfe.

## Tests

`tests/Feature/RoundsAndHardWordsTest.php`: Rundengröße wirkt und wird
geprüft; Grenzfälle der Definition „knifflig“ (2 falsch nein, „Kann ich“
nein); Knopf nur bei eigenen/gelernten Stapeln mit kniffligen Karten;
Rundenende nennt nur knifflige Wörter dieser Runde; „Nochmal üben“ behält
die Auswahl.

## Annahmen und Risiken

- **3 Fehler** als Grenze ist eine Annahme (Konfiguration).
- Ein Wort bleibt knifflig, bis es in „Kann ich“ ankommt, auch wenn es
  inzwischen oft richtig war. Gezählt wird über die ganze Lernzeit.
