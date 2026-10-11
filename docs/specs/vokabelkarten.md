# Vokabelkarten mit Karteikasten (Neuaufbau von vocaBox)

Vorlage: [kleineLoesungen/vocaBox](https://github.com/kleineLoesungen/vocaBox),
Zweig `init-vocabox` (Stand 7247dfb). Auf `main` liegt dort nur das
Nuxt-Grundgerüst; die Fachlichkeit steckt ausschließlich in diesem Zweig.

## Ziel

Schülerinnen und Schüler ab 10 Jahren und technisch unerfahrene Erwachsene
lernen Vokabeln **am Smartphone** mit einem digitalen Karteikasten: Stapel
anlegen oder übernehmen, Karten schnell eintippen oder einfügen, täglich die
fälligen Karten abfragen. Fachlich gleichwertig zu vocaBox (Leitner-System,
zwei Richtungen, freies Üben, geteilte Stapel). Die Bedienung wird aber neu
gedacht: einfacher, daumenfreundlich und mit weniger Begriffen.

## Leitlinien für Oberfläche und Bedienung

Diese Regeln gelten für jede Seite dieser Funktion. Bei Widerspruch zu
einzelnen Abläufen weiter unten gelten sie zuerst.

1. **Nur Smartphone.** Entworfen für 360–430 px Breite im Hochformat. Auf
   größeren Bildschirmen bleibt der Inhalt eine zentrierte Spalte
   (`max-w-md`); es gibt keine eigene Desktop-Gestaltung.
2. **Daumenzone.** Hauptaktionen liegen unten: in der Tab-Leiste, als
   schwebender Knopf („+") oder als breite Knöpfe am unteren Rand. Oben
   stehen nur Titel und Zurück.
3. **Eine Hauptaktion je Bildschirm**, groß und farbig (`btn-primary btn-lg
   btn-block`). Alles Weitere ist zweitrangig und steckt im „⋯"-Menü, das
   als Blatt von unten erscheint.
4. **Fingergerechte Flächen**: mindestens 48 px hoch, mindestens 8 px
   Abstand zwischen Tippflächen. Grundschrift 16 px, auf der Lernkarte
   deutlich größer.
5. **Einfache Sprache.** Kurze Sätze, „du", keine Fachwörter. Kein „Set",
   „Leitner", „CSV", „Box", „Ausgangs-/Zielsprache", „Tag". Stattdessen:

   | Fachbegriff | in der Oberfläche |
   |---|---|
   | Set | **Stapel** |
   | Box / Leitner-Kasten | **Fach** im **Karteikasten** |
   | gemeistert | **Kann ich** |
   | Ausgangs-/Zielsprache | **vorne** / **hinten** (z. B. „vorne Deutsch, hinten Englisch") |
   | vorwärts / rückwärts | „Deutsch → Englisch" / „Englisch → Deutsch" |
   | Übungsmodus | **Freies Üben** (zählt nicht) |
   | öffentlich | **Mit allen teilen** |
   | abonnieren / Live-Verweis | **Folgen** |
   | Fork / Duplikat | **Kopieren** |
   | CSV-Import | **Liste einfügen** |

6. **Freundlich, nicht kindisch.** Die Oberfläche gibt Rückmeldung und lobt
   in Maßen („10 von 12 gewusst"), ohne Comicfiguren und ohne Punkte,
   Abzeichen oder Ranglisten. Erwachsene sollen sich nicht veralbert fühlen.
7. **Leere Zustände führen weiter.** Jede leere Liste sagt, was als Nächstes
   zu tun ist, und bietet genau den passenden Knopf an.
8. **Folgen vor dem Löschen klar benennen.** Rückfragen als Blatt mit
   konkretem Text („12 Karten und dein Fortschritt werden gelöscht") und
   einem roten Knopf, der die Handlung benennt („Stapel löschen", nicht
   „OK").
9. **Kein Datenverlust durch Neuladen.** Eine Lernrunde übersteht Neuladen,
   Bildschirmsperre und Wechsel in eine andere App.
10. **Barrierefreiheit** wie im Template: Fokusring, `prefers-reduced-motion`
    (dann keine Umdreh-Animation), Kontrast AA, Hell/Dunkel über Tokens.
    Eingabefelder bekommen das `lang`-Attribut ihrer Sprache, damit Tastatur
    und Rechtschreibprüfung passen.

## Nicht dabei

- **Gruppen- und Capability-System von vocaBox** (`groups`, `permissions`,
  `cards.manage`, `cards.admin` …). Ersetzt durch die drei Rollen des
  Templates.
- **Karten in mehreren Stapeln.** In vocaBox war eine Karte ein Verweis,
  den mehrere Stapel teilen konnten. Hier gehört jede Karte genau einem
  Stapel (Begründung unter „Privat und geteilt").
- **Tags.** In vocaBox hatte jede Person einen eigenen Tag-Namensraum,
  gefiltert wurde aber über fremde Karten. Für die Zielgruppe ist das
  verwirrend. Ordnen geht hier nur über Stapel. Eine `tags`-Spalte in
  importierten Dateien wird ignoriert.
- **Globale Kartenliste.** Karten gibt es nur im Stapel.
- **Fremde Inhalte bearbeiten**, auch nicht als Moderatorin. Moderation
  heißt hier: Teilen beenden oder löschen.
- **Mehrsprachige Oberfläche** (vocaBox: EN/DE). Nur Deutsch.
- **Desktop-Gestaltung**, Tastaturkürzel, Mehrfachauswahl mit Umschalt.
- **Eingetippte Antworten**, Audioaufnahmen, Bilder auf Karten.
- **Punkte, Abzeichen, Ranglisten, Klassen/Lehrkraft-Ansicht.**
- **Teilen mit einzelnen Personen.** Ein Stapel ist privat oder mit allen
  geteilt.
- **JSON-API** für Karten, Stapel oder Fortschritt.
- **Datenübernahme** aus vocaBox. Neustart; Karten kommen per „Liste
  einfügen".
- **Offline-Lernen.** Der Service Worker cacht weiterhin keine Seiten.
- Die vocaBox-Tabellen `user_active_sets` und `learning_sessions`: In
  vocaBox ohne sichtbare Wirkung.

Konten, Registrierung und Anmeldung regelt
[konten-ohne-email.md](konten-ohne-email.md).

## Begriffe und Regeln

- **Stapel**: Name, Sprache vorne, Sprache hinten (verschieden), privat
  oder mit allen geteilt. Gehört genau einer Person.
- **Karte**: liegt in genau einem Stapel und gehört damit dessen
  Besitzerin. Hat eine **Vorderseite** und eine **Rückseite** mit je einer
  oder mehreren Bedeutungen (hinten z. B. „fast", „quick"). Die Sprache
  steht am Stapel, nicht an der Karte.
- **Sprachen**: feste Liste `de, en, fr, es, it, la, tr, pt, ru, zh, ja`
  und zwei Sonderfälle `formula` („Aufgabe") und `result` („Ergebnis") für
  Rechenkarten. In Auswahllisten stehen Deutsch und Englisch oben. Die
  Sprache steuert nur Beschriftung, `lang`-Attribut und Vorlesen.
  Sprachen eines Stapels lassen sich darum ändern, ohne Karten oder
  Fortschritt anzufassen.
- **Richtung**: *vorwärts* (vorne → hinten) oder *rückwärts* (hinten →
  vorne). In der Oberfläche immer mit Sprachen benannt („Deutsch →
  Englisch").
- **Fortschritt**: je Person, Karte und Richtung.

### Privat und geteilt

Grundsätze, angelehnt an verbreitete Muster (Anki und Quizlet: Kopieren;
Spotify-Playlists: Folgen oder Kopieren; Google Docs: Freigabe gilt immer
für das ganze Dokument):

1. **Sichtbarkeit hängt am Stapel.** Karten haben keine eigene; sie sind
   sichtbar, wenn ihr Stapel es ist.
2. **Eigentum wird nie geteilt.** Jede Karte und jeden Stapel bearbeitet nur
   die Besitzerin.
3. **Fremdes nutzt man auf zwei Wegen:** **folgen** (lesen und lernen,
   bleibt aktuell) oder **kopieren** (eigen, unabhängig, bearbeitbar).
4. **Nichts sickert durch.** Was privat ist, erscheint nirgends sonst,
   auch nicht über früher übernommene Inhalte.

Daraus folgt im Einzelnen:

| Was | Verhalten |
|---|---|
| Eigener Stapel, privat | nur für mich sichtbar |
| Eigener Stapel, geteilt | für alle Angemeldeten sichtbar, folg- und kopierbar; nur ich bearbeite |
| Fremdem geteiltem Stapel **folgen** | erscheint unter „Meine Stapel" mit „von Lena M."; lernen und üben ja, bearbeiten nein; Änderungen der Besitzerin kommen sofort an; mein Fortschritt gehört mir |
| Fremden geteilten Stapel **kopieren** | neuer privater Stapel mit eigenen Kopien aller Karten; „Kopie von …"; **mein Lernstand wird mitkopiert**; kein Bezug mehr zum Original |
| **Einzelne Karte übernehmen** (aus eigenem oder geteiltem Stapel) | wird als Kopie in meinen Stapel gelegt; bei vertauschten Sprachen mit getauschten Seiten |
| Besitzerin beendet das Teilen | Folgende sehen den Stapel nicht mehr (aus „Meine Stapel" ausgeblendet, direkter Aufruf → 404). Folgen und Fortschritt bleiben still gespeichert und sind wieder da, wenn erneut geteilt wird. Kopien bleiben unberührt. |
| Besitzerin löscht Stapel oder Karte | weg, samt Fortschritt aller Folgenden. Vorher sagt die Rückfrage, wie viele folgen („3 Personen folgen diesem Stapel"). Kopien bleiben unberührt. |
| Konto wird gelöscht | eigene Stapel samt Karten werden gelöscht; die Rückfrage der Administratorin nennt geteilte Stapel und Folgende |

### Karteikasten (Leitner)

- Fächer 0 bis N. Fach 0 = **Neu**, Fach N = **Kann ich** (wird nicht mehr
  abgefragt). Jedes Fach k (1 ≤ k < N) hat einen Abstand A_k in Tagen.
  Standard: N = 5, A = 1, 2, 4, 7.
- **Gewusst**: ein Fach weiter. Landet die Karte in Fach k < N, ist sie in
  A_k Tagen wieder fällig (Neu → Fach 1: morgen). Fach N erreicht: „Kann
  ich".
- **Nicht gewusst**: zurück in Fach 1, fällig in A_1 Tagen (morgen). Geübt
  wird sie sofort noch in derselben Runde (siehe Lernrunde).
- Gezählt wird nur die **erste** Bewertung einer Karte in einer Runde.
- „Heute" ist der Kalendertag in der Lern-Zeitzone (`LEARNING_TIMEZONE`,
  Standard `Europe/Berlin`), nicht UTC.
- Wird N verkleinert, zählen Karten in Fach ≥ N als „Kann ich". Gespeichert
  wird dabei nichts um.

**Abweichung von vocaBox:** Dort wurden neue Karten nach „gewusst" und
falsch beantwortete Karten auf *heute* gesetzt, und der erste Abstand wurde
nie benutzt (Fach 1 → 2 nahm schon den zweiten Wert). Hier gilt der
klassische Karteikasten: Jedes Fach hat genau seinen Abstand. Das „heute
noch einmal" übernimmt die Wiederholung innerhalb der Runde.

## Aufbau der Oberfläche

Untere Tab-Leiste (ersetzt die bisherige Auswahl in `layouts/app.blade.php`):

| Tab | Ziel |
|---|---|
| **Heute** | `/learn`, die Startseite nach der Anmeldung |
| **Stapel** | `/decks` |
| **Mehr** | Blatt: Lernplan, Hilfe, Konto, Abmelden; je nach Rolle Benutzer, Tokens, System |

`/dashboard` leitet auf `/learn` weiter, `/` für Angemeldete ebenfalls. Das
Notizen-Beispiel bleibt erreichbar, aber nicht in der Leiste.

Während einer Lernrunde ist die Tab-Leiste ausgeblendet (Vollbild).

## Verhalten

### Heute (`/learn`)

- Oben eine große Karte:
  - Karten fällig: „**12 Karten** warten auf dich" und Knopf **„Los geht's"**.
    Startet eine Runde über alle fälligen Karten aus „Meine Stapel" (eigene
    und gefolgte), in allen schon begonnenen Richtungen (höchstens 20
    Karten, älteste Fälligkeit zuerst). Neue Karten kommen hier nicht
    hinein. Die startet man bewusst im Stapel.
  - Nichts fällig: „Für heute alles geschafft." und, falls bekannt,
    „Morgen sind 8 Karten dran."
- **Lernserie**: „4 Tage in Folge gelernt" mit kleinem Symbol. Ein Tag zählt,
  sobald an ihm mindestens eine Karte bewertet wurde. Ohne Lernen gestern
  oder heute beginnt die Zählung neu. Bei 0 wird nichts angezeigt.
- **Meine Stapel** (Kurzliste): Name, „Deutsch → Englisch", bei gefolgten
  „von Lena M.", Fortschrittsbalken (Anteil „Kann ich") und Plakette „5
  fällig". Stapel mit fälligen Karten zuerst.
- Ganz neu (keine Stapel): Erklärsatz und zwei Knöpfe, „Eigenen Stapel
  anlegen" und „Geteilte Stapel entdecken".

### Stapel (`/decks`)

- Umschalter oben: **Meine** | **Entdecken**.
  - *Meine*: eigene Stapel (privat mit Schloss-Symbol, geteilt mit
    Personen-Symbol) und gefolgte Stapel („von Lena M.").
  - *Entdecken*: alle geteilten Stapel anderer mit Suchfeld (Name,
    Beschreibung) und Sprachfilter. Je Zeile: Name, Sprachen,
    Kartenanzahl, „von Lena M.", Zahl der Folgenden. Bereits gefolgte sind
    markiert.
- Schwebender Knopf **„+ Neuer Stapel"** → Blatt mit Name, „Vorne" und
  „Hinten" (native Auswahllisten). Beschreibung steht hinter „Mehr
  Angaben". Neue Stapel sind privat. Nach dem Speichern öffnet sich der
  Stapel direkt mit dem Blatt „Karte hinzufügen".

### Stapelansicht (`/decks/{deck}`)

Gleicher Aufbau für eigene, gefolgte und nur angesehene Stapel; was fehlt,
ist ausgeblendet, nicht ausgegraut.

- Kopf: Name, „Deutsch → Englisch", Kartenanzahl, bei fremden „von
  Lena M.", bei eigenen geteilten „Geteilt · 3 folgen".
- **Fremder Stapel, noch nicht gefolgt:** über allem ein Hinweisfeld mit
  zwei Knöpfen: **„Folgen"** (Hauptknopf, Erklärung: „Du lernst mit, Lena
  hält ihn aktuell") und **„Kopieren"** (Erklärung: „Du bekommst eine eigene
  Fassung zum Ändern"). Lernen und Üben gehen auch ohne Folgen; beim ersten
  Lernen wird automatisch gefolgt, damit der Stapel unter „Meine" erscheint.
- **Karteikasten-Leiste** (sobald es Fortschritt gibt): waagerechter,
  unterteilter Balken: Neu · Fach 1 … N−1 · Kann ich, mit Anzahl je Fach,
  für die gewählte Richtung. Tippen auf ein Fach zeigt dessen Karten in
  einem Blatt (vorne, hinten, nächste Abfrage als „morgen", „in 3 Tagen").
- **Richtungsumschalter** „Deutsch → Englisch" | „Englisch → Deutsch",
  darunter Hauptknopf **„Lernen (5 fällig · 7 neu)"**. Eine Runde nimmt
  fällige vor neuen Karten. Ohne beides: Knopf deaktiviert mit „Heute
  nichts fällig" und Verweis auf „Freies Üben".
- Zweitknopf **„Freies Üben"** → Blatt mit Richtung (eine von beiden oder
  „gemischt") → Runde.
- **Kartenliste**: je Zeile vorne | hinten (erste Bedeutung, weitere als
  „+1"). Eigener Stapel: Tippen öffnet „Karte bearbeiten". Fremder Stapel:
  Tippen klappt alle Bedeutungen auf. Übernommen wird nicht von hier aus,
  sondern im eigenen Stapel über „Aus anderen Stapeln übernehmen", weil
  dort das Ziel feststeht (Umsetzung Stufe 1).
- Schwebender Knopf **„+ Karte"** nur im eigenen Stapel → Blatt „Karte
  hinzufügen".
- **„⋯"-Menü**, je nach Lage:
  - eigener Stapel: Umbenennen/Sprachen ändern, Mit allen teilen an/aus,
    Liste einfügen, Kopieren, Fortschritt zurücksetzen, Stapel löschen
  - gefolgter Stapel: Nicht mehr folgen, Kopieren, Fortschritt zurücksetzen
  - Moderatorin bei fremdem geteiltem Stapel zusätzlich: Teilen beenden,
    Stapel löschen

„Nicht mehr folgen" fragt: „Dein Lernstand für diesen Stapel bleibt
erhalten, falls du wieder folgst." Wer den Lernstand loswerden will, setzt
ihn vorher zurück.

### Karte hinzufügen (Blatt im eigenen Stapel)

- Zwei große Felder, beschriftet mit der Sprache („Deutsch", „Englisch"),
  mit passendem `lang`. Das erste Feld hat den Fokus, die Tastatur öffnet
  sich sofort. `enterkeyhint="next"` springt ins zweite Feld,
  `enterkeyhint="done"` dort speichert.
- **„Speichern & nächste"** (Hauptknopf) speichert per htmx, leert die
  Felder, setzt den Fokus zurück und zeigt kurz „Gespeichert: Haus – house".
  Das Blatt bleibt offen; so lassen sich viele Karten hintereinander tippen.
- „+ weitere Bedeutung" je Seite für ein zusätzliches Feld.
- Unten zwei Links: **„Liste einfügen"** und **„Aus anderen Stapeln
  übernehmen"**.
- Gibt es im Stapel schon eine Karte mit genau dieser Vorderseite: Hinweis
  „Gibt es schon in diesem Stapel"; Speichern bleibt möglich.

### Karte bearbeiten (Blatt im eigenen Stapel)

- Gleiche Felder wie beim Hinzufügen, vorbelegt; Speichern.
- **„Karte löschen"** (rot, mit Rückfrage). Löscht die Karte samt
  Fortschritt aller, auch der Folgenden. Steht in der Rückfrage, wenn der
  Stapel geteilt ist und Folgende hat.

### Liste einfügen (`/decks/{deck}/import`, nur eigener Stapel)

- Großes Textfeld: „Eine Karte pro Zeile: vorne = hinten". Beispiel
  darüber. Erkannt werden als Trenner `=`, Tabulator (Einfügen aus einer
  Tabelle in Excel, Word oder Numbers) und `;`. Jede Seite bekommt genau
  eine Bedeutung; weitere ergänzt man später beim Bearbeiten.
- Alternativ **„Datei wählen"** (`.txt`, `.csv`), gleiche Regeln. Eine
  erste Zeile, die nur Sprachkürzel enthält (vocaBox-Format `de;en;tags`),
  wird übersprungen. Stehen die Kürzel dort andersherum als im Stapel,
  werden die Seiten getauscht. Weitere Spalten werden ignoriert.
- **„Vorschau"** zeigt die erkannten Paare als Liste. Zeilen ohne Trenner
  oder mit leerer Seite erscheinen rot mit Grund und werden nicht
  übernommen. Doppelte Zeilen werden zusammengefasst.
- **„12 Karten hinzufügen"** legt alle gültigen an und führt zurück in den
  Stapel mit Meldung.
- Grenzen: 1000 Zeilen, Datei bis 1 MB.

### Aus anderen Stapeln übernehmen (Blatt im eigenen Stapel)

- Suchfeld (htmx, mit Verzögerung) über die Bedeutungen beider Seiten.
- Durchsucht werden Karten aus **eigenen** Stapeln (privat und geteilt)
  und aus **geteilten Stapeln anderer**, jeweils nur Stapel mit demselben
  Sprachpaar in gleicher oder vertauschter Reihenfolge. Karten aus fremden
  privaten Stapeln erscheinen nie.
- Je Treffer: vorne | hinten und der Stapelname. „+" legt eine **Kopie**
  in meinen Stapel (bei vertauschten Sprachen mit getauschten Seiten).
  Bereits übernommene Texte sind markiert („schon im Stapel").

### Lernrunde (`/learn/round`)

- **Vollbild**: oben „✕" (zurück, ohne Rückfrage; Bewertetes ist bereits
  gespeichert) und Fortschrittsbalken „3 / 12".
- **Karte** mittig, groß: Vorderseite der Richtung, Schriftgröße passt sich
  der Länge an. Darunter klein die Sprache. Ein Lautsprecher-Knopf liest
  vor (`speechSynthesis` des Browsers, Sprache vom Stapel; ausgeblendet,
  wenn das Gerät keine passende Stimme hat, und bei `formula`/`result`).
- **„Umdrehen"** (oder Tippen auf die Karte) dreht die Karte (Animation,
  entfällt bei reduzierter Bewegung) und zeigt alle Bedeutungen der
  anderen Seite.
- Erst danach erscheinen unten zwei gleich große Knöpfe: links **„Nochmal"**
  (nicht gewusst), rechts **„Gewusst"**. Wischen nach links/rechts geht
  ebenso, aber nur nach dem Umdrehen.
- Jede Bewertung wird sofort gespeichert (htmx), die Antwort ist die
  nächste Karte.
- **Nicht gewusste Karten kommen am Ende der Runde noch einmal**, höchstens
  dreimal je Runde, gekennzeichnet mit „Wiederholung". Diese Wiederholungen
  ändern das Fach nicht mehr (die Karte bleibt in Fach 1, fällig morgen).
  Sie dienen nur dem Einprägen. Die Runde endet so meist mit einem Erfolg.
- **Ende**: „10 von 12 gewusst", Anzahl Karten, die ein Fach aufgestiegen
  sind, die Lernserie und der Knopf „Fertig" (→ Heute).
- Runde mit Stapel: fällige plus neue Karten dieses Stapels und dieser
  Richtung, fällige zuerst, insgesamt höchstens 20.
- Der Rundenstand (Warteschlange, Wiederholungszähler) liegt in der
  Server-Sitzung. Neuladen setzt an derselben Karte fort. Eine neue Runde
  ersetzt die alte.
- Wird während der Runde eine Karte gelöscht oder der Stapel nicht mehr
  geteilt, wird die Karte still übersprungen.

### Freies Üben (`/learn/round?mode=practice`)

- Gleiche Oberfläche, oben die Plakette „Freies Üben – zählt nicht".
- Bis zu 20 zufällige Karten des Stapels, bei „gemischt" je Karte zufällige
  Richtung. „Nochmal" stellt die Karte ans Ende (höchstens dreimal).
- **Auf ein Fach:** Im Blatt hinter einer Fach-Kachel steht oben „Diese
  3 Karten üben“. Geübt werden nur die Karten dieses Fachs in der gerade
  gewählten Richtung (Neu = noch nie beantwortet, „Kann ich“ schließt Fächer
  über N ein), ebenfalls zufällig und höchstens 20. „Gemischt“ geht hier
  nicht, weil ein Fach zu einer Richtung gehört. Ein unbekanntes Fach ergibt
  eine Fehlermeldung.
- Es wird **nichts** gespeichert, auch nicht für die Lernserie.

### Lernplan (`/learn/settings`, unter „Mehr")

- Drei Vorgaben als große Auswahlkarten mit Erklärung in einem Satz:
  - **Normal**: 5 Fächer, 1 · 2 · 4 · 7 Tage (Standard)
  - **Schnell** (vor einer Prüfung): 4 Fächer, 1 · 1 · 2 Tage
  - **Gründlich** (für lange Zeit merken): 6 Fächer, 1 · 2 · 4 · 7 · 14 Tage
- **„Eigener Plan"** klappt auf: Anzahl Fächer (2–10) mit „−"/„+" und je
  Fach ein Zahlfeld für die Tage (1–365). Darunter eine Vorschau als Kette
  „Fach 1 → 1 Tag → Fach 2 → 2 Tage → …".
- Speichern mit Meldung. Gilt für alle Stapel der Person.

### Hilfe (`/help`, unter „Mehr")

- Eine Seite „So funktioniert der Karteikasten": kurzer Text und eine
  Abbildung (Inline-SVG) der Fächer. Dazu kurze Abschnitte zu „Folgen oder
  kopieren?", zum Einfügen von Listen und zum Ablegen der App auf dem
  Startbildschirm (iPhone und Android, je zwei Sätze).

### Fehlerfälle

- Ungültige oder nicht sichtbare IDs → 404, nie 403, nie 500.
- Fremden Stapel bearbeiten, Karten hinzufügen, importieren → 404 (nicht
  403, damit nicht verraten wird, ob es den Stapel privat gibt).
- Validierungsfehler in Blättern erscheinen am Feld, das Blatt bleibt offen.
- Sitzung abgelaufen während einer Runde → Anmeldung, danach zurück zu
  Heute. Bereits Bewertetes ist gespeichert.

## Berechtigungen

| vocaBox | hier |
|---|---|
| `cards.manage` (Karten, Sets, Lernen, Einstellungen) | Gruppe `auth` |
| `cards.admin` (fremde Karten und geteilte Sets pflegen) | Gruppe `role:moderator`, nur „Teilen beenden" und „Löschen" unter `/moderation/decks/{deck}/…` |
| `admin.manage` | `role:admin` (besteht) |

Die normalen Routen rufen ausschließlich die Fassungen der Services auf,
die auf eigene bzw. sichtbare Stapel beschränken. Nur die
Moderationsrouten nutzen die systemweite Fassung, und die greift
ausschließlich auf **geteilte** Stapel. Fremde private Stapel bleiben auch
für Moderatorinnen und Administratorinnen unsichtbar (404).

Sichtbarkeit und Schreibrecht (geprüft im Service):

| | sehen, lernen, folgen, kopieren, Karten übernehmen | bearbeiten, Karten anlegen/löschen, teilen, löschen |
|---|---|---|
| eigener Stapel | ja | ja |
| fremder geteilter | ja | nein (Moderatorin: nur Teilen beenden, löschen) |
| fremder privater | nein (404) | nein (404) |

Fortschritt, Lernplan, Lernserie und Folgen: ausschließlich eigene.

## Datenmodell

UUID-Schlüssel, `timestampsTz()`, `foreignUuid(...)->constrained()->cascadeOnDelete()`
überall. Bezeichner englisch: Stapel = `deck`.

**Migration `2026_10_11_100000_create_decks_and_cards_tables.php`**
- `decks`: `id`, `user_id` (cascade), `name` (100), `description`
  (nullable, 500), `front_language`, `back_language` (string 10),
  `is_shared` (bool, false), `copied_from_name` (nullable, 100; nur für
  „Kopie von …", kein Fremdschlüssel), Zeitstempel; Check-Constraint
  Sprachen verschieden; Index (`is_shared`), Index (`user_id`).
- `cards`: `id`, `deck_id` (cascade), `front` (jsonb, Liste von
  Bedeutungen), `back` (jsonb, Liste von Bedeutungen), Zeitstempel;
  Index (`deck_id`). Bedeutungen je höchstens 500 Zeichen, je Seite
  höchstens 10.
- `deck_follows`: `user_id` (cascade), `deck_id` (cascade), `created_at`;
  Primärschlüssel aus beiden. Eigene Stapel kann man nicht folgen.

**Migration `2026_10_11_100001_create_learning_tables.php`**
- `card_progress`: `user_id` (cascade), `card_id` (cascade), `direction`
  (`forward`/`backward`), `box` (smallint, 0), `due_on` (date),
  `last_reviewed_at` (nullable), `review_count`, `correct_count`,
  Zeitstempel; Primärschlüssel (`user_id`, `card_id`, `direction`); Index
  (`user_id`, `due_on`).
- `learning_settings`: `user_id` (Primärschlüssel, cascade), `box_count`
  (smallint, 5), `day_gaps` (jsonb, `[1,2,4,7]`), Zeitstempel. Fehlt die
  Zeile, gilt „Normal".
- `study_days`: `user_id` (cascade), `day` (date), `reviews` (int),
  `correct` (int); Primärschlüssel (`user_id`, `day`). Grundlage der
  Lernserie.

Keine Abweichung von den Datenbankregeln in CLAUDE.md mehr: Weil es keine
Verweise auf fremde Karten gibt, darf alles mit seiner Besitzerin
verschwinden.

Suche über `front`/`back` per `ILIKE` auf den Text der jsonb-Liste
(Platzhalter, `%`/`_` maskiert). Ein Index ist bei der zu erwartenden
Menge nicht nötig; bei Bedarf später `pg_trgm`.

Neue Konfiguration `config/learning.php`: `LEARNING_TIMEZONE` (Standard
`Europe/Berlin`), Lernplan-Vorgaben, Rundengröße (20), Wiederholungen je
Runde (3), Importgrenzen.

## Betroffene Stellen

- `app/Enums/Language.php` (deutsche Beschriftung, BCP-47-Code für
  `lang`/Vorlesen), `app/Enums/Direction.php`, `app/Enums/StudyPlan.php`.
- `app/Models/`: `Deck`, `Card`, `CardProgress`, `LearningSettings`,
  `StudyDay`; Beziehungen an `User` (`decks`, `followedDecks`).
- `app/Services/`:
  - `DeckService`: sichtbare Stapel („Meine", „Entdecken"),
    `getVisible`/`getOwned`, anlegen, ändern, teilen, löschen, folgen,
    entfolgen, kopieren (inkl. Lernstand), systemweit nur für geteilte:
    `unshareShared`, `deleteShared`.
  - `CardService`: anlegen/ändern/löschen im eigenen Stapel, suchen in
    sichtbaren Stapeln, übernehmen als Kopie (mit Seitentausch).
  - `CardImportService`: Text/Datei zerlegen, Vorschau, anlegen.
  - `LeitnerService`: Bewertung, Fälligkeit, „heute" in Lern-Zeitzone.
  - `StudyRoundService`: Runde zusammenstellen, Warteschlange und
    Wiederholungen. Bekommt den Rundenstand als Wert übergeben und gibt ihn
    zurück; die Sitzung liest und schreibt der Controller.
  - `LearningOverviewService`: Heute-Zahlen, Fächer je Stapel, Lernserie.
  - `LearningSettingsService`.
- `app/Http/Controllers/`: `LearnController`, `StudyRoundController`,
  `DeckController`, `DeckFollowController`, `DeckCardController`,
  `DeckImportController`, `LearningSettingsController`, `HelpController`,
  `Moderation/DeckController`.
- `app/Http/Controllers/Admin/UserController.php` + `admin/user.blade.php`:
  Rückfrage beim Löschen nennt geteilte Stapel und Folgende.
- `routes/web.php`: alles in `auth` bzw. `role:moderator`, IDs mit
  `->whereUuid(…)`.
- `resources/views/`: `learn/` (heute, runde, ende, lernplan), `decks/`
  (liste, ansicht, import), `help.blade.php`; `partials/` für
  htmx-Fragmente (Lernkarte, Kartenzeile, Suchtreffer, Fachinhalt,
  Importvorschau, Folgen-Knopf).
- `resources/views/components/`: `sheet` (Blatt von unten,
  wiederverwendbar), `fab` (schwebender Knopf), `segmented` (Umschalter),
  `box-bar` (Karteikasten-Leiste), `deck-row`, `empty-state`.
- `resources/css/components.css`: Klassen für Lernkarte (inkl. Umdrehen),
  Blatt, schwebenden Knopf, Umschalter. Nur Tokens.
- `layouts/app.blade.php`: Tab-Leiste Heute | Stapel | Mehr; Vollbild-Variante
  ohne Leiste für Runden. `nav-links.blade.php`: Einträge für „Mehr".
- Wenige Zeilen Vanilla-JS inline: Blatt öffnen/schließen, Umdrehen,
  Wischen, Vorlesen, Fokus nach htmx-Speichern.
- `PageController`/`AuthController`: nach Anmeldung und für `/` auf `/learn`.
- `lang/de/validation.php`: Attributnamen.
- `config/learning.php`, `.env.example`, `docs/DEPLOY.md`, `CHANGELOG.md`.

### Reihenfolge der Umsetzung

0. **Konten ohne E-Mail** ([konten-ohne-email.md](konten-ohne-email.md)).
1. **Stapel und Karten** ✅: Migration 1, Stapel anlegen/ändern/teilen/löschen,
   Karten hinzufügen/bearbeiten/löschen, Liste einfügen, Folgen,
   Kopieren, Übernehmen, Moderation, neue Tab-Leiste mit Blatt-Komponente.

   Umgesetzt mit diesen Abweichungen bzw. Zwischenständen:
   - „Heute" liegt bis Stufe 2 unter `/dashboard` und zeigt „Meine Stapel".
     Mit Stufe 2 zieht es nach `/learn`, `/dashboard` leitet dann weiter.
   - Lernen-Knöpfe, Karteikasten-Leiste und „Fortschritt zurücksetzen"
     fehlen bis Stufe 2. Kopieren übernimmt den Lernstand erst ab Stufe 2
     (es gibt ihn vorher nicht).
   - `/moderation` listet alle geteilten Stapel anderer. Die Aktionen
     „Teilen beenden" und „Löschen" stehen im „⋯"-Menü des Stapels.
   - Blätter sind native `<dialog>`-Elemente (`components/sheet`). Es ist
     immer nur ein Blatt offen.
   - In htmx-Blättern (Karte anlegen/bearbeiten) erscheint ein Fehler als
     Meldung (htmx-422, `bootstrap/app.php`), nicht am Feld. Das Blatt
     bleibt offen, die Eingaben bleiben stehen. Normale Formulare (Stapel
     anlegen/bearbeiten) öffnen ihr Blatt wieder und zeigen den Fehler am
     Feld.
   - Rückfragen vor dem Löschen: seit Stufe 3 ein eigenes Blatt mit rotem
     Knopf (siehe dort).
   - „Liste einfügen“ ist auf 20 Anfragen pro Minute gedrosselt.
   - „Zurück zur Liste" in der Import-Vorschau behält den Text (seit Stufe 3).
2. **Lernen** ✅: Migration 2, Karteikasten, Lernrunde, Freies Üben, Heute,
   Fächer-Leiste, Lernserie, Lernstand beim Kopieren.

   Umgesetzt mit diesen Abweichungen bzw. Zwischenständen:
   - `card_progress` hat eine eigene UUID als Schlüssel und einen
     eindeutigen Index über (`user_id`, `card_id`, `direction`) statt eines
     zusammengesetzten Primärschlüssels, damit es ein normales
     Eloquent-Modell ist. Migration `2026_10_11_110000_create_learning_tables.php`.
     `due_on` ist bei „Kann ich“ leer.
   - `learning_settings`: Ohne Zeile gilt „Normal“ aus `config/learning.php`.
     Bearbeitbar seit Stufe 3 (Lernplan).
   - „Ein Fach weiter“ auf dem Endbildschirm zählt nur gewusste Karten. Eine
     neue, nicht gewusste Karte landet zwar in Fach 1, steigt aber nicht auf.
   - Während Wiederholungen bleibt der Zähler auf der letzten Zahl
     („3 / 3“), daneben steht „Wiederholung“.
   - Zusätzlich zur Bedienung per Finger: Leertaste/Enter dreht um,
     Pfeil links/rechts bewertet (für Tastaturen und Tablets).
   - Der Anteil „Kann ich“ in „Meine Stapel“ bezieht sich auf die schon
     begonnenen Richtungen.
   - Jede Antwort trägt die Schrittnummer der Runde mit. Eine Antwort mit
     veralteter Nummer (Doppeltipp, altes Tab, gehaltene Taste) wird
     ignoriert. Antworten einer Sitzung laufen nacheinander (`->block()`
     an der Route), damit zwei gleichzeitige Anfragen eine Karte nicht
     doppelt befördern.
   - Das Fach-Blatt rechnet „morgen / in 3 Tagen“ über reine
     Kalenderdaten. Unbekannte Fachnummern ergeben 404.
3. **Feinschliff** ✅: Lernplan, Hilfe, Vorlesen, Wischen, Leerzustände,
   Prüfung auf echten Geräten (iPhone SE, kleines Android).

   Umgesetzt mit diesen Abweichungen bzw. Ergänzungen:
   - **Lernplan** (`/learn/settings`, `LearningSettingsService`): Ohne
     JavaScript sind alle Tagesfelder sichtbar, „−“/„+“ und die Vorschau
     braucht es nicht. Gespeichert werden nur die ersten N−1 Abstände.
   - **Hilfe** (`/help`) zeichnet die Fächer nach dem eigenen Lernplan, die
     Tage stehen im Fach.
   - **Vorlesen**: ein Knopf unter der Karte statt in der Karte (kein
     Bedienelement in einem Bedienelement). Er liest vor dem Umdrehen die
     Frage, danach alle Bedeutungen der Antwort.
   - **Wischen**: Pointer-Ereignisse, also Finger und Maus. Erst nach dem
     Umdrehen; die Karte folgt dem Finger, ab einem Viertel der Breite
     (höchstens 120 px) wird bewertet, sonst springt sie zurück.
   - **Rückfragen** als Blatt (`#confirm-sheet` in `layouts/app`): Text
     aus `data-confirm` bzw. `hx-confirm`, roter Knopf mit dem Namen aus
     `data-confirm-action`, darunter „Abbrechen“. Gilt auch in der
     Verwaltung.
   - **Prüfung auf echten Geräten** ersetzt vorerst eine Prüfung in 320 px
     Breite (kleiner als iPhone SE) im Browser: kein waagerechtes Scrollen
     auf Heute, Stapel, Stapelansicht, Runde, Lernplan und Hilfe. Ein Test
     auf echten Telefonen steht noch aus.

Jede Stufe endet mit grünem `just check` und eigenem Changelog-Eintrag.

## Tests

**Sichtbarkeit**
- fremder privater Stapel: ansehen, lernen, folgen, kopieren, Karte
  übernehmen, importieren → 404, auch für Moderatorin und Administratorin
- fremder geteilter Stapel: sichtbar in „Entdecken", lernbar, folgbar,
  kopierbar; bearbeiten, Karte anlegen/ändern/löschen, importieren → 404
- Suche „Aus anderen Stapeln übernehmen" findet eigene private, eigene
  geteilte und fremde geteilte Karten, **nie** Karten aus fremden privaten
  Stapeln; `%` und `_` werden als Zeichen gesucht
- Übernehmen per direkter ID aus fremdem privatem Stapel → 404

**Folgen und Kopieren**
- eigenem Stapel folgen wird abgewiesen
- gefolgter Stapel erscheint unter „Meine"; Änderung der Besitzerin ist
  sofort sichtbar
- Besitzerin beendet Teilen: Stapel verschwindet bei Folgenden, direkter
  Aufruf → 404; erneut geteilt → wieder da, samt Fortschritt
- Kopie: privat, gehört der Kopierenden, eigene Karten-IDs, gleicher Inhalt,
  Lernstand der Kopierenden übertragen, fremder Lernstand nicht; spätere
  Änderung am Original ändert die Kopie nicht
- Übernommene Karte bei vertauschtem Sprachpaar hat getauschte Seiten
- erstes Lernen eines fremden Stapels folgt automatisch
- Entfolgen behält Fortschritt; erneutes Folgen zeigt ihn wieder

**Karten und Import**
- anlegen mit mehreren Bedeutungen; doppelte Bedeutung wird still
  zusammengefasst; leere Seite und mehr als 10 Bedeutungen abgewiesen
- Löschen entfernt Fortschritt aller, auch Folgender
- Import: erkennt `=`, Tabulator, `;`; überspringt Kopfzeile im
  vocaBox-Format und tauscht bei umgekehrter Kopfzeile; meldet fehlerhafte
  Zeilen, legt die übrigen an; fasst doppelte zusammen; lehnt mehr als
  1000 Zeilen bzw. > 1 MB ab; Vorschau legt nichts an
- Sprachen eines Stapels ändern lässt Karten und Fortschritt unberührt

**Moderation**
- Moderatorin beendet Teilen bzw. löscht fremden geteilten Stapel; bei
  fremdem privatem → 404; als einfache Benutzerin → 403
- Konto löschen entfernt dessen Stapel, Karten, Folgen und Fortschritt;
  die Rückfrage nennt geteilte Stapel und Folgende

**Karteikasten und Runde**
- Übergänge: Neu→1 fällig in A_1; k→k+1 fällig in A_(k+1); N−1→„Kann ich";
  nicht gewusst → Fach 1, fällig in A_1
- Wiederholung in derselben Runde ändert Fach und Fälligkeit nicht
- verkleinerte Fächerzahl: höhere Fächer zählen als „Kann ich"
- Richtungen getrennt
- Fälligkeit nach Lern-Zeitzone (eingefrorene Zeit 23:30 UTC)
- Runde im Stapel: höchstens 20, fällige vor neuen, keine „Kann ich";
  „Los geht's" nur fällige aus eigenen und gefolgten Stapeln, nicht aus
  nicht mehr geteilten
- nicht gewusste Karte erscheint am Ende erneut, höchstens dreimal
- Neuladen setzt an derselben Karte fort; gelöschte Karte wird übersprungen
- Bewertung einer Karte außerhalb der aktuellen Runde wird abgewiesen
- Freies Üben speichert weder Fortschritt noch Lerntag
- Lernserie: zählt aufeinanderfolgende Tage, bricht nach einem Tag Pause ab
- „Fortschritt zurücksetzen" löscht nur eigenen Fortschritt dieses Stapels

**Lernplan**
- Vorgaben speichern richtige Werte; eigener Plan: 2–10 Fächer, genau N−1
  Abstände, je 1–365

**Routen und Oberfläche**
- alle neuen Routen geschützt, keine neuen `PUBLIC_ROUTES`
- `/dashboard` und `/` (angemeldet) leiten auf `/learn`
- htmx-Antworten sind Fragmente (Lernkarte, Kartenzeile, Suchtreffer)
- keine N+1-Abfragen in „Meine", „Entdecken" und Stapelansicht
- Heute, Stapel und Runde bei 360 px ohne waagerechtes Scrollen (manuell in
  Stufe 3, Ergebnis hier nachgetragen)

## Annahmen und Risiken

- **Sprache:** Oberfläche, Spezifikation, Changelog und Testnamen deutsch,
  Kommentare und PHPDoc englisch (CLAUDE.md, „Sprache im Code").
- **Folgende verlieren Lernstand**, wenn die Besitzerin einen Stapel oder
  eine Karte löscht. Das ist die Kehrseite von „bleibt aktuell". Wer
  unabhängig sein will, kopiert. Die Rückfrage der Besitzerin nennt die
  Zahl der Folgenden.
- **Kopie nimmt Lernstand mit**, damit ein Kind, das erst folgt und später
  kopiert, nicht von vorn beginnt. Danach laufen beide Fortschritte
  getrennt.
- **Fortschritt hängt an der Karte**, nicht am Stapel. Da jede Karte genau
  einem Stapel gehört, ist das gleichbedeutend, und „Fortschritt
  zurücksetzen" wirkt nur auf diesen Stapel.
- **Abweichungen von vocaBox**, um es einfacher und sicherer zu machen:
  eine Karte je Stapel statt geteilter Verweise; Folgen und Kopieren; keine
  Tags; Moderation ohne Fremdbearbeitung; klassische Fach-Abstände;
  Wiederholung nicht gewusster Karten in derselben Runde;
  Lernplan-Vorgaben; Lernserie; Vorlesen; Import mit Vorschau.
- **Anzeigename statt Benutzername** bei geteilten Stapeln („von Lena M."),
  damit Anmeldenamen nicht für alle sichtbar sind.
- **Vorlesen** hängt vom Gerät ab (Stimmen für Latein fehlen meist). Der
  Knopf erscheint nur, wenn eine passende Stimme da ist.
- **Desktop** funktioniert, sieht aber aus wie ein großes Telefon. Gewollt.
  Die Kopfzeilen-Navigation ab `lg` bleibt technisch bestehen, wird aber
  nicht gestaltet oder getestet.
- **Notizen-Beispiel** bleibt im Code, verschwindet aus der Tab-Leiste.
  Entfernen wäre eine eigene Änderung.
