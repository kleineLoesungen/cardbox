# Eltern sehen den Fortschritt

Status: **umgesetzt** (18.10.2026)

Aus der Vorschlagsliste vom 16.10.2026, Punkt 10.

Entscheidungen (Rückfragen vom 18.10.2026):

- Verknüpft wird über einen **Code vom Kind**, ohne Admin.
- Eltern sehen **Lerntage und Tagesziel, Wortschatz, Stapel mit
  Fortschritt und knifflige Wörter**.
- Eltern **sehen nur**: kein Lob, keine Nachricht, keine Änderung.
- **Keine Benachrichtigung** der Eltern, erst einmal.

## Ziel

Ein Elternteil sieht im eigenen Konto, wie das Kind lernt: an welchen Tagen,
ob das Tagesziel geschafft ist, wie der Wortschatz wächst, welche Stapel
laufen und welche Wörter knifflig sind. So kann es zu Hause loben oder
gezielt mitüben. Das Kind entscheidet selbst, wer zuschaut, und sieht das
jederzeit.

## Nicht dabei

- Lob, Nachrichten oder Reaktionen in der App.
- Benachrichtigungen an Eltern (Push, Wochenbericht).
- Einzelne Antworten, Zeitpunkte einzelner Runden, Antworten im
  Schreibmodus.
- Etwas für das Kind ändern: Lernplan, Stapel, Karten, Einstellungen.
- Kinderkonten anlegen oder verwalten („Familienkonto“), Passwort für das
  Kind zurücksetzen. Das bleibt Sache des Admins.
- Eine eigene Rolle „Eltern“. Jedes Konto kann Elternteil und/oder Kind
  sein.
- Lehrkräfte mit Klassen. Technisch ginge es mit derselben Verknüpfung, die
  Oberfläche ist aber für wenige Kinder gedacht.

---

## Verhalten

### A. Verknüpfen (Kind → Elternteil)

**Beim Kind** unter **Mein Konto → „Eltern“**:

- Text: „Deine Eltern können sehen, an welchen Tagen du lernst, wie viele
  Wörter du kannst, welche Stapel du lernst und welche Wörter knifflig sind.
  Deine einzelnen Antworten sehen sie nicht.“
- Knopf **„Code für Eltern erzeugen“**. Dann erscheint groß ein Code, z. B.
  `K7P2-M9QX`, mit dem Hinweis „Gilt 24 Stunden und nur einmal.“
- Es gibt **höchstens einen gültigen Code** je Kind. Ein neuer ersetzt den
  alten.
- Liste der verknüpften Eltern: „**Anna** sieht deinen Fortschritt“ mit
  **„Nicht mehr zeigen“** und Rückfrage. Das beendet die Verknüpfung sofort.
  Höchstens 4 Eltern je Kind.

**Beim Elternteil** unter **Mein Konto → „Kind hinzufügen“**:

- Feld **„Code vom Kind“** und Knopf „Hinzufügen“. Leerzeichen,
  Bindestrich und Groß/Klein sind egal.
- Danach steht das Kind in der Liste „**Meine Kinder**“ mit Link zur Ansicht
  und **„Entfernen“**.
- Ein Elternteil kann mehrere Kinder haben (Geschwister), höchstens 10.

**Fehlerfälle** (immer dieselbe Meldung, damit nichts über fremde Konten
verraten wird): falscher, abgelaufener oder schon benutzter Code → „Dieser
Code passt nicht. Bitte das Kind um einen neuen.“ Der eigene Code →
„Das ist dein eigener Code.“ Schon verknüpft → „Ben ist schon in deiner
Liste.“ Zu viele → „Höchstens 4 Eltern je Kind.“ bzw. „Höchstens 10 Kinder.“
Codes eingeben ist gebremst (10 Versuche pro Stunde je Konto).

### B. Ansicht für Eltern

**Menü:** Hat ein Konto mindestens ein Kind, erscheint unter „Mehr“ der
Punkt **„Meine Kinder“** (`/children`). Bei genau einem Kind führt er direkt
zu dessen Ansicht.

**Ansicht eines Kindes** (`/children/{id}`), nur lesen:

1. **Kopf:** Name des Kindes, „🔥 5 Tage in Folge“, „Heute: 12 von 20
   gewusst“ (oder „Tagesziel geschafft“).
2. **Lerntage (4 Wochen):** Kalenderstreifen mit 28 Tagen, Wochen als
   Zeilen (Montag bis Sonntag, die laufende Woche unten; Tage nach heute
   gestrichelt als „noch nicht“, heute umrandet). Jeder Tag zeigt einen von drei Zuständen: **Ziel geschafft**
   (gefüllt, Erfolgsfarbe), **gelernt** (halb), **nicht gelernt** (leer).
   Darunter eine Legende. Barrierefrei: Jeder Tag hat einen Text („Mo 13.10.:
   Ziel geschafft“). Seit Issue #18 steht darüber „Jede Kachel ist ein Tag,
   jede Zeile eine Woche …“, und jede Kachel zeigt den Tag des Monats.
3. **Wortschatz:** „Kann schon **124 Wörter**, +5 diese Woche“ (wie auf
   „Heute“ des Kindes) und die **zuletzt** in „Kann ich“ gekommenen bis zu
   10 Wörter („Hund – dog“).
4. **Stapel:** die Stapel, die das Kind lernt (eigene und gefolgte, ohne
   pausierte), je mit Name, Sprachpaar, „3 fällig“ und einem dreiteiligen
   Balken (Issue #18): „kann Ben“, „im Karteikasten“, „noch nicht gelernt“,
   darunter die Anzahlen. Pausierte Stapel fehlen; ein Satz nennt nur, wie
   viele es sind ([aktive-stapel-und-freie-karten.md](aktive-stapel-und-freie-karten.md)).
   Kein Link in den Stapel: Eltern sehen keine Kartenlisten und keine fremden
   privaten Stapel-Seiten.
5. **Knifflige Wörter:** bis zu 20 Wörter („Hund – dog“, mit Stapelname),
   die das Kind oft falsch hat (Definition aus
   [runden-und-knifflige-woerter.md](runden-und-knifflige-woerter.md)).
   Darüber: „Zum Mitüben: Frag die Wörter einfach mal beim Abendessen ab.“
6. Unten: „Ben hat dir das freigegeben und kann es jederzeit beenden.“

**Leere Zustände:** Noch nichts gelernt → „Ben hat noch nicht gelernt.“
Keine kniffligen Wörter → Abschnitt fehlt.

**Wenn die Verknüpfung endet** (Kind beendet, Elternteil entfernt,
Kinderkonto gelöscht): Die Ansicht antwortet 404, das Kind
verschwindet aus der Liste. Bei **gesperrtem** Kinderkonto bleibt die
Verknüpfung bestehen, die Ansicht zeigt dann nur „Dieses Konto ist gerade
gesperrt.“

---

## Berechtigungen

Alles in der Gruppe `auth`, keine neue Rolle.

| Route | Wer | Prüfung im Service |
|---|---|---|
| `POST /account/guardian-code` (Code erzeugen) | Kind | eigenes Konto |
| `POST /account/guardians/{user}/delete` | Kind | nur eigene Verknüpfung, sonst 404 |
| `POST /account/children` (Code eingeben) | Elternteil | gebremst: `throttle:10,60,guardian-code` (eigener Zähler je Konto) |
| `POST /account/children/{user}/delete` | Elternteil | nur eigene Verknüpfung, sonst 404 |
| `GET /children`, `GET /children/{user}` | Elternteil | `GuardianService::getChild()` – nur verknüpfte Kinder, sonst **404** (nicht 403, damit IDs nichts verraten) |

Keine Rollenprüfung im Controller. Admins sehen **nicht** automatisch alle
Kinder; auch für sie gilt die Verknüpfung.

Die Ansicht liest Daten eines **anderen** Kontos. Das ist neu in Cardbox und
geht deshalb ausschließlich über `GuardianService`. Der prüft die
Verknüpfung und ruft dann die bestehenden Auswertungen
(`LearningOverviewService`, `LeitnerService`) für das Kind auf. Keine Stelle
gibt Kartenlisten, Antworten oder Einstellungen des Kindes heraus.

## Datenmodell

Migration `2026_10_18_100000_create_guardian_tables.php`:

- **`guardian_links`**: `guardian_id` (→ users, cascade), `child_id`
  (→ users, cascade), `created_at` (tz). Primärschlüssel
  `(guardian_id, child_id)`, Index auf `child_id`. Prüfung
  `guardian_id <> child_id`.
- **`guardian_codes`**: `child_id` (→ users, cascade, **Primärschlüssel**:
  höchstens ein Code je Kind), `code_hash` (HMAC-sha256 des normalisierten Codes mit `APP_KEY`,
  unique), `expires_at` (tz), `created_at` (tz). Der Code selbst wird nicht
  gespeichert. Abgelaufene Codes werden beim Erzeugen und Einlösen
  aufgeräumt; einen Scheduler braucht es nicht.

Code-Format: 8 Zeichen aus `23456789ABCDEFGHJKMNPQRSTUVWXYZ` (ohne
verwechselbare 0/O, 1/I/L), angezeigt als `XXXX-XXXX`. Das sind etwa 10¹²
Möglichkeiten. Mit der Bremse ist Raten aussichtslos.

## Betroffene Stellen

- **app/Services/**: neu `GuardianService`:
  - `createCode`, `redeem`;
  - `guardiansOf`, `childrenOf`, `unlinkAsChild`, `unlinkAsGuardian`;
  - `getChild` (404, wenn nicht verknüpft);
  - `overview(child)`: Kalender, Serie, heute, Wortschatz, zuletzt
    gewusste Wörter, Stapel, knifflige Wörter.
  - In `LearningOverviewService` neu `recentlyMastered(User, int)` und
    `hardWords(User, int)` über alle Stapel des Kindes. Bestehende
    Methoden werden mit dem Kind als Person aufgerufen.
- **app/Http/Controllers/**: `AccountController` (Code, Eltern, Kinder),
  neu `ChildController` (`index`, `show`).
- **routes/web.php**: Routen aus der Tabelle.
- **resources/views/**:
  - `pages/account.blade.php`: Abschnitte „Eltern“ und „Kind hinzufügen“;
  - neu `children/index.blade.php` und `children/show.blade.php`;
  - `components/nav-links.blade.php`: „Meine Kinder“, nur mit Kindern;
  - Hilfe-Abschnitt „Eltern“.
- **resources/css/components.css**: `.day-strip` für den Kalender (Tokens).
- **lang/de/validation.php**: Attribut `code`.
- **CHANGELOG.md**, Produktseite (ein Satz bei „Motiviert, ohne Punkte“).

## Tests

- **Code:**
  - Erzeugen zeigt einen Code; ein zweiter ersetzt den ersten.
  - Einlösen verknüpft. Danach ist der Code verbraucht.
  - Nach 24 h ungültig (`travelTo`).
  - Groß/Klein, Leerzeichen und Bindestrich sind egal.
- **Fehler:**
  - Falscher, abgelaufener oder benutzter Code ergibt dieselbe Meldung.
  - Eigener Code und doppelte Verknüpfung haben eigene Meldungen.
  - Grenzen 4 Eltern / 10 Kinder.
  - Bremse nach 10 Versuchen.
- **Trennung:**
  - Ansicht fremder, nicht verknüpfter Kinder ergibt 404, auch für Admins.
  - Ungültige ID ergibt 404 statt 500.
  - Nach „Nicht mehr zeigen“ beziehungsweise „Entfernen“ ergibt sie sofort
    404.
  - Ein gelöschtes Kinderkonto entfernt die Verknüpfung.
- **Ansicht:**
  - Kalender mit drei Zuständen, Tag in Berliner Zeit.
  - Serie und „heute“.
  - Wortschatz und zuletzt gewusste Wörter.
  - Stapel mit Anteil, auch ein gefolgter.
  - Knifflige Wörter.
  - Keine Kartenlisten, keine Links in die Stapel des Kindes.
- **Gesperrtes Kind:** Hinweis statt Daten.
- **Menü:** „Meine Kinder“ nur mit Kindern; bei einem Kind direkt dessen
  Ansicht.

## Annahmen und Risiken

- **Einwilligung:** Der Code ist die Zustimmung des Kindes. Für ein
  11-Jähriges ist das gut verständlich („Ich gebe Mama meinen Code“). Das
  Kind sieht dauerhaft, wer zuschaut, und kann es beenden. Ob Eltern das
  Beenden verhindern können sollen, ist bewusst **nicht** vorgesehen.
- **Private Stapel des Kindes:** Eltern sehen deren Namen und knifflige
  Wörter daraus. Darüber informiert der Text beim Erzeugen des Codes.
- **Datenschutz:** Neue Verarbeitung personenbezogener Lerndaten durch
  Dritte im selben System, nur nach aktiver Freigabe durch das Kind. Kein
  Export, keine Weitergabe nach außen.
- **Kalender** nutzt `study_days` (Ziel geschafft = `goal_met`, gelernt =
  `answers > 0` oder `reviews > 0`). Tage vor dem Tagesziel-Update zählen
  als „Ziel geschafft“, weil die Migration sie so gesetzt hat.
- **Leistung:** Die Ansicht rechnet je Aufruf einige Abfragen für ein Kind.
  Das ist unkritisch.
- **Wortwahl:** „Eltern“ und „Kinder“ in der Oberfläche. Für Lehrkräfte
  passt das nicht ganz; das kann später neutraler werden („Wer sieht meinen
  Fortschritt“).
