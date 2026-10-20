# Motivierender Start

Status: **umgesetzt** (17.10.2026)

Anlass: Die App soll Kindern beim Lernen helfen und sie motivieren. Aus der
Vorschlagsliste vom 16.10.2026 gehören diese drei in dieses Paket:

1. **Neue Wörter erst zeigen, dann abfragen.**
2. **Tagesziel** auf „Heute“.
3. **Wachsender Wortschatz:** „Du kannst schon N Wörter“ und am Rundenende
   die Wörter, die neu in „Kann ich“ sind.

Entscheidungen (Rückfragen vom 16.10.2026):

- Das Tagesziel ist **wählbar: 10, 20 oder 30 Karten**, Vorgabe 20.
- Ein Tag zählt für die **Lernserie**, wenn das Tagesziel erreicht ist **oder**
  „Heute“ nichts mehr anbietet. Bisherige Tage zählen weiter.
- Für das Tagesziel zählen **gewusste Karten** aus jeder Runde, auch „Für den
  Test üben“. Der Karteikasten bleibt davon unberührt. (Zuerst hieß es „jede
  Antwort“; geändert am 17.10.2026, weil sonst 20-mal „Nochmal“ das Ziel
  füllte. Es sollen Erfolge nötig sein.)

Leitlinie 6 bleibt: keine Punkte, keine Abzeichen, keine Ranglisten.
(Seit 20.10.2026 mit einer bewussten Ausnahme: das Duell unter Freunden,
siehe [duell.md](duell.md) – ein Gewinner nur für das eine Duell.)

## Ziel

Ein Kind wird nicht mehr nach Wörtern gefragt, die es nie gesehen hat. Es
sieht jeden Tag, wann es fertig ist, und es sieht, dass sein Wortschatz
wächst.

## Nicht dabei

- Meilensteine, Abzeichen, Punkte, Ranglisten, Vergleiche mit anderen.
- Erinnerungen (Push), Schreibmodus, Testdatum. Das sind eigene spätere
  Pakete.
- Ein Tagesziel in Minuten. Gezählt werden Antworten, das ist eindeutig und
  ohne Uhr im Hintergrund.
- Statistik-Seiten oder Verläufe über Wochen.
- Änderungen an der Leitner-Rechnung: Die Einführung einer Karte ist keine
  Antwort und verschiebt kein Fach.

---

## Verhalten

### A. Neue Wörter erst zeigen

**Wo:** in Lernrunden (`learn`), also „Los geht's“ und „Nur diesen Stapel
lernen“. Nicht in „Für den Test üben“ und nicht beim Üben eines Fachs, denn
dort geht es um Bekanntes.

**Ablauf je neuer Karte:**

1. Statt der Frage kommt zuerst eine **offene Karte**. Oben steht das
   Kennzeichen „Neues Wort“, darunter Frage und Antwort gleichzeitig, mit
   weiteren Bedeutungen („auch: …“), Hinweis und „Vorlesen“. Unten steht ein
   Knopf **„Weiter“**, darüber klein: „Merk es dir – gleich fragt die App
   danach.“
2. Die eigentliche Abfrage dieser Karte kommt **drei Karten später** in
   derselben Runde (`learning.intro_gap = 3`), also mit genau drei Einträgen
   dazwischen. Sind es weniger, kommt sie am Ende. Bestehen die Plätze nur
   aus neuen Karten, ergibt das Blöcke: vier Einführungen, dann die vier
   Abfragen, dann die nächsten.
3. Ab da läuft alles wie bisher: „Gewusst“ → Fach 1, morgen wieder;
   „Nochmal“ → Fach 1, und die Karte kommt am Ende der Runde noch einmal.

**Gilt für:**

- Neu ist eine Karte, wenn es in dieser Richtung noch keinen Lernstand gibt,
  wie beim Zusammenstellen der Runde.
- Bei „Auch rückwärts lernen“ bekommt die Rückwärts-Richtung **keine** eigene
  Einführung. Das Wort kennt das Kind dann schon von vorne → hinten. Ist es
  in derselben Runde neu eingeführt, kommt die Rückwärts-Abfrage direkt nach
  der Vorwärts-Abfrage.
- „Weiter“ ist **keine Antwort**: Es entsteht kein Lernstand, kein Fach
  ändert sich, und das Tagesziel zählt die Einführung nicht.
- Die Fortschrittsanzeige („3 / 12“) zählt nur Abfragen, keine
  Einführungen.
- Wer die Runde nach der Einführung, aber vor der Abfrage verlässt, hat
  nichts verloren: Die Karte bleibt neu und wird beim nächsten Mal wieder
  eingeführt.

### B. Tagesziel auf „Heute“

**Anzeige:** Oben in der Karte auf „Heute“ steht ein **Ring** mit der Zahl
darin, z. B. „15 / 20“, und darunter „Karten heute“.

- Ist das Ziel noch nicht erreicht, ist der Ring teilweise gefüllt
  (Markenfarbe). Darunter steht wie bisher „12 Karten warten auf dich“ und
  „Los geht's“.
- Ist das Ziel erreicht, ist der Ring voll (Erfolgsfarbe) und es steht
  „**Tagesziel geschafft!**“ da. Warten noch Karten, steht ein schlichter
  Knopf „**Weiterlernen**“ da. Das Kind darf weiterlernen, muss aber nicht.
- Der Ring ist nur voll, wenn der Tag für die Serie zählt: Ziel erreicht,
  oder nach einer Runde gab es nichts mehr zu tun. Dann steht „**Für heute
  alles geschafft.**“ da.
- Ist heute gar nichts dran und noch nichts geübt: „**Heute ist nichts
  dran.**“ mit dem Hinweis, ein paar Karten in einem Stapel zu üben, damit der
  Tag für die Serie zählt. Der Ring bleibt leer.
- Die Lernserie („🔥 3 Tage in Folge“) steht wie bisher unter der Begrüßung.

**Was zählt:** jede **gewusste** Karte in jeder Runde (Lernen, „Für den Test
üben“, Fach üben), je Runde höchstens einmal je Karte und Richtung. Auch eine
Karte, die erst bei der Wiederholung gewusst wird, zählt dann. „Nochmal“
zählt nicht, Einführungen (A) auch nicht. Der Ring heißt „heute gewusst“. Gezählt wird je Kalendertag in der Lern-Zeitzone, wie die
Fälligkeit.

**Ziel wählen:** Unter **Mein Konto → Lernplan** steht oben neu
„**Tagesziel**“ mit drei Möglichkeiten: 10 · 20 · 30 Karten, darunter: „So
viele Karten möchtest du jeden Tag schaffen.“ Gespeichert wird mit dem Plan
zusammen. Auf der Kontoseite steht es in der Lernplan-Karte („Normal – … ·
Tagesziel 20 Karten“). Ändert man das Ziel, zählen die schon geschafften Tage
weiter. Für den heutigen Tag gilt das neue Ziel ab sofort.

**Lernserie:** Ein Tag zählt, wenn an ihm

- das Tagesziel erreicht wurde, oder
- nach einer Runde mit Antworten (auch Test üben oder Fach üben) „Heute“
  nichts mehr anbot (keine fälligen Karten mehr und keine neuen innerhalb des
  Tageskontingents).

Ein Tag ist erledigt, sobald eine der beiden Bedingungen eintritt. Das
bleibt so, auch wenn danach neue Karten dazukommen. Bisherige Tage zählen
weiter (Migration).

Am Rundenende steht wie bisher die Lernserie. Ist das Ziel mit dieser Runde
erreicht, steht zusätzlich „Tagesziel geschafft!“ da (nicht bei späteren
Runden desselben Tages). Wird das Ziel gesenkt und ist es damit schon
erreicht, zählt der Tag sofort.

Die Kennzeichen beim Üben heißen „Freies Üben – nur Übung“ und „Test üben –
nur Übung“ (vorher „zählt nicht“), denn für das Tagesziel zählen sie.

### C. Wachsender Wortschatz

**Auf „Heute“** unter der Karte, vor „Meine Stapel“, steht eine ruhige
Zeile:

- „Du kannst schon **124 Wörter**“, darunter klein „+5 diese Woche“ (nur wenn
  größer 0).
- Solange noch kein Wort in „Kann ich“ liegt, aber schon gelernt wird:
  „**45 Wörter** im Karteikasten. Nach ein paar Wiederholungen kannst du
  sie.“ Bis „Kann ich“ vergehen mit dem Plan „Normal“ rund zwei Wochen, so
  ist die Zeile vom ersten Tag an sinnvoll.
- Ohne Lernstand steht dort nichts.

**Was als Wort zählt:**

- Eine Karte zählt, wenn sie in **mindestens einer Richtung** in einem
  ihrer Durchgänge „Kann ich“ erreicht hat.
- Gezählt wird nur in Stapeln, die man besitzt oder lernt (`mineQuery`), und
  nur aktive Karten.
- „Diese Woche“: Karten, die in den letzten 7 Tagen „Kann ich“ erreicht
  haben. Als Zeitpunkt gilt die letzte Antwort, mit der sie dort ankamen.
- „Im Karteikasten“: Karten mit irgendeinem Lernstand (Fach 1 bis „Kann
  ich“).

**Am Rundenende** (Lernrunden) steht unter „8 von 10 gewusst“ zusätzlich:

- „**Neu in Kann ich:** dog, cat, house“, also die Hauptbedeutung der
  Antwortseite. Es sind höchstens 8 Wörter, dazu „und 3 weitere“. Das
  erscheint nur, wenn mit dieser Runde Karten dort angekommen sind.
- Das bisherige „3 Karten sind ein Fach weiter“ bleibt.

### Fehlerfälle

- Ein ungültiges Tagesziel (z. B. 15, Text, Liste) ergibt die
  Validierungsmeldung „Bitte 10, 20 oder 30 wählen.“ Nichts wird gespeichert.
- „Weiter“ mit falschem Schritt (Doppeltipp, alter Tab) ändert nichts. Die
  aktuelle Karte erscheint erneut, wie bei Antworten.
- Wird eine Karte gelöscht, deren Abfrage nach der Einführung noch aussteht,
  wird sie übersprungen, wie bisher bei verschwundenen Karten.

---

## Berechtigungen

Keine neuen Routen. Alles bleibt in der Gruppe `auth` und betrifft nur eigene
Daten:

- `POST /learn/round/answer`: „Weiter“ läuft über dieselbe Route mit Karte
  und Schritt wie eine Antwort.
- `POST /learn/settings` nimmt zusätzlich `daily_goal` an.

## Datenmodell

Eine Migration: `2026_10_16_000000_add_daily_goal_and_goal_met.php`.

- **`learning_settings.daily_goal`**: `smallInteger`, Vorgabe 20. Personen
  ohne Zeile nutzen `learning.default_daily_goal` (20).
- **`study_days.answers`**: `integer`, Vorgabe 0. Die für das Tagesziel gezählten (gewussten) Karten des Tages; der Name stammt aus der ersten Fassung.
  Die bisherige Spalte `reviews` bleibt und zählt weiter nur die Antworten,
  die ein Fach bewegen.
- **`study_days.goal_met`**: `boolean`, Vorgabe `false`.

**Bestehende Daten:** Die Migration setzt für alle bisherigen Zeilen
`answers = reviews` und `goal_met = true`. So bleiben bestehende Lernserien
erhalten.

**Wann `goal_met` gesetzt wird:**

- nach jeder Antwort, wenn `answers` das Tagesziel erreicht;
- beim Ende einer Lernrunde, wenn „Heute“ nichts mehr anbietet.

Für Tage, an denen nur „Für den Test üben“ gelaufen ist, entsteht ebenfalls
eine `study_days`-Zeile (`reviews = 0`, `answers` > 0). Die Lernserie zählt
nur noch Tage mit `goal_met`.

Die Rundendaten in der Session bekommen zwei Felder: Einführungs-Einträge
(`intro: true`) in der Warteschlange und `mastered` (Karten-IDs, die in
dieser Runde in „Kann ich“ kamen). Alte Runden ohne diese Felder laufen
weiter.

## Betroffene Stellen

- **app/Services/**
  - `StudyRoundService`:
    - Einführungen beim Zusammenstellen von Lernrunden, `answer()` für
      Einführungen ohne Bewertung.
    - Jede in einer Runde erstmals gewusste Karte (je Richtung) zählt für das
      Tagesziel (`counted` in der Runde).
    - `mastered` wird mitgeschrieben, am Rundenende wird „nichts mehr da?“
      geprüft.
  - `LeitnerService`:
    - `recordStudyDay` zählt weiter Fach-Antworten (`reviews`), neu
      `countAnswer` alle Antworten (`answers`) und setzt `goal_met`.
    - neu `markGoalMet`, `refreshGoalMet` (nach Änderung des Ziels),
      `dayStatus`; `streak` zählt nur `goal_met`.
    - `review` meldet, ob die Karte in „Kann ich“ ankam (gibt es über `to`
      schon).
  - `LearningSettingsService`: `daily_goal` lesen, prüfen und speichern.
    Fehlt das Feld, bleibt das bisherige Ziel.
  - `LearningOverviewService`:
    - `today` bekommt `answers`, `goal`, `goal_met`.
    - neu `vocabulary(User)` mit `mastered`, `mastered_week`, `in_box`.
- **app/Http/Controllers/**: `StudyRoundController` (Weiter, Rundenende),
  `LearningSettingsController` (Feld durchreichen), `AccountController`
  (Tagesziel in der Lernplan-Karte), `LearnController` (Wortschatz).
- **resources/views/**
  - `learn/today.blade.php`: Ring, Zustände, Wortschatz-Zeile.
  - `learn/partials/round-body.blade.php`: Einführungskarte, „Weiter“,
    „Neu in Kann ich“, „Tagesziel geschafft!“.
  - `learn/settings.blade.php`: Auswahl Tagesziel.
  - `pages/account.blade.php`: Tagesziel in der Lernplan-Karte.
  - `pages/help.blade.php`: Absatz zu neuen Wörtern, Tagesziel und Serie.
- **resources/css/components.css**: Klasse `.goal-ring` (conic-gradient mit
  Tokens `brand`/`success`/`surface-sunken`, ohne JavaScript;
  `role="progressbar"` mit `aria-valuenow`).
- **config/learning.php**: `default_daily_goal` (20), `daily_goals`
  ([10, 20, 30]), `intro_gap` (3).
- **lang/de/validation.php**: Attribut `daily_goal` → „Tagesziel“.
- **site/index.html**: gegenlesen (Tags „Lernserie“ u. a.).
- **CHANGELOG.md**: Eintrag mit `Migration:`-Zeile.

## Tests

**Einführung (A):**
- Eine Runde mit neuen Karten: zuerst die Einführung (Frage und Antwort
  sichtbar, „Weiter“), die Abfrage kommt 3 Einträge später, bei kurzen
  Runden am Ende.
- „Weiter“ legt keinen Lernstand an und zählt nicht fürs Tagesziel. Die
  spätere Abfrage legt Fach 1 an.
- Fällige Karten, Test üben und Fach üben haben keine Einführung.
- Rückwärts eingeschaltet: Die neue Rückwärts-Karte hat keine eigene
  Einführung.
- „Weiter“ mit falschem Schritt ändert nichts.
- „3 / 12“ zählt keine Einführungen.

**Tagesziel und Serie (B):**
- 20 Antworten (inklusive Wiederholungen und Test üben) → Ring voll,
  „Tagesziel geschafft!“, `goal_met`.
- Ziel 10 gewählt → nach 10 Antworten geschafft.
- Ungültiges Ziel → Fehlermeldung, nichts gespeichert.
- Nur 4 Karten fällig, alle beantwortet → „Für heute alles geschafft.“,
  Tag zählt für die Serie, obwohl 4 < 20.
- Ein Tag mit 5 Antworten bei weiterhin wartenden Karten zählt nicht für
  die Serie.
- Migration: Bestehende `study_days` werden `goal_met = true`, `answers =
  reviews`; eine bestehende Serie bleibt gleich lang (Migrationstest).
- Tagesgrenze in Berliner Zeit (wie bei `new_per_day`).

**Wortschatz (C):**
- „Du kannst schon N Wörter“ zählt Karten mit „Kann ich“ in mindestens
  einer Richtung einmal, auch bei zwei Durchgängen. Pausierte Karten und
  Karten aus nicht mehr gelernten Stapeln zählen nicht.
- „+N diese Woche“ zählt nur die letzten 7 Tage.
- Ohne „Kann ich“, mit Lernstand: die Zeile „im Karteikasten“.
- Rundenende nennt die Wörter, die in dieser Runde in „Kann ich“ kamen
  (höchstens 8 plus „und N weitere“).

## Annahmen und Risiken

- **Abstand der Abfrage:** Drei Karten nach der Einführung ist eine Annahme.
  Lang genug, um nicht nur abzuschreiben, kurz genug, um es noch zu wissen.
  Liegt in der Konfiguration und lässt sich ändern.
- **Erwachsene** sehen die Einführung auch. Das kostet je neuem Wort einen
  Tipp. Ein Schalter ist bewusst nicht vorgesehen. Wenn es stört, wäre er
  eine kleine spätere Ergänzung.
- **Strengere Lernserie:** Ein Tag mit nur wenigen Karten zählt nicht mehr,
  solange noch mehr wartet. Das ist gewollt, kann aber einmal eine Serie
  kosten, die vorher gehalten hätte. Die Sonderregel „nichts mehr da“
  verhindert, dass das Kind dafür bestraft wird, dass nicht genug zu lernen
  war.
- **„Diese Woche“ ist eine Näherung:** Eine Karte in „Kann ich“ wird beim
  Lernen nicht mehr abgefragt. Ihre letzte Antwort ist deshalb der Moment,
  in dem sie ankam. Wird sie danach im Test üben gefragt, ändert das nichts,
  denn dort wird nichts gespeichert.
- **„Wörter“ heißt Karten.** Eine Karte mit „Hund – dog, hound“ zählt als ein
  Wort. Das ist einfache Sprache, keine Linguistik.
- **Leitlinie 6** bleibt eingehalten: Ring, Zahl und Wortliste sind
  Rückmeldung, keine Belohnung. Es gibt nichts zu sammeln und keinen
  Vergleich mit anderen.
