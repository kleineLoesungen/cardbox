# Duell

Status: **umgesetzt** (20.10.2026)

Entscheidungen (Rückfragen vom 20.10.2026):

- **Nacheinander:** Jede und jeder spielt, wann es passt, innerhalb von
  24 Stunden. Das Ergebnis steht fest, sobald alle fertig sind.
- **Abfrageart wählt, wer das Duell anlegt:** „Auswahl aus 4“ oder
  „Eintippen“.
- **Kleine Gruppe:** Wer das Duell anlegt, gibt die **Anzahl der Spieler**
  an (2 bis 10, die Erstellerin mitgezählt). Daran erkennt die App, wann
  alle gespielt haben und das Duell beendet ist.
- **Wertung:** Mehr richtige Antworten gewinnen, bei Gleichstand die
  kürzere Zeit. Gewusste Wörter zählen wie beim Üben fürs Tagesziel, nicht
  für den Karteikasten.
- **Auch Gäste ohne Konto können mitmachen**, mit dem Code und einem
  Namen. Ein Duell anlegen können nur Mitglieder.

> **Abweichung von einer Leitlinie:** Bisher gilt „Motiviert, ohne Punkte –
> ohne Ranglisten oder Vergleiche“ (motivierender-start.md, Produktseite).
> Ein Duell mit Gewinner ist bewusst ein Vergleich. Er bleibt eng begrenzt:
> Er gilt nur unter den Leuten mit demselben Code und nur für dieses eine
> Duell. Es gibt keine dauerhafte Rangliste, keine Punkte, die sich
> ansammeln, und keine Siegesserie. Wer verliert, sieht seine eigene
> Leistung; bei mehreren Runden gibt es nach jeder Runde eine neue Chance.

## Ziel

Kinder fordern Geschwister oder Freunde mit einem Code heraus, auch solche
ohne eigenes Konto. Alle bekommen dieselben 10 Wörter aus einem Stapel, am
Ende steht fest, wer gewonnen hat.
Das macht Üben zu einem kurzen gemeinsamen Spiel.

## Nicht dabei

- Live-Duelle mit Warteraum oder Zwischenstand.
- Eine Rangliste über mehrere Duelle, Punkte, Abzeichen oder Siegesserien.
- Benachrichtigungen (Push), wenn jemand fertig ist oder herausfordert.
- Umdrehen mit „Gewusst“ als Abfrageart, weil man sich dabei selbst
  bewertet.
- Duelle in der Eltern-Ansicht und über die API.
- Ein Konto für Gäste. Ein Gast ist nur ein Name in genau einem Duell; es
  entsteht kein Benutzer, die Freigabe wird nicht berührt.

## Verhalten

### Duell anlegen

- Auf der Stapelseite eines Stapels, den man lernt (eigener, auch privat,
  oder geteilter mit „lerne ich“ oder „pausiert“), steht unter den
  Üben-Knöpfen **„Duell starten“**. Der Stapel braucht mindestens 4 Karten.
- Ein Blatt fragt nach
  - der **Anzahl der Spieler**: 2 bis 10 inklusive mir, Vorgabe 2;
  - der **Abfrageart**: „Auswahl aus 4“ (vorgewählt) oder „Eintippen“.

  Darunter steht der Knopf **„Code erzeugen“**. Andere Zahlen ergeben
  „Bitte 2 bis 10 Spieler wählen.“
- Die App zieht **10 zufällige Karten** aus den aktiven Karten, bei weniger
  Karten alle. Gefragt wird immer vorne → hinten, mit der Hauptbedeutung.
  Bei „Auswahl aus 4“ legt sie für jede Karte **drei falsche Antworten** aus
  demselben Stapel fest und mischt die vier Antworten. Karten, Reihenfolge
  und Auswahl stehen damit fest und sind für alle gleich.
- Danach öffnet sich die Duellseite. Dort stehen **„Los geht's“**,
  „Noch 3 Plätze frei“ und groß der Code, z. B. `D7P2-M9QX`, mit „Gilt bis
  21.10., 19:31“ und dem Hinweis, dass man unter `/duell` auch ohne Konto
  mitmachen kann. Die Erstellerin ist automatisch die erste Spielerin.

### Mitmachen

- Unter **Mehr → Duelle** steht ein Feld **„Code eingeben“**. Groß/Klein,
  Leerzeichen und Bindestrich sind egal.
- Wer den Code eingibt, kommt auf die Duellseite: Stapelname, Abfrageart,
  wer schon mitmacht, und **„Los geht's“**.
- **Fehler:** Ein falscher Code oder einer, dessen Duell schon gelöscht ist,
  ergibt „Dieser Code passt nicht.“ Der Code eines beendeten Duells führt
  24 Stunden lang zum Ergebnis (siehe unten). Sind schon so viele dabei wie angegeben, kommt „Das Duell ist
  schon voll.“ Ist man schon dabei, führt der Code einfach zur Duellseite.
- Sobald alle Plätze vergeben sind, nimmt der Code niemanden mehr an. Wird
  jemand entfernt, wird sein Platz wieder frei.
- Codes eingeben ist gebremst: 10 Versuche pro Stunde je Konto.
- **Einladung teilen (Entscheidung vom 20.10.2026):** Solange Plätze frei
  sind, steht unter dem Code **„Einladung teilen“**. Am Handy öffnet das
  das Teilen-Menü (Chat), sonst wird der Text kopiert („Einladung kopiert –
  jetzt im Chat einfügen.“). Der Text: „Machst du mit beim Duell „Tiere“?
  Code: D7P2-M9QX“ und der Link `…/duell#D7P2-M9QX`.
  - Der Code steht hinter `#`: Er füllt das Code-Feld aus, erreicht aber nie
    den Server und landet in keinem Protokoll. Eintippen und „Mitmachen“
    bleiben – ein Link allein tritt nirgends bei.
  - Angemeldete landen von `/duell` unter **Mehr → Duelle**, das Feld ist
    auch dort ausgefüllt. „Anmelden“ auf der Gastseite führt über die
    Anmeldung zu **Duelle**; der Code bleibt dafür im Tab gemerkt
    (`sessionStorage`).

### Als Gast (ohne Konto)

- Die öffentliche Seite **`/duell`** hat die Felder **„Code“** und **„Dein
  Name“** (2–20 Zeichen, z. B. ein Vorname). Wer angemeldet ist, kommt von
  dort zu „Duelle“ und spielt mit seinem Konto.
- Nach dem Eingeben bekommt der Gast einen **persönlichen Link**
  (`/duell/<geheimer Schlüssel>`). Darüber spielt er und sieht später das
  Ergebnis. Die Seite zeigt den Link mit „Link kopieren“ und dem Hinweis
  „Heb dir diesen Link auf – damit siehst du später, wer gewonnen hat.“ Im
  selben Browser führt `/duell` auch ohne Link zurück zum laufenden Duell.
- Gäste erscheinen in der Liste mit „(Gast)“ hinter dem Namen.
- Für Gäste zählt nichts fürs Tagesziel (sie haben keins). Runden starten
  können nur Mitglieder (die Erstellerin), Gäste spielen alle Runden mit.
- Die Erstellerin kann jeden Teilnehmer **entfernen**, Gäste wie Mitglieder,
  z. B. wenn ein Code bei Fremden gelandet ist oder ein Name unpassend ist.
  Ein entfernter Gast-Link führt danach ins Leere.
- Die Gast-Seiten tragen `noindex` und geben ihre Adresse nicht als
  Referrer weiter, weil der Link wie ein Schlüssel wirkt.

### Spielen

- Die Runde läuft im Vollbild wie eine Lernrunde, mit Fortschritt „3 / 10“.
- **Auswahl aus 4:** vier große Knöpfe. Nach dem Tippen sieht man sofort
  „Richtig!“ oder „Richtig ist: …“ und geht mit „Weiter“ zur nächsten Karte.
- **Eintippen:** wie „Antworten eintippen“ (AnswerCheckService). Auch
  „Fast!“ zählt als richtig, mit der richtigen Schreibweise.
- **Zeit:** Gemessen wird je Karte vom Anzeigen bis zur Antwort, höchstens
  60 Sekunden je Karte. So kostet eine Unterbrechung nicht das ganze Duell.
  Die Zeit misst der Server, nicht das Gerät.
- Jede Person spielt **einmal**. Wer abbricht, macht später an derselben
  Karte weiter. Beantwortete Karten lassen sich nicht wiederholen.
- **Tagesziel:** Jede richtige Antwort zählt einmal fürs Tagesziel, wie
  beim Üben. Am Karteikasten ändert sich nichts.

### Ergebnis

- Auf der Duellseite stehen alle Teilnehmenden in der Reihenfolge der
  Wertung, mit „8 / 10“ und der Zeit („1:12 min“). Der erste Platz hat
  🏆 und **„Ben gewinnt!“**.
- Solange das Duell läuft, steht dort, worauf es wartet. Die eigene
  Leistung ist schon zu sehen.
  - Wer schon dabei ist, aber noch nicht fertig: „Warten auf: Clara, Max“.
  - Freie Plätze: „Noch 1 Platz frei – Code: D7P2-M9QX“.
- **Das Duell ist beendet**, sobald so viele fertig sind wie Spieler
  angegeben. Dann erscheint der Gewinner; der Code gilt nicht mehr.
- Danach nimmt der Code niemanden mehr auf, er zeigt nur noch das Ergebnis.
- Spätestens 24 Stunden nach dem Anlegen ist es auch beendet, wenn nicht
  alle gespielt haben. Wer dabei, aber nicht fertig ist, steht mit „nicht
  fertig“ unten; freie Plätze verfallen. Hat dann nur eine Person
  überhaupt eine Karte beantwortet, heißt es statt eines Siegers „Niemand
  hat mitgespielt.“
- **Nur fertig gespielte Runden zählen.** Wer eine Runde abbricht, bekommt
  für sie weder Punkte noch Zeit und kann mit ihr nicht gewinnen – sonst
  wäre Aufhören bei gutem Stand ein Vorteil (kürzere Zeit).
- **Runden (Entscheidungen vom 20.10.2026):**
  - **Beim Anlegen** wählt man **1 bis 5 Runden** (Vorgabe 1), je Runde
    10 Wörter. Andere Zahlen ergeben „Bitte 1 bis 5 Runden wählen.“
  - **Wörter aller Runden** zieht die App schon beim Anlegen. Keine Runde
    wiederholt ein Wort, bis der Stapel durch ist. Danach kommen die
    restlichen neuen Wörter dran, aufgefüllt mit früheren, und es geht von
    vorn los. Die Duellseite sagt dann „Alle Wörter des Stapels waren schon
    dran – ab Runde N kommen sie wieder.“ Weil alles vorab gezogen ist,
    bleiben spätere Runden spielbar, auch wenn der Stapel geändert oder
    gelöscht wird.
  - **Dieselben Spieler** spielen alle Runden. Einsteigen geht nur in
    Runde 1. Danach sagt der Code „Das Duell läuft schon – einsteigen geht
    nur in Runde 1.“
  - **Ende einer Runde:** wenn alle gespielt haben (in Runde 1: so viele wie
    angegeben), spätestens 24 Stunden nach ihrem Start. Dann zeigt die
    Duellseite „Runde N ist fertig!“, die **Rangliste der Runde** und darunter
    den **Zwischenstand**.
  - **Nächste Runde:** Nur die Erstellerin startet sie, mit **„Runde N
    starten“**, innerhalb von 24 Stunden; sonst endet das Duell nach der
    zuletzt gespielten Runde. Die anderen sehen „Anna startet gleich Runde
    N.“ Wer die vorige Runde nicht fertig gespielt hat, steigt in der neuen
    Runde vorn ein; die abgebrochene Runde zählt für sie nicht.
  - Ist die Frist abgelaufen, während die Seite noch offen war, meldet
    „Runde N starten“: „Die Zeit für Runde N ist abgelaufen – das Duell ist
    beendet.“
  - **Gesamtsieg:** die meisten richtigen Antworten aller fertig
    gespielten Runden zusammen, bei Gleichstand die kürzere Zeit dieser
    Runden. Am Ende stehen die Rangliste
    der letzten Runde und „Gesamt“.
  - Eine Revanche als neues Duell gibt es nicht mehr; wer weiterspielen
    will, legt ein neues Duell an.
- **Ergebnis nach dem Ende (Entscheidung vom 20.10.2026):** Das Ergebnis
  bleibt **24 Stunden nach dem Ende** sichtbar, für die Spieler auf ihrer
  Duellseite und **für jeden mit dem Code**. Wer den Code eines beendeten
  Duells eingibt, sieht nur die Rangliste und spielt nicht mit; Gäste
  brauchen dafür keinen Namen. Nach der Code-Eingabe geht es auf die Seite
  `/duell/ergebnis/<Duell>`, die nur in der Sitzung aufgeht, die den Code
  eingegeben hat (Neuladen schickt nichts erneut). Die Duellseite sagt „Das
  Ergebnis ist bis … zu sehen – auch mit dem Code … Danach wird das Duell
  gelöscht.“ Nach dem Ende kann niemand mehr entfernt werden: Namen und
  Leistungen bleiben diese 24 Stunden für alle mit dem Code sichtbar –
  bewusst so, das Ergebnis soll feststehen.
- **Danach wird alles gelöscht:** Duell, Code, Karten, Rangliste und
  Ergebnisse, samt Gastnamen. Ein Duell, das ohne alle Spieler abläuft,
  endet nach 24 Stunden und ist 24 Stunden später weg. Gelöscht wird bei
  jedem Zugriff auf Duelle (Liste, Duellseite, Code eingeben), einen
  Scheduler gibt es nicht.
- **Die Duellseite aktualisiert sich selbst (Entscheidung vom
  20.10.2026):** Solange das Duell läuft, fragt sie alle **3 Sekunden** den
  Stand ab – wer fertig ist, ob eine Runde vorbei ist, ob die nächste
  läuft, wer gewonnen hat. Ohne Neuladen erscheint so „Runde N starten“
  bzw. „Los geht's – Runde N“. Nicht in einem verborgenen Tab (beim
  Zurückkehren sofort) und nicht, solange eine Rückfrage offen ist. Nach
  dem Ende hört sie auf.
  - Die Seite schickt ihren Stand mit (ein Fingerabdruck aus Runde, Fristen
    und dem Fortschritt aller Spieler). Hat sich nichts geändert, antwortet
    der Server mit 204 und nichts wird ausgetauscht – kein Fokusverlust,
    kein Springen beim Lesen. Beim Austausch steht der Gast-Link weiter vor
    der Rangliste.
  - Fehler beim Abfragen (kein Netz, Server kurz weg) bleiben still; der
    nächste Versuch kommt ohnehin gleich. Wer entfernt wurde oder dessen
    Duell gelöscht ist, landet bei **Duelle** bzw. `/duell`.
  - Ist die Sitzung abgelaufen, geht es zur Anmeldung und danach zurück zum
    Duell.
  - Gelöscht wird beim Abfragen höchstens einmal pro Minute (`prune()`),
    damit viele offene Seiten die Datenbank nicht belasten.
  - Bewusst so: Solange eine Duellseite offen ist, läuft die Sitzung nicht
    ab – jede Abfrage zählt als Aktivität.
- Unter **Mehr → Duelle** stehen die eigenen Duelle: erst die laufenden,
  dann die beendeten, deren Ergebnis noch zu sehen ist.

### Wenn sich etwas ändert

- Die Fragen und Antworten werden beim Anlegen ins Duell **kopiert**. Wird
  danach eine Karte geändert oder gelöscht, ändert sich das Duell nicht.
- Wird der Stapel gelöscht, bleibt das Duell mit seinen Kopien spielbar;
  der Stapelname bleibt stehen.
- Wird ein Konto gelöscht, verschwindet es aus allen Duellen. Wird das
  Konto der Erstellerin gelöscht, verschwinden ihre Duelle ganz.

## Berechtigungen

Alles in der Gruppe `auth`, keine neue Rolle.

| Route | Zweck | Prüfung im Service |
|---|---|---|
| `GET /duels` | Liste, Code eingeben | eigene Duelle |
| `POST /decks/{deck}/duels` | anlegen | Stapel sichtbar und gelernt (eigener oder gefolgter), sonst 404; mindestens 4 Karten; `throttle:20,60,duel-create` |
| `POST /duels/join` | Code eingeben | `throttle:10,60,duel-code` |
| `GET /duels/{duel}` | Duellseite und Ergebnis | nur Teilnehmende, sonst **404** |
| `GET /duels/{duel}/live` | der veränderliche Teil der Duellseite (htmx, alle 3 Sekunden) | nur Teilnehmende |
| `GET /duels/{duel}/play` | spielen (die Uhr der Karte startet beim Anzeigen) | nur Teilnehmende |
| `POST /duels/{duel}/answer` | Antwort (htmx) | nur Teilnehmende, nur die aktuelle Karte |
| `POST /duels/{duel}/next-round` | nächste Runde starten | nur die Erstellerin, nur zwischen den Runden und vor der letzten, sonst 404 |
| `POST /duels/{duel}/players/{player}/delete` | Entfernen | nur die Erstellerin, nur solange das Duell läuft, nie sich selbst; sonst 404 |

**Öffentlich, für Gäste** – bewusst in `PUBLIC_ROUTES` von
`RouteProtectionTest`, jeweils mit Begründung:

| Route | Zweck | Schutz |
|---|---|---|
| `GET /duell`, `POST /duell` | Code und Name eingeben | `throttle:30,60,duel-guest` je IP (auch fürs Ergebnis-Ansehen, deshalb großzügiger); nur mit gültigem Code und freiem Platz entsteht etwas (höchstens die angegebene Spielerzahl, maximal 10) |
| `GET /duell/ergebnis/{duel}` | Ergebnis eines beendeten Duells | nur für die Sitzung, die den Code eingegeben hat, sonst 404 |
| `GET /duell/{token}`, `GET …/play`, `POST …/answer` | spielen, Ergebnis | der geheime Schlüssel (32 zufällige Bytes, gespeichert nur als HMAC); Bremse `duel-play`: 120 je Minute **je Link** |
| `GET /duell/{token}/live` | Stand der Duellseite (alle 3 Sekunden) | derselbe Schlüssel; eigene Bremse `duel-live`, 120 je Minute je Link – offene Tabs bremsen nie das Antworten |

Das Duell zeigt den Mitspielern nur die 10 kopierten Wörter und den
Stapelnamen, nie den Stapel selbst. Ein privater Stapel bleibt also privat,
auch gegenüber Gästen. Auch Admins sehen fremde Duelle nicht. Gäste sehen
von Mitgliedern nur den Anzeigenamen.

## Datenmodell

Migration `2026_10_20_000000_create_duel_tables.php`:

- **`duels`**:
  - `id` (uuid);
  - `creator_id` (→ users, cascade);
  - `deck_id` (→ decks, `nullOnDelete`) und `deck_name` (Kopie);
  - `mode` (`choice` | `typed`);
  - `player_count` (2–10, angegebene Anzahl der Spieler);
  - `finished_at` (null = läuft; gesetzt, sobald alle Plätze fertig sind
    oder beim ersten Aufruf nach Ablauf der 24 Stunden);
  - `front_language`, `back_language` (für `lang` und das Prüfen beim
    Eintippen);
  - `code_hash` (HMAC wie bei den Eltern-Codes, unique, zum Nachschlagen)
    und `code_encrypted` (mit `APP_KEY` verschlüsselt, zum erneuten Anzeigen
    auf der Duellseite);
  - `expires_at`, `created_at`.
- **`duel_cards`**:
  - `duel_id` (→ duels, cascade), `position` (1–10), Primärschlüssel aus
    beiden;
  - `question` (Text), `hint` (Hinweis der Karte), `answers` (jsonb, alle
    Bedeutungen fürs Prüfen);
  - `choices` (jsonb, vier Texte) und `correct_choice` (Stelle der
    richtigen), beide null bei `typed`. Falsche Antworten sind
    Hauptbedeutungen anderer Karten und nie eine Bedeutung der eigenen.
- **`duel_players`**:
  - `id` (uuid), `duel_id` (→ duels, cascade);
  - entweder `user_id` (→ users, cascade; unique zusammen mit `duel_id`)
    oder für Gäste `guest_name` und `guest_token_hash` (HMAC, unique);
    eine Prüfung stellt sicher, dass genau eins von beiden gesetzt ist;
  - `position` (nächste Karte), `correct`, `time_ms`;
  - `shown_at` (wann die aktuelle Karte erschien), `joined_at`,
    `finished_at` (null = nicht fertig).

Migration `2026_10_20_100000_add_rounds_to_duels.php` (Runden):

- `duels.rounds` (1–5) und `duels.round` (die laufende Runde),
  `duels.round_finished_at` (die laufende Runde ist vorbei und wartet auf
  die Erstellerin), `duels.repeat_from_round` (erste Runde mit Wörtern, die
  schon dran waren; null = keine);
- `duel_cards.round` und `duel_cards.card_id` (die Karte des Stapels, ohne
  Fremdschlüssel); Positionen zählen über alle Runden durch;
- `duel_players.round_correct`, `round_time_ms` (laufende Runde),
  `correct`/`time_ms` bleiben die Summen, `answered` (beantwortete Karten
  aller Runden; 0 = hat nicht mitgespielt);
- neue Tabelle `duel_round_results` (`player_id` → duel_players, `round`,
  `correct`, `time_ms`): das Ergebnis je Spieler und fertiger Runde; die
  Wertung rechnet nur damit.

## Betroffene Stellen

- **Services:** neu `DuelService`. Er übernimmt:
  - anlegen (Karten ziehen, falsche Antworten, Code);
  - Code einlösen;
  - `getForPlayer` (404, wenn man nicht teilnimmt);
  - aktuelle Karte und Antwort werten (Auswahl oder AnswerCheckService),
    Zeit und Tagesziel (`LeitnerService::countAnswer`);
  - Runden: `drawRounds` (alle Runden beim Anlegen, ohne Wiederholung),
    `nextRound`, `roundStandings`, Gesamtwertung in `standings`;
  - `prune` löscht Duelle 24 Stunden nach dem Ende; Ergebnis per Code.

- **Controller:** neu `DuelController`, für Mitglieder und – mit eigenen
  Methoden – für Gäste; das Spielen teilen sich beide.
- **Support:** `ShareCode` (Codes erzeugen, prüfen, HMAC), auch für
  `GuardianService`. Benannte Bremse `duel-play` im `AppServiceProvider`.
- **Middleware/Antwort:** `X-Robots-Tag: noindex` und
  `Referrer-Policy: no-referrer` für die Gast-Seiten.
- **Routen:** `routes/web.php`, Tabelle oben.
- **Ansichten:**
  - Stapelseite: Knopf und Blatt „Duell starten“;
  - neu `duels/index`, `duels/show` (Code, Mitspieler, Ergebnis) und
    `duels/play` mit Fragment im Vollbild-Layout (für Mitglieder und Gäste
    gleich, nur mit anderen Adressen);
  - neu die öffentliche Seite `duels/guest` (Code und Name) im Layout ohne
    Navigation;
  - `nav-links` (Menüpunkt „Duelle“), `Navigation::SECTIONS` (Bereich
    `duels`, Duellseiten fest darunter);
  - Hilfe.
- **CHANGELOG.md**, Produktseite (ein Satz, und die Aussage „ohne Vergleiche“
  anpassen).

## Tests

`tests/Feature/DuelTest.php`:

- **Anlegen:**
  - Nur bei gelernten Stapeln, mindestens 4 Karten.
  - Bei kleinen Stapeln weniger als 10 Karten.
  - Für alle gleiche Karten und gleiche Auswahl.
  - Falsche Antworten kommen aus dem Stapel und doppeln die richtige nicht.
- **Code:**
  - Einlösen, Groß/Klein und Leerzeichen sind egal.
  - Falscher und abgelaufener Code.
  - Mehr als die angegebene Zahl wird abgewiesen („schon voll“).
  - Ein entfernter Platz wird wieder frei.
  - Eine Spielerzahl außerhalb von 2–10 wird abgelehnt.
  - Erneutes Einlösen führt nur zur Seite.
  - Die Bremse greift nach 10 Versuchen.
- **Spielen:**
  - Richtige und falsche Auswahl.
  - Eintippen mit „Fast!“.
  - Nur die aktuelle Karte wird gewertet: keine doppelte Antwort, keine
    fremde Position.
  - Weitermachen nach Abbruch.
  - Höchstens 60 s je Karte.
  - Das Tagesziel zählt, der Karteikasten bleibt unverändert.
- **Ergebnis:**
  - Reihenfolge nach richtigen Antworten, dann nach Zeit.
  - „Warten auf …“ und „Noch N Plätze frei“.
  - Beendet, sobald so viele fertig sind wie angegeben; danach gilt der
    Code nicht mehr.
  - Bei nur einem Spieler nach 24 Stunden gibt es keinen Sieger.
- **Auswahl (Issue #21):** Lernt die Erstellerin nur einen Teil des Stapels
  („Karten auswählen“), kommen nur diese Karten dran; das Duell-Fenster sagt
  „aus deiner Auswahl (6 von 20 Karten)“.
  - Nach 24 Stunden steht es fest, mit „nicht fertig“.
  - Eine abgebrochene Runde zählt nicht; wer abbricht, gewinnt nicht.
- **Runden:** mehrere Runden ohne Wiederholung, Rangliste je Runde,
  Zwischenstand, nur die Erstellerin startet die nächste (sonst 404, nach
  Ablauf der Frist eine Meldung),
  Gesamtsieg nach Summe; Ablauf einer Runde nach 24 Stunden mit „nicht
  fertig“ und Weiterspielen ab der neuen Runde; Ende, wenn die nächste
  Runde nicht startet; Einsteigen nur in Runde 1; Wiederholung erst, wenn
  der Stapel durch ist, mit Hinweis; Rundenzahl 1–5; spielbar trotz
  gelöschtem Stapel; wer nie gespielt hat, zählt ab Runde 2 nicht als
  Spieler; Entfernen des Letzten, der noch spielt, beendet die Runde;
  Aufräumen nach Pause und Ergebniszeit.
- **Trennung:**
  - Nicht Teilnehmende bekommen 404, auch Admins.
  - Ungültige IDs ergeben 404.
  - Ein privater Stapel ist für Mitspieler nicht aufrufbar.
- **Gäste:**
  - Mit Code und Name spielen, ohne Konto; es entsteht kein Benutzer.
  - Der persönliche Link funktioniert später wieder; ein falscher Link
    ergibt 404.
  - Der Name wird geprüft (2–20 Zeichen).
  - „(Gast)“ steht in der Liste, kein Tagesziel.
  - Nach dem Entfernen ergibt der Link 404.
  - Die Bremse je IP greift.
  - `noindex` und Referrer-Policy sind gesetzt.
  - Gäste kommen nicht an Stapelseiten oder andere Duelle.
- **Routentest:** Die neuen öffentlichen Routen stehen begründet in
  `PUBLIC_ROUTES`.
- **Ergebnis und Löschen:** Bis 24 Stunden nach dem Ende sehen Spieler und
  andere mit dem Code (Mitglied oder Gast, ohne Namen) die Rangliste, ohne
  mitzuspielen. Danach sind Duell, Karten, Spieler und Code gelöscht, auch
  ohne dass jemand das Duell noch einmal ansieht.

## Annahmen und Risiken

- **Stapelwahl:** Jeder gelernte Stapel geht, auch ein privater. Die
  Mitspieler sehen 10 Wörter daraus, die Erstellerin entscheidet das mit dem
  Anlegen. (Nicht ausdrücklich gefragt.)
- **Richtung:** immer vorne → hinten. Rückwärts kann später kommen.
- **Fairness bei „Auswahl aus 4“:** Ein Stapel mit sehr ähnlichen Wörtern
  macht es schwer. Das ist so gewollt.
- **Mogeln:** Wer zwei Geräte nutzt oder nachschlägt, ist schneller. Die
  60-s-Grenze und die Messung auf dem Server begrenzen das, verhindern es
  aber nicht. Bei Kindern unter sich ist das hinnehmbar.
- **Gefühl beim Verlieren:** Es gibt keine dauerhafte Rangliste. Das
  Ergebnis nennt für jeden die eigene Leistung; bei mehreren Runden gibt es
  nach jeder Runde eine neue Chance.
- **Zielgruppe ab 10 Jahren und Gäste:** Mit Gästen kann ein weitergegebener
  Code Fremde ins Duell bringen. Dagegen helfen:
  - Ein Code nimmt nur 24 Stunden lang Spieler auf; danach zeigt er noch
    24 Stunden das Ergebnis.
  - Es sind nur so viele Personen dabei, wie angegeben (höchstens 10).
    Danach nimmt der Code niemanden mehr an.
  - Gäste sind sichtbar als „(Gast)“.
  - Die Erstellerin kann jeden entfernen.

  Wer den Code hat, sieht im Duell Anzeigenamen und Leistungen aller
  Spieler, sonst nichts aus der App.
- **Gastnamen** sind frei gewählt und werden nicht geprüft. Unpassende
  Namen kann die Erstellerin entfernen; 24 Stunden nach dem Ende sind sie
  gelöscht.
- **Öffentliche Routen** öffnen Cardbox erstmals für Menschen ohne Konto.
  Gebremst wird je IP und je Schlüssel; mehr als 10 Gäste je Duell gibt es
  nicht. Ein Captcha ist nicht vorgesehen.
- **Gast-Links in der Sitzung** stehen dort nur verschlüsselt (damit
  `/duell` im selben Browser zurückführt); der Code des Duells ist für die
  Anzeige verschlüsselt, zum Nachschlagen nur als HMAC gespeichert. Nach
  einem neuen `APP_KEY` zeigt die Duellseite keinen Code mehr.
- **„Das Duell ist schon voll.“** verrät, dass es den Code gibt. Bewusst so:
  Für Kinder ist das verständlicher, und Raten ist bei 10¹² Codes und der
  Bremse aussichtslos.
- **TRUST_PROXY** muss bei öffentlichen Gast-Seiten besonders stimmen:
  Ohne echten Proxy auf `true` gesetzt, umgeht ein falscher
  `X-Forwarded-For` die Bremse je IP.
- **Ein verlorener Gast-Link** lässt sich nicht wiederherstellen: Ohne Link
  und in einem anderen Browser findet der Gast sein Duell nicht mehr. Das
  ist der Preis dafür, ohne Konto zu spielen.
- **Leitlinie:** Die Produktseite verspricht bisher „Ohne Abzeichen,
  Ranglisten oder Vergleiche“. Der Satz muss angepasst werden, z. B.
  „Ohne Punktesammeln und dauerhafte Ranglisten – auf Wunsch ein kurzes
  Duell unter Freunden“.
