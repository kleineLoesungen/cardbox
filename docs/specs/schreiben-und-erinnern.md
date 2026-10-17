# Schreiben und Erinnern

Status: Teil A und B **umgesetzt** (17.10.2026)

Die nächsten zwei Schritte aus der Vorschlagsliste vom 16.10.2026:

- **A. Schreibmodus:** Die Antwort wird eingetippt statt nur umgedreht.
- **B. Erinnerung:** eine tägliche Mitteilung der installierten App.

Entscheidungen (Rückfragen vom 17.10.2026):

- Eintippen ist ein **Schalter je Stapel** („Antworten eintippen“). Er gilt
  beim Lernen und bei „Für den Test üben“.
- **Kleiner Tippfehler** = „Fast richtig“: Beim Lernen zählt das als gewusst.
  Bei „Für den Test üben“ zählt es als nicht gewusst, denn im Test zählt die
  Rechtschreibung.
- **Auslöser der Erinnerungen:** Eine geschützte Aufruf-URL, die ein
  externer Cron-Dienst oder der Cron des Hosters alle 15 Minuten aufruft.
  Dazu gibt es einen gleichwertigen artisan-Befehl.
- **Reihenfolge:** Erst A umsetzen, dann B. Beide stehen in dieser Datei,
  werden aber getrennt umgesetzt, committet und freigegeben.

## Ziel

Kinder üben die Schreibweise, so wie sie im Vokabeltest gefragt wird. Die
Prüfung ist nachsichtig: Groß/Klein ist egal, jede Bedeutung zählt. Und wer
möchte, wird jeden Tag zur gewählten Uhrzeit ans Lernen erinnert, ohne die
App öffnen zu müssen.

## Nicht dabei

- Spracheingabe, Handschrift, Auswahl aus mehreren Antworten (Multiple
  Choice).
- „War doch richtig“ zum Überstimmen der Prüfung. Das lädt zum Schummeln ein.
  Fehlt eine Bedeutung, gehört sie auf die Karte (Fehler melden, Bearbeiten).
- Sprachabhängige Regeln wie „to “ vor englischen Verben oder Artikel
  („der/die/das“). Das geht über Klammern auf der Karte, siehe A3.
- E-Mail oder SMS als Erinnerung (Konten haben keine E-Mail-Adresse).
- Mehrere Erinnerungen am Tag, Erinnerungen an Wochentagen, Erinnerungen
  durch Eltern.
- Ein dauerhaft laufender Hintergrundprozess oder Queue-Worker (CLAUDE.md).

---

## A. Schreibmodus

### A1. Einschalten

- Im „⋯“-Menü eigener und gelernter Stapel steht neu: **„Antworten
  eintippen“ (an/aus)**, darunter klein „Statt umzudrehen schreibst du die
  Antwort.“ Gespeichert wird je Person und Stapel, wie „Auch rückwärts
  lernen“.
- Vorgabe ist aus. Bisherige Stapel ändern sich nicht.

### A2. Ablauf einer Abfrage

Gilt für Abfragen in Lernrunden („Los geht's“, „Nur diesen Stapel lernen“)
und bei „Für den Test üben“, wenn der **Stapel der Karte** den Schalter an
hat. In einer gemischten Runde unter „Heute“ wird also je Karte eingetippt
oder umgedreht. Einführungen neuer Wörter (motivierender-start.md, A) bleiben
offen, dort wird nichts getippt. „Fach üben“ bleibt beim Umdrehen.

1. Oben steht die Frage wie bisher, mit Hinweis und Vorlesen.
2. Darunter steht ein **Eingabefeld** („Deine Antwort“) mit der Sprache der
   Antwortseite (`lang`). Autokorrektur, Großschreibung und
   Rechtschreibprüfung sind aus, damit das Gerät nicht mitschreibt. Das Feld
   hat beim Erscheinen den Fokus. Auf dem iPhone öffnet sich die Tastatur
   dabei vermutlich nicht von selbst, weil der Fokus nach einer Antwort des
   Servers kommt. Dann tippt man einmal ins Feld (auf einem echten Gerät zu
   prüfen).
3. Darunter stehen **„Prüfen“** als Hauptknopf (auch Enter) und ein
   schlichter Knopf **„Weiß ich nicht“**.
4. Nach „Prüfen“ zeigt dieselbe Stelle das **Ergebnis**:
   - **Richtig** (grün): „Richtig!“, darunter die Hauptbedeutung und
     weitere Bedeutungen als „auch: …“.
   - **Fast richtig** (gelb): „Fast! So schreibt man es:“ und das richtige
     Wort mit **markierten Stellen**, die anders sind. Darunter klein „Du
     hast geschrieben: *recieve*“.
   - **Falsch** oder „Weiß ich nicht“ (neutral, nicht rot): „Richtig ist:“
     mit allen Bedeutungen, darunter „Du hast geschrieben: …“ (bei „Weiß ich
     nicht“ entfällt das).
   - Ein Knopf **„Weiter“** (auch Enter), ein normaler Link auf die Runde.
     Vorlesen der Antwort ist möglich. Die Ergebniszeile bekommt den Fokus,
     damit Screenreader sie vorlesen. Die markierten Buchstaben werden für
     Screenreader zusätzlich genannt („Anders als bei dir: ei“).
5. Wie das Ergebnis zählt:

| Ergebnis | Lernen (Karteikasten) | Test üben |
|---|---|---|
| Richtig | Gewusst | Gewusst |
| Fast richtig | Gewusst | Nochmal (kommt wieder) |
| Falsch / Weiß ich nicht | Nochmal | Nochmal |

   Gewertet wird beim Prüfen, nicht erst bei „Weiter“. Ein Neuladen auf dem
   Ergebnis wertet nicht doppelt und führt zur nächsten Karte. Für das
   Tagesziel zählt die Antwort wie jede andere.

### A3. Wann eine Antwort richtig ist

Verglichen wird mit **jeder Bedeutung** der Antwortseite. Die beste
Übereinstimmung zählt. Vorher werden beide Seiten gleich behandelt:

- Groß/Klein egal; Leerzeichen am Rand weg, mehrere innen zu einem.
- Satzzeichen am Rand egal: am Ende `. ! ? , ; :`, am Anfang `¿ ¡`. Sie
  werden auch nie markiert.
- Gleiche Zeichen in verschiedener Unicode-Form (z. B. „é“ als „e“ plus
  Akzent, etwa aus PDFs) gelten als gleich (NFC, sofern `intl` vorhanden).
- **Teile in Klammern sind freiwillig, jede für sich:** „(to) miss“ ist mit
  „miss“ und „to miss“ richtig, „colo(u)r“ mit „color“ und „colour“, „(to)
  look (at)“ mit allen vier Formen. Ab fünf Klammern gelten nur alle oder
  keine. Steht auf der Karte „der Hund“, braucht es den Artikel. Soll er
  freiwillig sein, schreibt man „(der) Hund“.
- Bei Rechenkarten (Sprachen `formula`/`result`) zählt nur der Wortlaut nach
  dieser Vereinheitlichung. Es gibt kein „fast richtig“, denn „12“ und „13“
  sind nicht fast gleich.

**Fast richtig** ist eine Antwort, die zu einer Bedeutung

- nur in Akzenten/Umlauten abweicht („e“ statt „é“, „ae“ statt „ä“, „ss“
  statt „ß“), oder
- höchstens **einen Tippfehler** entfernt ist (ein Zeichen zu viel, zu
  wenig, falsch oder zwei vertauscht). Bei Wörtern ab 9 Zeichen sind es
  höchstens zwei. Unter 4 Zeichen gibt es kein „fast“: „cat“ und „car“ sind
  verschiedene Wörter.

Alles andere ist falsch. Eine leere Eingabe gilt als „Weiß ich nicht“.

### A4. Fehlerfälle

- Eine Eingabe über 500 Zeichen wird abgeschnitten verglichen und zählt
  dann als falsch. Es gibt keinen Fehler.
- Schalter für einen Stapel, den man weder besitzt noch lernt, oder eine
  ungültige ID ergibt 404 (wie „Auch rückwärts lernen“).
- Doppeltes „Prüfen“ oder ein alter Tab wertet nicht doppelt: Es gilt die
  bestehende Prüfung von Karte und Schritt.

---

## B. Erinnerung

### B1. Einrichten

Unter **Mein Konto** gibt es neu den Abschnitt **„Erinnerung“**:

- Ist ein Gerät eingerichtet: „Erinnerung jeden Tag um **17:00**“, Uhrzeit
  wählbar (06:00–21:00 in 15-Minuten-Schritten), dazu die Knöpfe „Speichern“
  und „Ausschalten“.
- Ist keins eingerichtet: Der Knopf **„Erinnerung einschalten“** fragt den
  Browser nach der Erlaubnis für Mitteilungen und meldet dieses Gerät an.
  Vorgabe ist 17:00.
- Mehrere Geräte sind möglich, z. B. Handy und Tablet. Jedes bekommt die
  Mitteilung. „Ausschalten“ meldet **alle** Geräte ab.
- Wo der Browser keine Mitteilungen kann, steht ein Hinweis statt des
  Knopfs. Auf dem iPhone/iPad geht es nur, wenn die App auf dem
  Home-Bildschirm liegt (iOS 16.4 oder neuer); der Hinweis verlinkt auf die
  Hilfe „Als App auf den Startbildschirm“. Hat die Person Mitteilungen
  abgelehnt: „Mitteilungen sind für diese Seite blockiert – in den
  Einstellungen des Browsers erlauben.“
- Ohne konfigurierte Schlüssel (siehe B4) fehlt der Abschnitt ganz.

### B2. Die Mitteilung

- **Wann:** einmal am Tag, sobald die gewählte Uhrzeit in der Lern-Zeitzone
  erreicht ist (Genauigkeit: Abstand der Aufrufe, also bis zu 15 Minuten
  später).
- **Nur wenn sinnvoll:** Ist das Tagesziel schon erreicht oder wartet nichts
  (keine fälligen und keine neuen Karten im Tageskontingent), kommt nichts.
  Der Tag gilt dann als erledigt.
- **Text:** Titel ist der App-Name. Der Inhalt lautet „12 Karten warten auf
  dich – ein paar Minuten reichen.“, bei einer laufenden Lernserie „🔥 5
  Tage in Folge – mach weiter! 12 Karten warten auf dich.“ Es gibt keine
  Namen und keine Wörter in der Mitteilung.
- **Antippen** öffnet „Heute“ (`/learn`), in der App, falls installiert.
- Kann der Push-Dienst ein Gerät nicht mehr erreichen (abgemeldet,
  deinstalliert: Antwort 404/410), wird es gelöscht.
- **Nur echte Push-Dienste:** Ein Gerät wird nur angenommen, wenn seine
  Adresse bei einem Push-Dienst der Browser liegt (`fcm.googleapis.com`,
  `push.apple.com`, `push.services.mozilla.com`, `notify.windows.com` samt
  Unteradressen, `push.endpoint_hosts`). Sonst könnte jemand den Server
  dazu bringen, an beliebige Rechner zu senden (SSRF).
- Der letzte Lauf (Zeit, Zahlen) steht in der Tabelle `settings` unter
  `reminders_last_run` und wird auf der Systemseite gezeigt.
- Antwortet die Anmeldung eines Geräts mit einem Fehler, kommt er als JSON
  (422) zurück, nicht als Weiterleitung. Das Skript zeigt dann „Das hat nicht
  geklappt“.

### B3. Auslöser

- **Aufruf-URL:** `POST /api/v1/reminders/run` mit dem Kopf
  `X-Cron-Token: <REMINDER_TOKEN>`. Sie verschickt alle fälligen
  Mitteilungen und antwortet mit `{"sent": 3, "skipped": 5, "removed": 0}`.
  Ein externer Dienst (z. B. cron-job.org) oder der Cron des Hosters ruft
  sie alle 15 Minuten auf.
  - Gedrosselt auf 10 Aufrufe pro Minute.
  - Ohne gesetztes `REMINDER_TOKEN` antwortet sie 404, die Funktion ist
    dann aus.
  - Wie beim Deploy-Hook nimmt sie zusätzlich `X-Cron-Token` statt
    `Authorization`, weil FastCGI auf dem Webspace den `Authorization`-Kopf
    verwirft.
- **Für cards.se-wi.com geplant:** Cronicle auf der NAS (Rückmeldung vom
  17.10.2026). Dort wird ein Ereignis mit dem Plugin „HTTP Request“
  angelegt: Methode `POST`, URL `https://cards.se-wi.com/api/v1/reminders/run`,
  Kopf `X-Cron-Token: …`, Zeitplan alle 15 Minuten, Zeitüberschreitung
  60 s. Cronicle zeigt Antwort und Fehler im eigenen Verlauf. Die
  Installationsanleitung bekommt dafür ein Beispiel. Das Token liegt nur in
  Cronicle und in der `.env` auf dem Webspace.
- **artisan:** `php artisan reminders:send` macht dasselbe, für Webserver
  und Docker-Host mit eigenem Cron.
- Zwei Aufrufe gleichzeitig verschicken nichts doppelt: Je Person wird
  `reminder_sent_on` vor dem Senden mit Sperre gesetzt.
- Die Mitteilungen gehen nacheinander raus, je Anfrage mit 5 s Zeitlimit,
  höchstens 100 je Aufruf (`push.max_messages`) und höchstens 40 s lang
  (`push.time_budget`, unter den 60 s von Cronicle). Was nicht mehr drankam,
  oder alles, wenn der Versand als Ganzes scheitert (Schlüssel, Umgebung),
  wird für den nächsten Aufruf wieder freigegeben (`failed`).
- Höchstens 5 Geräte je Konto (`push.max_devices`); ein neues ersetzt das
  älteste.
- Mehr als 2 Stunden verspätet (Aufrufer ausgefallen) wird für den Tag
  nichts mehr verschickt (`push.catch_up_hours`), damit kein Kind abends
  spät noch eine Mitteilung bekommt.
- Ein Gerät, das die Bibliothek nicht vorbereiten kann, gilt als „weg“ und
  wird gelöscht. Scheitern gleich die ersten drei so, liegt es an der
  Umgebung: Dann wird nichts gelöscht und alles kommt wieder.
- Antworten 403 (z. B. nach einem Schlüsselwechsel) löschen das Gerät
  nicht; sie stehen nur im Log (Host und Status, nie die Adresse).
- `docs/INSTALL-WEBSPACE.md` bekommt den Abschnitt „Erinnerungen
  einrichten“ mit Cronicle-Beispiel, `INSTALL-WEBSERVER.md` den Cronjob,
  auch für Docker (`docker compose exec app php artisan reminders:send`).

### B4. Konfiguration

Neue Umgebungsvariablen (`config/push.php`, Rezept 11):

| Variable | Zweck |
|---|---|
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | Schlüssel für Web-Push. Erzeugt mit `php artisan push:keys` (gibt die zwei Zeilen für `.env` aus). |
| `VAPID_SUBJECT` | Kontakt für die Push-Dienste, z. B. `mailto:admin@example.com` oder die App-URL. |
| `REMINDER_TOKEN` | Geheimnis für die Aufruf-URL. |

Die Systemseite (`/admin/system`) zeigt, ob Erinnerungen eingerichtet sind
(Schlüssel ja/nein, Token ja/nein) und wann zuletzt verschickt wurde.

Neue Abhängigkeit: **`minishlink/web-push`** (Composer). Sie übernimmt
Verschlüsselung und VAPID-Signatur nach RFC 8291/8292 und braucht die
PHP-Erweiterungen `openssl` und `curl`, dazu `gmp` oder `bcmath`. Die
Deploy-Prüfung zeigt, ob sie da sind, wie bei `zip`.

---

## Berechtigungen

| Route | Gruppe | Daten |
|---|---|---|
| `POST /decks/{deck}/typing` | `auth` | eigene Einstellung, eigener oder gelernter Stapel (`DeckPreferenceService`) |
| `POST /learn/round/answer` (mit `typed`) | `auth`, bestehend | eigene Runde |
| `POST /account/reminder` (Uhrzeit, aus) | `auth` | eigenes Konto |
| `POST /account/push-subscriptions` | `auth` | meldet das Gerät für das eigene Konto an |
| `POST /api/v1/reminders/run` | **öffentlich** mit `reminder.token` + Drossel | systemweit (alle Konten mit Erinnerung) |

Die Aufruf-URL ist eine **neue öffentliche Route**. Sie kommt bewusst in
`PUBLIC_ROUTES` des Routentests, mit Begründung, so wie `/api/v1/deploy`. Sie
gibt keine Personendaten heraus, nur Zahlen.

Ein Gerät gehört genau einem Konto. Meldet sich jemand auf demselben Gerät
mit einem anderen Konto an und schaltet die Erinnerung ein, wechselt der
`endpoint` das Konto. **Abmelden** löscht die Geräte dieses Browsers nicht
(die Anmeldung fehlt dann ja); deshalb verschickt der Sender nur an
**freigegebene, aktive** Konten.

## Datenmodell

**A:** Migration `2026_10_17_000000_add_type_answers_to_deck_preferences.php`

- `deck_preferences.type_answers` – `boolean`, Vorgabe `false`.

**B:** Migration `2026_10_17_100000_create_push_subscriptions_table.php`

- `push_subscriptions`: `id` (uuid), `user_id` (→ users, cascade), `endpoint`
  (text, unique), `public_key` (text, p256dh), `auth_token` (text),
  `created_at`/`updated_at` (tz).
- `learning_settings.reminder_time` – `time`, nullable (aus = null).
- `learning_settings.reminder_sent_on` – `date`, nullable.

Das Ergebnis einer getippten Antwort wird nicht in der Session gespeichert.
Es kommt als Antwort auf „Prüfen“ (htmx-Fragment). „Weiter“ lädt die aktuelle
Karte (`hx-get="/learn/round"` mit `hx-select="#round"`). Ein Neuladen zeigt
deshalb gleich die nächste Karte.

## Betroffene Stellen

**A:**
- `app/Services/AnswerCheckService.php` (neu): Vereinheitlichung, Vergleich
  mit allen Bedeutungen, Tippfehler-Abstand (mehrbyte-sicher),
  Akzent-Faltung, markierte Unterschiede (als Liste von Abschnitten, nicht
  als HTML).
- `DeckPreferenceService`: `typeAnswers()`, `setTypeAnswers()`.
- `StudyRoundService`: `current()` liefert `typed` je Stapel der Karte (nie
  bei Einführungen und beim Fach üben). `answerTyped()` prüft, wertet nach
  Tabelle A2 über `answer()` und gibt das Ergebnis zurück. Für das
  Tagesziel zählt nur „gewusst“ (Rückmeldung vom 17.10.2026).
- `StudyRoundController`, `DeckController::typing`, `routes/web.php`.
- `learn/partials/round-body.blade.php`: Eingabe- und Ergebnisansicht.
  `layouts/focus.blade.php`: Enter auf dem Ergebnis = Weiter.
- `decks/show.blade.php` (⋯-Menü), Hilfe.

**B:**
- `config/push.php`, `.env.example`, `composer.json`.
- `app/Services/ReminderService.php` (Geräte anmelden und abmelden,
  Uhrzeit, wer ist dran, Text, `reminder_sent_on`, Prüfung der
  Konfiguration), `PushSender` (Schnittstelle) und `WebPushSender`
  (`minishlink/web-push`, Guzzle mit Zeitlimit).
- `routes/console.php`: `reminders:send`, `push:keys`.
- `Dockerfile`: `bcmath` für schnellere Verschlüsselung.
- `app/Http/Controllers/AccountController` (Uhrzeit, Geräte),
  `Api/V1/ReminderController`, Middleware `RequireReminderToken`.
- `routes/web.php`, `routes/api.php`, `bootstrap/app.php` (Middleware-Alias).
- `pages/account.blade.php` (Abschnitt mit Inline-Skript für
  `Notification.requestPermission` und `pushManager.subscribe`),
  `pwa/service-worker.blade.php` (`push`, `notificationclick`; weiterhin
  **keine** Seiten im Cache).
- `admin/system.blade.php`, `DeployService` (Erweiterungen prüfen).
- `docs/INSTALL-*.md`, Hilfe („Erinnerung“).
- `tests/Feature/RouteProtectionTest.php`: `PUBLIC_ROUTES` + Begründung.

**Beide:** `CHANGELOG.md` mit `Migration:`-Zeilen. **B:**
`lang/de/validation.php` (`reminder_time`).

## Tests

**A – Prüfung (Unit, `AnswerCheckService`):**
- Groß/Klein, Leerraum, Satzzeichen am Ende → richtig.
- Jede Bedeutung zählt; „(to) miss“ mit „miss“ und „to miss“ → richtig.
- „recieve“/„receive“, „Haus“/„Hasu“ (vertauscht), „Fahrkarte“/„Farkarte“ →
  fast richtig; „cat“/„car“ → falsch; „Strassenbahn“/„Straßenbahn“,
  „cafe“/„café“ → fast richtig.
- Ab 9 Zeichen zwei Fehler fast richtig, drei falsch.
- Rechenkarten: „12“/„13“ → falsch, „ 12 “ → richtig.
- Markierte Abschnitte zeigen genau die abweichenden Zeichen;
  HTML-Sonderzeichen bleiben Text.

**A – Ablauf (Feature):**
- Schalter an → Runde zeigt Eingabe statt „Umdrehen“; aus → wie bisher.
  Gemischte Runde: je Stapel richtig.
- Lernen: fast richtig rückt ins nächste Fach, falsch → Fach 1;
  Test üben: fast richtig kommt wieder.
- „Weiß ich nicht“ und leere Eingabe = nicht gewusst.
- Doppeltes Prüfen wertet einmal; Neuladen auf dem Ergebnis geht weiter.
- Einführung neuer Wörter bleibt ohne Eingabe; „Fach üben“ ohne Eingabe.
- Schalter: 404 für fremde private / nicht gelernte Stapel.

**B – Erinnerung:**
- Gerät anmelden (gültiges JSON) speichert; ungültiges → 422; derselbe
  `endpoint` mit anderem Konto wechselt das Konto.
- Uhrzeit setzen/ausschalten; ungültige Uhrzeit → 422; Ausschalten löscht
  alle Geräte.
- Sender (Push-Versand gefälscht, keine echten Anfragen): Uhrzeit erreicht +
  Karten warten → eine Mitteilung je Gerät mit Kartenzahl, `reminder_sent_on`
  = heute; zweiter Lauf → nichts.
- Ziel erreicht / nichts wartet → nichts gesendet, Tag erledigt.
- Vor der Uhrzeit → nichts; Berliner Zeit statt UTC.
- Gesperrte/nicht freigegebene Konten → nichts.
- Antwort 410 → Gerät gelöscht.
- Aufruf-URL: ohne, mit falschem oder zu kurzem Token 404 (wie der Deploy-Hook),
  mit Token 200 und Zahlen; `reminders:send` gleichwertig.
- Ohne VAPID-Schlüssel kein Abschnitt im Konto, Sender sendet nichts.
- Routentest: neue öffentliche Route nur mit Eintrag.

## Annahmen und Risiken

- **Tippfehler-Grenzen** (1 Fehler ab 4 Zeichen, 2 ab 9) sind eine Annahme
  und stehen in `config/learning.php`.
- **Klammern statt Sprachregeln:** Kartenautoren müssen „(to) miss“ oder
  „(der) Hund“ schreiben, damit Varianten gelten. In der Hilfe unter
  „Mehrere Bedeutungen und Hinweise“ erklären.
- **Bildschirmtastatur:** Beim Eintippen schiebt die Tastatur die Karte
  nach oben. Die Frage muss oberhalb sichtbar bleiben; das wird auf einem
  echten Handy geprüft.
- **Datenfluss an Dritte:** Web-Push läuft über die Push-Dienste der
  Browser-Hersteller (Apple, Google, Mozilla). Übertragen werden nur der
  verschlüsselte Text (Kartenzahl, Serie) und die Geräteadresse. Das passiert
  nur nach ausdrücklichem Einschalten und gehört in die Hilfe. Die
  Produktseite bleibt ohne Dritte.
- **Lange Anfrage beim Senden:** Jede Mitteilung ist eine HTTP-Anfrage an
  einen Push-Dienst; `minishlink/web-push` v11 schickt sie nacheinander.
  Deshalb die Grenzen oben (100 Nachrichten, 40 s je Aufruf). Für eine
  Familie oder eine Klasse reicht das weit; bei Tausenden Konten bräuchte es
  einen parallelen Versand.
- **Ungetestet ohne Gerät:** Verschlüsselung und Antworten der Push-Dienste
  sind mit einem nachgestellten Push-Dienst getestet (Guzzle-MockHandler),
  nicht mit echten Diensten. Die erste echte Erinnerung auf einem Handy ist
  der eigentliche Test.
- **iOS:** Mitteilungen nur für installierte Web-Apps ab iOS 16.4. Ist die
  App nur im Safari-Tab offen, gibt es keine Erinnerung; der Hinweis sagt
  das.
- **Neue Abhängigkeit** `minishlink/web-push` (mit `web-token/jwt-*`,
  `guzzlehttp/guzzle`). Das ist bewusst in Kauf genommen, denn eine eigene
  Umsetzung der Verschlüsselung wäre fehleranfälliger.
