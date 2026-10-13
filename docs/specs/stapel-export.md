# Mehrere Stapel exportieren und importieren (ZIP)

Status: **umgesetzt**

Issue #9. Entscheidungen vom 13.10.2026: erneuter Import **erkennt und
aktualisiert** (1a), exportierbar sind **eigene und gefolgte** Stapel (2b),
Import als **eine ZIP-Datei**, ohne Teilen-Status, mit Beschreibung, mit
Übersichtsdatei.

## Ziel

Eine Auswahl von Stapeln lässt sich als eine ZIP-Datei herunterladen – zum
Sichern, zum Bearbeiten in einer Tabelle und zum Weitergeben als
„Standardset“ – und später wieder importieren. Ein erneuter Import
aktualisiert die passenden Stapel, statt Doppelte anzulegen.

## Nicht dabei

- Lernstand, Durchgänge, Folgen und Teilen-Status (importierte Stapel sind
  **privat**).
- Mehrere lose CSV-Dateien auf einmal hochladen. Eine einzelne CSV geht wie
  bisher über „Liste einfügen“ in einen bestehenden Stapel.
- Löschen per Import: fehlende Karten oder Stapel bleiben.
- Import in fremde Stapel – auch nicht für Admins (dafür gibt es den
  Admin-Import je Stapel aus [csv-und-produktseite.md](csv-und-produktseite.md)).
- Andere Formate (JSON, Anki, `.xlsx`).

---

## Verhalten

### Export

- Unter **Stapel → Meine** ein Knopf **„Stapel exportieren“** (neben dem
  Suchfeld bzw. im Leerzustand nicht). Er öffnet eine Seite mit allen
  eigenen und gefolgten Stapeln als Liste mit Häkchen, oben „Alle
  auswählen“. Darunter **„Als ZIP herunterladen“**.
- Ohne Auswahl → Meldung „Bitte mindestens einen Stapel wählen.“
- Die Datei heißt `cardbox-stapel-JJJJ-MM-TT.zip` und enthält:
  - **`stapel.csv`** – die Übersicht, gleiches Format wie die Karten-CSV
    (UTF-8 mit BOM, `;`, CRLF):

    | Spalte | Inhalt |
    |---|---|
    | `Datei` | Name der Karten-CSV im ZIP |
    | `ID` | ID des Stapels |
    | `Name` | Name |
    | `Beschreibung` | Beschreibung (z. B. ISBN), kann leer sein |
    | `Vorne`, `Hinten` | Sprachkürzel (`de`, `en`, …) |
    | `Karten` | Anzahl, nur zur Information |

  - je Stapel eine **Karten-CSV** im bekannten Format
    (`ID;de;en;Hinweis`, siehe Hilfe „Tabellen (CSV)“), benannt wie beim
    Einzel-Export (`englisch-unit-3_de-en.csv`, bei gleichen Namen mit
    `-2`, `-3`).
- Gefolgte Stapel werden exportiert, wie sie gerade sind.

### Import

- Unter **Stapel → Meine** ein Knopf **„Stapel importieren“**: Seite mit
  einem Dateifeld (nur `.zip`) und **„Vorschau“**.
- **Vorschau** – nichts wird gespeichert. Je Stapel aus `stapel.csv` eine
  Zeile:

  | Fall | Anzeige | Beim Import |
  |---|---|---|
  | Ein **eigener** Stapel hat diese ID | „wird aktualisiert: 3 neu · 2 geändert · 40 unverändert“ | Name und Beschreibung aus der Datei, Karten wie beim CSV-Import (neu/geändert, nichts gelöscht, Lernstand bleibt) |
  | Ein eigener Stapel ist **aus diesem Stapel entstanden** (Kopie, früherer Import) | wie oben, mit „deine Kopie“ | wie oben, in die Kopie |
  | sonst | „wird neu angelegt (45 Karten)“ | neuer **privater** Stapel; Karten, deren ID eine für dich sichtbare Karte ist, merken sich ihr Original (wie beim Übernehmen) |
  | Sprachen passen nicht zum vorhandenen Stapel | „Sprachen passen nicht (de → fr statt de → en)“ | übersprungen |
  | Karten-CSV fehlt oder ist kaputt | „Datei fehlt“ / Grund | übersprungen |

  Fehlerzeilen innerhalb einer Karten-CSV werden wie beim CSV-Import
  gezählt und übersprungen („2 Zeilen werden nicht übernommen“,
  aufklappbar).
- **Importieren** führt alles in **einer** Transaktion aus. Ergebnis als
  Meldung: „2 Stapel aktualisiert, 1 neu angelegt.“ Danach geht es zu
  „Meine“.
- **Ohne `stapel.csv`** (z. B. von Hand gepackt): Jede Karten-CSV im ZIP
  wird ein Stapel; Name aus dem Dateinamen, Sprachen aus der Kopfzeile,
  keine Beschreibung, immer neu.
- Der erneute Import derselben Datei ändert nichts mehr („alles
  unverändert“) und legt keine Doppelten an.

### Herkunft von Stapeln

Damit Kopien wiedererkannt werden, merkt sich ein Stapel, aus welchem er
entstanden ist:

- **Kopieren** in der App (bestehend) setzt sie auf den Originalstapel.
- **Import als neuer Stapel** setzt sie auf die ID aus `stapel.csv`.
- Kein Fremdschlüssel: Das Original darf verschwinden oder auf einer
  anderen Installation liegen.

### Grenzen und Schutz

- ZIP höchstens **5 MB**, entpackt höchstens **20 MB** (Schutz vor
  „ZIP-Bomben“), höchstens **50 Stapel**, je Stapel die bekannten
  **1000 Zeilen**.
- Gelesen werden nur `.csv`-Einträge im obersten Ordner (auch aus einem
  einzigen Unterordner, wie macOS sie beim Packen anlegt); alles andere
  wird ignoriert. Nichts wird auf die Platte entpackt.
- Formelschutz (`'`) beim Export und beim Lesen wie bei der Karten-CSV.
- Ohne PHP-Erweiterung `zip` sind Export und Import nicht verfügbar; die
  Knöpfe erklären das („auf diesem Server nicht verfügbar“). Die
  Deploy-Prüfung führt `ext-zip` als **freiwillige** Prüfung: Sie zeigt ✓
  oder einen Hinweis, verhindert aber nicht die Migration.

### Fehlerfälle

- Keine ZIP / kaputte ZIP / zu groß → Meldung am Feld, nichts passiert.
- ZIP ohne einen einzigen lesbaren Stapel → „In der Datei wurde kein
  Stapel gefunden.“
- Fremde oder unsichtbare Stapel-IDs in der Export-Auswahl → werden
  ignoriert (nur eigene und gefolgte sind wählbar).
- Ein Stapel der Datei hat **dieselbe ID wie ein fremder** Stapel →
  behandelt wie „unbekannt“: neuer eigener Stapel.

## Berechtigungen

| Was | Route | Gruppe | Prüfung im Service |
|---|---|---|---|
| Export-Seite, Download | `GET /decks/export`, `POST /decks/export` | `auth` | nur `DeckService::mineQuery` (eigene + gefolgte) |
| Import-Seite, Vorschau, Import | `GET /decks/import`, `POST /decks/import/preview`, `POST /decks/import` | `auth` | schreibt nur in **eigene** Stapel; neue gehören der Person |

Vorschau und Import gedrosselt wie „Liste einfügen“ (20 pro Minute). Keine
neuen `PUBLIC_ROUTES`.

## Datenmodell

**Migration `2026_10_14_000000_add_source_deck_id_to_decks_table.php`**

- `decks.source_deck_id` – uuid, nullable, **kein** Fremdschlüssel, Index.
  Gesetzt von „Kopieren“ und vom Import neuer Stapel.
- Vorhandene Kopien bekommen sie nicht nachträglich (die Herkunft wurde
  bisher nicht gespeichert, nur `copied_from_name`).

## Betroffene Stellen

Umgesetzt wie beschrieben, mit diesen Präzisierungen:
- Zwischen Vorschau und Import liegt die ZIP unter
  `storage/app/private/imports/` (Verweis in der Sitzung), weil ein
  Dateifeld nicht erneut gesendet werden kann. Nach dem Import wird sie
  gelöscht, liegen gebliebene nach einer Stunde beim nächsten Upload.
- Ein Stapel ohne Karten in der ZIP wird trotzdem angelegt bzw.
  aktualisiert (beim Einzel-Import bleibt eine leere Datei ein Fehler).
- Ohne `stapel.csv` wird der Name aus dem Dateinamen gebildet
  (`englisch-unit-3_de-en.csv` → „Englisch unit 3“).
- Tests: `tests/Feature/DeckBundleTest.php`.

Geplante Stellen:

- `app/Services/DeckBundleService.php` (neu): `export(User, deckIds)` →
  ZIP als Text; `parse(User, zipPath)` → Vorschau je Stapel;
  `import(User, zipPath)` → Ergebnis. Nutzt `CardCsvService` für jede
  Karten-CSV (Parsen, Formelschutz, Zeilenregeln).
- `CardCsvService`: Parsen und Import für einen **neuen** Stapel öffnen
  (heute nur in einen bestehenden).
- `DeckService::copy`: `source_deck_id` setzen.
- `DeployService`: freiwillige Prüfung `ext-zip` (Hinweis statt ✗);
  Doku der Voraussetzungen.
- `app/Http/Controllers/DeckBundleController.php` (neu).
- `routes/web.php`: Routen vor `/decks/{deck}` (statische Pfade).
- Views: `decks/export.blade.php`, `decks/import-bundle.blade.php`,
  `decks/import-bundle-preview.blade.php`; Knöpfe in `decks/index`.
- `config/learning.php`: `bundle_max_kilobytes` (5120),
  `bundle_max_unpacked_kilobytes` (20480), `bundle_max_decks` (50).
- Hilfe „Tabellen (CSV)“ um „Mehrere Stapel als ZIP“ ergänzen;
  `CHANGELOG.md` mit `Migration:`-Zeile; README (Voraussetzung `zip`).

## Tests

- Export: nur ausgewählte eigene und gefolgte Stapel, fremde IDs
  ignoriert; `stapel.csv` mit Spalten, Beschreibung, Sprachen; Karten-CSVs
  im bekannten Format; gleiche Namen → `-2`; leere Auswahl → Meldung.
- Rundreise: Export → Import bei derselben Person → alles „unverändert“,
  keine neuen Stapel, keine Schreibvorgänge.
- Import aktualisiert eigenen Stapel (Name, Beschreibung, Karten neu/
  geändert, nichts gelöscht, Lernstand bleibt).
- Gefolgter Stapel → beim ersten Import neue Kopie mit `source_deck_id`
  und Karten-Herkunft; beim zweiten Import wird die Kopie aktualisiert,
  keine zweite.
- Andere Person importiert → alles neu, privat, ihr gehörend.
- Sprachen passen nicht → Stapel übersprungen, Rest importiert.
- Ohne `stapel.csv` → je CSV ein neuer Stapel aus Dateiname/Kopfzeile.
- Grenzen: zu große ZIP, entpackt zu groß, zu viele Stapel, kaputte ZIP,
  ZIP ohne CSV; Unterordner von macOS; Nicht-CSV-Einträge ignoriert.
- Import läuft in einer Transaktion: Fehler in Stapel 3 → nichts
  gespeichert.
- Kopieren setzt `source_deck_id`.
- Migration läuft vor und zurück.

## Annahmen und Risiken

- **Wiedererkennen über `source_deck_id`** – nötig, damit gefolgte Stapel
  (2b) beim erneuten Import nicht doppelt angelegt werden. Gibt es mehrere
  eigene Kopien desselben Stapels, wird die **zuletzt angelegte**
  aktualisiert.
- **Name und Beschreibung werden beim Aktualisieren überschrieben.** Wer
  den Namen in der App geändert hat und eine alte Datei importiert, bekommt
  den alten Namen zurück. Die Vorschau zeigt es („Name: alt → neu“).
- **Herkunft der Karten bei gefolgten Stapeln:** Die Karten der Kopie
  merken sich ihre Originale, Korrekturen am Original werden also
  angeboten – wie beim Kopieren in der App.
- **`zip`-Erweiterung auf dem Webspace:** Der nächste Deploy zeigt, ob sie
  da ist. Fehlt sie, bleibt der Rest von Cardbox nutzbar, nur die
  ZIP-Funktionen melden „auf diesem Server nicht verfügbar“; sie lässt
  sich meist im Kundenmenü einschalten.
- **Große Sets:** 50 Stapel × 1000 Zeilen in einer Anfrage sind auf
  kleinem Webspace an der Grenze der Laufzeit; bei Bedarf die Grenze
  senken.
