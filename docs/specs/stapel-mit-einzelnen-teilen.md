# Stapel mit einzelnen Personen teilen

## Ziel

Ein Stapel ist künftig **privat**, **mit bestimmten Personen geteilt** oder
**mit allen geteilt**. Wer einen Stapel angelegt hat, gibt ihn einzelnen
Konten per Benutzername frei – etwa der eigenen Klasse oder den eigenen
Kindern –, ohne ihn für alle sichtbar zu machen. Wer so einen Stapel
bekommt, kann ihn lernen oder ablehnen.

Entscheidungen vom 21.11.2026:

- Personen werden **per Benutzername** hinzugefügt.
- Wer einen Stapel bekommt, kann ihn **ablehnen**.
- Die Personen dürfen dasselbe wie bei „mit allen geteilt“: ansehen,
  lernen, kopieren, Fehler melden.
- Wird der Zugriff entzogen oder abgelehnt, **bleibt der Lernstand
  erhalten** – wie beim „Nicht mehr lernen“ öffentlicher Stapel. Teilt man
  wieder, ist er noch da (geändert am 21.11.2026; zuerst war Löschen
  vorgesehen).

## Nicht dabei

- Gemeinsam bearbeiten: Nur wer den Stapel angelegt hat, ändert Karten.
- Gruppen oder Klassen als eigene Einheit; geteilt wird Person für Person.
- Einladungslinks oder -codes, Teilen mit Gästen ohne Konto.
- Benachrichtigungen per Push oder Mail über neue Freigaben (das Konto hat
  keine Mail; die App zeigt sie beim nächsten Öffnen).
- Moderation einzeln geteilter Stapel: Moderation gilt weiter nur für
  Stapel, die mit allen geteilt sind – auch wenn ein Moderator selbst auf der
  Liste steht, bekommt er dort keine Moderationsrechte
  (`DeckService::ownOrPublic` statt `visibleTo` für die `…Moderated`-Fassungen).
  Beendet die Moderation das Teilen eines öffentlichen Stapels, endet auch das
  Teilen mit Einzelnen.
- JSON-API (`/api/v1`): unverändert.

## Verhalten

### Für den Ersteller: Teilen

- Im „⋯“-Menü des Stapels heißt der Eintrag **„Teilen“** (mit Plakette
  „privat“, „mit 3“ oder „mit allen“; bisher „Mit allen teilen“ / „Nicht
  mehr teilen“). Er öffnet ein Blatt:
  - ein Satz zum Stand („Privat: nur du siehst den Stapel.“ / „Nur die
    Personen unten sehen den Stapel.“ / „Mit allen geteilt …“);
  - **„Mit allen teilen“** bzw. **„Nicht mehr mit allen teilen“**;
  - **„Mit bestimmten Personen“**: die Liste und darunter das Feld
    „Benutzername“ mit **„Hinzufügen“**;
  - **„Privat machen“**, sobald geteilt ist.
- **Person hinzufügen:** Danach steht die Person mit Anzeigenamen und
  Benutzername in der Liste („Mia (mia) – noch nicht angenommen“ bzw. „lernt
  mit“), das Blatt bleibt offen. Meldungen:
  - unbekannter, gesperrter oder nicht freigegebener Benutzername: „Diesen
    Benutzernamen gibt es nicht.“ (eine Meldung für alle drei Fälle);
  - der eigene: „Das bist du selbst.“;
  - schon in der Liste: „Mit Mia ist der Stapel schon geteilt.“;
  - mehr als 50 Personen: „Mehr als 50 Personen geht nicht – teil den
    Stapel lieber mit allen.“
- **Person entfernen:** „Entfernen“ neben dem Namen, mit Rückfrage: „Mia
  verliert den Stapel. Teilst du ihn wieder, ist der Lernstand noch da.“
- **„Privat machen“** mit Personen in der Liste: Rückfrage „3 Personen
  verlieren den Stapel …“. Danach ist die Liste leer.
- **„Mit allen teilen“:** Die Liste bleibt gespeichert, spielt aber keine
  Rolle, solange der Stapel mit allen geteilt ist (Hinweis im Blatt).
  **„Nicht mehr mit allen teilen“** lässt die Liste wieder gelten; wer nicht
  darin steht, verliert den Zugriff wie heute beim „Nicht mehr teilen“.
- Kopf der Stapelseite: Plakette „privat“, „geteilt mit 3 Personen“ (dazu
  „2 lernen mit“, wenn es welche gibt) oder „geteilt“ (alle). In der
  Stapelliste trägt ein eigener Stapel „geteilt mit 3“.

### Für die Person, die den Stapel bekommt

- Unter **Stapel → Meine** steht oben ein Abschnitt **„Mit dir geteilt“**
  mit neuen Freigaben, je mit „von Lena M.“ und zwei Knöpfen:
  **„Lernen“** (Hauptaktion) und **„Ablehnen“**.
  - „Lernen“ wirkt wie heute bei einem geteilten Stapel: Er wird
    „Lerne ich“ und kommt unter „Heute“.
  - „Ablehnen“ fragt nach („Der Stapel verschwindet aus deiner Liste.“) und
    entfernt die Freigabe.
- Der Reiter „Stapel“ in der unteren Leiste trägt einen kleinen Zähler,
  solange neue Freigaben warten (wie wartende Registrierungen bei Admins).
- Ein angenommener Stapel verhält sich wie ein gefolgter geteilter Stapel:
  pausieren, wieder lernen, kopieren, Fehler melden, Wörterbuch, Duell.
- Auf der Stapelseite eines einzeln geteilten Stapels steht „von Lena · mit
  dir geteilt“ und im „⋯“-Menü zusätzlich **„Stapel entfernen“** (= später
  ablehnen): Rückfrage „… Teilt Lena ihn wieder mit dir, ist dein Lernstand
  noch da.“ „Nicht mehr lernen“ dagegen lässt den Stapel wieder unter „Mit
  dir geteilt“ erscheinen.
- Im **Wörterbuch** und bei **„Aus anderen Stapeln übernehmen“** zählen
  einzeln geteilte Stapel wie geteilte – für die Personen in der Liste.
- **Ablehnen sperrt:** Wer ablehnt (oder „Stapel entfernen“ tippt), kann vom
  Ersteller für diesen Stapel nicht erneut hinzugefügt werden („Mia hat den
  Stapel abgelehnt.“). So lässt sich niemandem – gerade Kindern – derselbe
  Stapel immer wieder zuschieben. Vom Ersteller entfernte Personen dagegen
  kann er neu hinzufügen.

### Lernstand

Entfernen, Ablehnen und „Privat machen“ nehmen die Person nur von der Liste
und beenden ihr Folgen (`deck_follows`). Fortschritt, Durchgänge,
Einstellungen und Kartenauswahl bleiben – wie beim „Nicht mehr lernen“ – und
gelten wieder, sobald der Stapel erneut geteilt und gelernt wird.

## Berechtigungen

- Teilen-Blatt, Hinzufügen, Entfernen: Gruppe `auth`; im Service nur über
  `DeckService::getOwned` – fremde Stapel ergeben 404.
- Annehmen, Ablehnen, „Stapel entfernen“: `auth`; im Service nur eigene
  Freigaben (`deck_shares.user_id` = angemeldete Person), sonst 404.
- **Sichtbarkeit** an einer Stelle: `DeckService::visibleTo` wird zu
  „eigen ODER mit allen geteilt ODER mit mir geteilt“. Alle Stellen, die
  heute `is_shared` als Zugriffsregel benutzen (z. B. `mineQuery`,
  „Fehler melden“, Übernehmen, Kopieren), gehen über diese eine Regel.
  `is_shared` bleibt für „Entdecken“, Moderation, Zusammenführen doppelter
  Karten und die Verwaltung – dort ist „öffentlich“ gemeint.
- Hinzufügen ist gebremst: 30 Versuche pro Stunde je Konto
  (`throttle:30,60,deck-share`), damit sich Benutzernamen nicht durchprobieren
  lassen.
- Keine neue öffentliche Route.

## Datenmodell

Neue Tabelle `deck_shares`, Migration
`2026_11_21_000000_create_deck_shares_table.php`:

| Spalte | Typ | |
|---|---|---|
| `deck_id` | uuid | Fremdschlüssel `decks`, `cascadeOnDelete` |
| `user_id` | uuid | Fremdschlüssel `users`, `cascadeOnDelete` – die Person, mit der geteilt ist |
| `created_at` | timestampTz | |

| `declined_at` | timestampTz, nullable | abgelehnt: die Zeile bleibt als Sperre und zählt sonst nirgends |

Primärschlüssel (`deck_id`, `user_id`), Index auf `user_id`. „Angenommen“
ergibt sich aus `deck_follows`; eine eigene Spalte braucht es nicht.

`decks.is_shared` bleibt (= „mit allen“). Der Zustand „mit bestimmten
Personen“ ist: nicht `is_shared` und mindestens eine Zeile in `deck_shares`.

## Betroffene Stellen

- **app/Services/:**
  - `DeckService`: `visibleTo`, `mineQuery` erweitern; `setVisibility`
    (privat / Personen / alle), `sharesOwned`, `shareWith`, `unshareWith`,
    `pendingShares`, `pendingShareCount`, `isSharedWith`, `decline`;
    `transfer` nimmt die neue Besitzerin von der Liste.
  - „Fehler melden“, Übernehmen, Kopieren und Wörterbuch gingen schon über
    `visibleTo`/`getVisible`; die Ansichten nutzen `is_shared` nicht mehr als
    Zugriffsregel (Fehler melden, Rückfragen beim Löschen).
- **app/Http/Controllers/:** `DeckController::visibility`, `shareWith`,
  `unshareWith`, `decline` (ersetzt `share`); „Lernen“ ist das bisherige
  `follow`.
- **app/Models/Deck.php:** Beziehung `sharedWith`, `shares_count` in den
  Listen.
- **app/Providers/AppServiceProvider.php:** `pendingShareCount` für den
  Zähler.
- **routes/web.php:** neue Routen in der `auth`-Gruppe, `whereUuid`.
- **resources/views/:** Teilen-Blatt in `decks/show`, Abschnitt „Mit dir
  geteilt“ in `decks/index`, Plaketten in `decks/show` und
  `components/deck-row`, „Stapel entfernen“ im Menü, Zähler in der unteren
  Leiste (`layouts/app.blade.php`), Hilfe.
- **Doku:** diese Datei, `docs/specs/mcp-schnittstelle.md` (Assistenten
  sehen mit mir geteilte Stapel wie geteilte; Teilen bleibt dort draußen),
  CHANGELOG mit Migration, Produktseite gegenlesen („privat oder geteilt“).

## Tests

- Sichtbarkeit: Person in der Liste sieht und lernt den Stapel; andere nicht
  (Stapelseite, Wörterbuch, Übernehmen, Kopieren, Fehler melden → 404).
- Hinzufügen: unbekannt / gesperrt / nicht freigegeben → dieselbe Meldung;
  sich selbst; doppelt; 51. Person; Bremse greift; fremder Stapel → 404.
- Annehmen → „Lerne ich“ und unter „Heute“; Ablehnen → weg.
- Ablehnen, Entfernen und „Privat machen“: Zugriff weg, Lernstand bleibt;
  erneutes Teilen und „Lernen“ bringen ihn zurück.
- Personen → alle → Personen: Liste bleibt, wer nur über „Entdecken“ kam,
  ist wieder draußen.
- Ungültige Sichtbarkeit wird abgewiesen; fremde Stapel → 404; Bremse an der
  Route; Übertragen nimmt die neue Besitzerin von der Liste.
- Zähler in der unteren Leiste und Abschnitt „Mit dir geteilt“.
- Moderation und „Entdecken“ zeigen einzeln geteilte Stapel nicht.

## Annahmen und Risiken

- **Benutzernamen erraten:** Wer hinzufügt, erfährt, ob es einen
  Benutzernamen gibt. Abhilfe: dieselbe Meldung für unbekannt, gesperrt und
  nicht freigegeben, dazu die Bremse. Der Anzeigename erscheint erst nach dem
  Hinzufügen – das ist gewollt, damit man sieht, ob es die richtige Person
  ist.
- **Ungefragte Freigaben an Kinder:** Jeder, der einen Benutzernamen kennt,
  kann einen Stapel zuschieben. Er landet aber nur unter „Mit dir geteilt“
  und nicht unter „Heute“, bis man „Lernen“ tippt, und lässt sich ablehnen –
  das sperrt denselben Stapel für diese Person. Für Missbrauch (anstößige
  Inhalte) gibt es in einzeln geteilten Stapeln keine Moderation und keinen
  Meldeweg; bei Bedarf können Admins das Konto sperren. Offen: Freigaben
  eines bestimmten Kontos ganz sperren, oder „Melden“ an die Moderation.
- **„Lernen mit“** zählt nur Personen, die den Stapel noch sehen (Rückfragen
  beim Löschen ebenso).
- **Lernstand bleibt** bei Entfernen und Ablehnen, wie bei öffentlichen
  Stapeln – kein Unterschied zwischen den beiden Arten zu teilen.
- **Obergrenze 50 Personen** je Stapel: Annahme; darüber ist „mit allen“
  gedacht.
- **Elternfunktion:** „Aus ‚Meine Kinder‘ wählen“ ist nicht dabei; Eltern
  geben den Benutzernamen ihres Kindes ein.
- **MCP-Schnittstelle:** Assistenten lesen mit mir geteilte Stapel wie
  geteilte, ändern dürfen sie wie bisher nur eigene. Die Teilen-Funktionen
  selbst bekommt der Assistent nicht.
