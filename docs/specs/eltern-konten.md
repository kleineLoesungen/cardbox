# Eltern-Konten: Alter, feste Zuordnung, Teilen nur durch Erwachsene

Status: **umgesetzt** (21.11.2026, Version 2026.11.21.2).

Ändert [eltern-ansicht.md](eltern-ansicht.md) (Verknüpfung per Code,
Eltern sehen nur zu) und [stapel-mit-einzelnen-teilen.md](stapel-mit-einzelnen-teilen.md)
(wer teilen darf).

Entscheidungen vom 21.11.2026:

- **Alter setzt der Admin:** Jedes Konto ist **unter 18** oder **ab 18**.
  Das legt der Admin beim Anlegen oder bei der Freigabe einer Registrierung
  fest; nur er ändert es.
- **Vorhandene Konten** gelten zunächst als unter 18 (Ausnahme: Admins und
  Moderatoren, siehe Annahmen). Der Admin stellt Erwachsene um.
- **Eltern ordnet der Admin zu**, beim Anlegen bzw. bei der Freigabe eines
  Kontos unter 18. Die Zuordnung ist fest: Das Kind kann sie nicht lösen.
  Der Code vom Kind entfällt.
- **Eltern bearbeiten die Stapel ihrer Kinder wie die Kinder selbst** und
  können mit dem Kind geteilte Stapel ablehnen oder entfernen. Den Lernstand,
  das Lernen selbst, Passwort und Einstellungen des Kindes ändern sie nicht.
- **Teilen dürfen nur Erwachsene:** ihre eigenen Stapel und – als Eltern –
  die Stapel ihrer Kinder, öffentlich oder mit ausgewählten Konten wie heute.
  Konten unter 18 teilen nicht.
- **Schon Geteiltes** von Konten unter 18 bleibt geteilt, bis ein Elternteil
  es einmal durchgesehen hat.
- **Duelle** bleiben, wie sie sind: Sie zeigen Karten nur kurz und sind
  danach nicht mehr einsehbar.

Grund: Urheberrecht und Lizenzen – wer Inhalte weitergibt, soll dafür
geradestehen können.

## Ziel

Kinder lernen und bauen Stapel wie bisher, geben sie aber nicht selbst
weiter. Ihre Eltern sind fest zugeordnet, sehen und pflegen die Stapel ihrer
Kinder und entscheiden, was geteilt wird und was das Kind von anderen
bekommt.

## Nicht dabei

- Altersnachweis oder Geburtsdatum: nur „unter 18“ / „ab 18“, vom Admin
  gesetzt.
- Eltern legen Kinderkonten selbst an oder setzen Passwörter zurück – das
  bleibt beim Admin.
- Automatisches Umstellen mit 18 (kein Geburtsdatum).
- Eltern lernen für das Kind, ändern seinen Lernstand, Lernplan, Tagesziel,
  Erinnerungen oder sein Passwort.
- Lehrkräfte mit Klassen als eigene Rolle.
- Änderungen an Duellen.
- Die MCP-Schnittstelle (eigene Spezifikation; dort ist offen, ob Konten
  unter 18 einen Assistenten verbinden dürfen).

## Verhalten

### A. Konten anlegen und freigeben (Admin)

- **„Benutzer anlegen“** bekommt die Wahl **„unter 18“ / „ab 18“** (ohne
  Vorgabe, Pflichtfeld) und, bei „unter 18“, das Feld **„Eltern“**:
  Benutzername eines Kontos ab 18, mindestens eines, höchstens vier.
- **Freigabe einer Registrierung:** „Freigeben“ öffnet dasselbe: Alter wählen
  und bei unter 18 Eltern eintragen. Ohne Elternteil kann ein Konto unter 18
  nicht freigegeben werden („Für ein Konto unter 18 braucht es mindestens
  ein Elternteil.“).
- Als Eltern zulässig sind nur freigegebene, aktive Konten **ab 18**.
  Unbekannte, gesperrte oder minderjährige Konten: „Kein freigegebenes Konto
  ab 18 mit diesem Benutzernamen.“
- Das allererste Konto der Installation wird wie bisher Admin – und **ab
  18**.

### B. Konto verwalten (Admin, `/admin/users/{id}`)

- Anzeige „unter 18“ / „ab 18“ und **„Ändern“**:
  - **unter 18 → ab 18:** Die Zuordnung zu den Eltern endet (Rückfrage:
    „Anna und Ben können Mias Stapel danach nicht mehr bearbeiten.“).
  - **ab 18 → unter 18:** nur, wenn das Konto selbst keine Kinder hat, und
    nur mit mindestens einem Elternteil.
- **Eltern** (bei unter 18): Liste mit „Entfernen“ und Feld „Elternteil
  hinzufügen“; das letzte Elternteil lässt sich nicht entfernen.
- **Kinder** (bei ab 18): Liste mit Link auf das jeweilige Kind.
- In der Benutzerliste eine Plakette „unter 18“ und ein Hinweis bei Konten
  unter 18 ohne Elternteil („Elternteil fehlt“) – das betrifft nur vorhandene
  Konten nach der Umstellung.

### C. Für Kinder (unter 18)

- **Mein Konto:** Statt „Code für Eltern erzeugen“ steht „Deine Eltern:
  Anna, Ben“ ohne Knopf zum Entfernen; dazu: „Deine Eltern können deine
  Stapel ansehen und bearbeiten. Deinen Lernstand und deine Antworten sehen
  sie nicht.“ (Was Eltern sehen, wie bisher in eltern-ansicht.md.)
- **Teilen** fehlt im „⋯“-Menü. Stattdessen im Menü als Hinweis: „Teilen
  können deine Eltern.“ (auch ohne eingetragene Eltern; dann steht unter
  Mein Konto, dass die Verwaltung sie einträgt).
- Beim Teilen steht für Erwachsene und Eltern ganz oben der Hinweis, nur zu
  teilen, was man weitergeben darf – mit Blick auf **Urheber- und
  Lizenzrechte** (Vokabellisten aus Schulbüchern, Arbeitsblätter).
- Alles andere bleibt: Stapel anlegen, bearbeiten, kopieren, lernen, mit
  ihnen geteilte Stapel annehmen oder ablehnen, Fehler melden, Duelle.

### D. Für Eltern

- **Meine Kinder → Kind:** Die bisherige Übersicht (Lerntage, Wortschatz,
  Stapel mit Fortschritt, knifflige Wörter) bleibt. Neu:
  - **Stapel des Kindes:** Jeder eigene Stapel des Kindes ist ein Link auf die
    Stapelseite. Dort arbeiten Eltern wie das Kind selbst: Karten anlegen,
    ändern, löschen, Liste einfügen, umbenennen, Sprachen ändern, Stapel
    löschen (mit Rückfrage), **teilen**. Oben steht „Stapel von Mia – du
    bearbeitest ihn als Elternteil.“ Lernen, Fächer und Lernplan zeigen dort
    nichts vom Kind und lassen sich nicht bedienen.
  - **„Neuer Stapel für Mia“:** legt einen Stapel im Konto des Kindes an.
  - **Mit Mia geteilt:** Stapel, die andere mit dem Kind geteilt haben
    (wartende und angenommene), je mit „von Lena“ und **„Ablehnen“** bzw.
    **„Entfernen“** – wirkt wie beim Kind selbst (sperrt erneutes
    Zuschieben, Lernstand bleibt). Öffentlich geteilte Stapel, die das Kind
    lernt, stehen dort auch; „Entfernen“ beendet das Mitlernen (wie „Nicht
    mehr lernen“).
  - **Bitte durchsehen** (einmalig, siehe F).
- Kinder hinzufügen oder entfernen können Eltern nicht mehr selbst; der
  Hinweis verweist auf den Admin.

### E. Teilen

- `setVisibility`, `shareWith`, `unshareWith` erlauben:
  - der Besitzerin, wenn sie **ab 18** ist;
  - einem **Elternteil** der Besitzerin (Konto unter 18).
- Sonst: „Teilen dürfen nur Erwachsene – bei Kindern ihre Eltern.“ – und
  die Knöpfe fehlen.
- **Weniger teilen darf jede Besitzerin**, auch unter 18: privat machen,
  „Nicht mehr mit allen teilen“ und Personen herausnehmen. Das Teilen-Fenster
  zeigt Kindern dann nur diese Knöpfe („Weitergeben können deine Eltern.
  Zurücknehmen kannst du es selbst.“). Privat machen erledigt auch „bitte
  durchsehen“.
- „So lassen“ / „Privat machen“ aus F nur durch Eltern des Kindes (bzw. den
  Admin), nur für markierte Stapel dieses Kindes.
- Teilen sonst wie in stapel-mit-einzelnen-teilen.md: öffentlich oder per
  Benutzername.
- Wird ein Stapel eines Kindes von einem Elternteil geteilt, steht bei den
  Empfängern weiter „von Mia“ (Besitzerin). Wer geteilt hat, wird in
  `deck_shares.shared_by` festgehalten (noch ohne Anzeige).

### F. Was schon geteilt ist

- Bei der Umstellung bleiben alle Freigaben bestehen. Stapel von Konten
  unter 18, die öffentlich oder mit Einzelnen geteilt sind, werden als
  **„bitte durchsehen“** markiert.
- Eltern sehen beim Kind einen Kasten „Mia hat diese Stapel geteilt – bitte
  einmal durchsehen“ mit je **„So lassen“** und **„Privat machen“**. Danach
  verschwindet die Markierung.
- Hat das Kind kein Elternteil, sieht der Admin die Markierung in der
  Benutzerverwaltung und entscheidet dort.
- Stellt der Admin ein Konto auf „ab 18“, entfällt die Markierung: Das Konto
  entscheidet dann selbst. Stellt er ein Konto auf „unter 18“, werden seine
  geteilten Stapel markiert.

## Berechtigungen

- Alter, Eltern-Zuordnung: Gruppe `role:admin`, unter `/admin/users`.
- Eltern-Aktionen auf Stapeln des Kindes: Gruppe `auth`; die Prüfung steht im
  Service:
  - neue Fassung **`DeckService::getManaged`** = eigener Stapel **oder**
    Stapel eines eigenen Kindes (über `guardian_links`); fremde ergeben 404.
  - Alle Bearbeitungs-Routen (Stapel ändern, löschen, Karten, Import, Teilen)
    nutzen `getManaged` statt `getOwned`. Lernen, Lernstand, Kartenauswahl,
    Pausieren, Duell anlegen bleiben bei `getOwned`/`getVisible` – also nur
    für das Kind.
  - Ablehnen/Entfernen für das Kind: `DeckService::declineForChild(guardian,
    child, deck)`, nur bei eigenen Kindern.
- Das Kind kann die Zuordnung nicht lösen: `unlinkAsChild` und
  `unlinkAsGuardian` entfallen samt Routen; nur der Admin ändert sie.
- **Datentrennung:** Eltern sehen weiter keine einzelnen Antworten, nur die
  Stapel und Karten (wie das Kind) und die Übersicht.

## Datenmodell

Migration `2026_11_21_100000_add_adult_and_review_to_users_and_decks.php`:

| Tabelle | Spalte | |
|---|---|---|
| `users` | `is_adult` boolean, default false | „ab 18“; bei der Migration `true` für Admins und Moderatoren |
| `decks` | `share_review_pending` boolean, default false | „bitte durchsehen“; bei der Migration `true` für geteilte Stapel von Konten unter 18 |
| `deck_shares` | `shared_by` uuid, nullable, Fremdschlüssel `users`, `nullOnDelete` | wer die Person hinzugefügt hat (Besitzerin oder Elternteil) |
| `guardian_links` | `linked_by` uuid, nullable, Fremdschlüssel `users`, `nullOnDelete` | welcher Admin zugeordnet hat |

- `guardian_codes` wird nicht mehr gebraucht und in derselben Migration
  gelöscht (kein Rückweg nötig; `down()` legt sie leer wieder an).
- `guardian_links.confirmed_at` (timestamptz, nullable): wann ein Admin die
  Verknüpfung eingerichtet oder bestätigt hat.
- Vorhandene `guardian_links` (aus der Zeit der Codes) bleiben, aber **ohne
  `confirmed_at`**: Das Kind hatte nur dem Zuschauen zugestimmt, nicht dem
  Bearbeiten. Solche Eltern sehen weiter die Übersicht; bearbeiten und
  teilen dürfen sie erst, wenn der Admin die Verknüpfung beim Kind mit
  **„Bestätigen“** freigibt (Admin-Seite: Plakette „nur zuschauen“). Bis
  dahin zählt das Kind als „Elternteil fehlt“.
- Verknüpfungen, bei denen das Kind nach der Umstellung ab 18 ist (Admins,
  Moderatoren), werden gelöscht.
- Handeln darf ein Elternteil nur, wenn es ab 18 ist, das Kind unter 18 und
  die Verknüpfung bestätigt (`GuardianService::managedChildIds`; alle
  Prüfungen gehen darüber).

## Betroffene Stellen

- **app/Services/UserService.php:** `create`/`approve` mit Alter und Eltern;
  `setAge`, `addGuardian`, `removeGuardian`, `minorsWithoutGuardian`;
  Validierung „mindestens ein Elternteil ab 18“.
- **app/Services/GuardianService.php:** Code-Teil entfällt; `isGuardianOf`
  (verlangt ein Elternteil ab 18).
- **app/Services/DeckService.php:** `getManaged`, `managesAsGuardian`,
  `canShare`, Prüfung in `setVisibility`/`shareWith`/`unshareWith`;
  `childDecks`, `sharedWithChild`, `declineForChild`, `createForChild`
  (Stapel für ein Kind), `reviewPending`, `review`, `reviewAsAdmin`.
- **app/Services/CardService.php, CardImportService.php:** Bearbeiten über
  `getManaged`.
- **app/Http/Controllers/:** `Admin/UserController` (Alter, Eltern),
  `ChildController` (Stapel, geteilt, durchsehen, neuer Stapel),
  `AccountController` (Code-Aktionen weg), `DeckController`/`CardController`
  (Elternmodus).
- **routes/web.php:** Code-Routen weg, neue Routen unter `/children/{user}/…`
  (`auth`, Prüfung im Service) und `/admin/users/{user}/…` (`role:admin`).
- **resources/views/:** Admin-Formulare (anlegen, freigeben, Konto), Mein
  Konto (Eltern-Abschnitt), `children/show`, Stapelseite (Elternmodus,
  Teilen ausgeblendet für Kinder), Hilfe.
- **Doku:** eltern-ansicht.md und stapel-mit-einzelnen-teilen.md nachziehen,
  CLAUDE.md (Abschnitt Registrierung: Alter und Eltern bei der Freigabe),
  CHANGELOG mit Migration, Produktseite gegenlesen.

## Tests

- **Anlegen/Freigeben:** Alter Pflicht; unter 18 ohne Eltern abgewiesen;
  Eltern müssen ab 18, freigegeben, aktiv sein; höchstens vier; erstes Konto
  ab 18.
- **Umstellen:** unter 18 → ab 18 löst Verknüpfungen; ab 18 → unter 18 nur
  ohne eigene Kinder und mit Elternteil; letztes Elternteil nicht entfernbar.
- **Kind kann nicht lösen:** Routen weg, alte Code-Routen 404.
- **Eltern bearbeiten:** Karten, Stapel, Import, Löschen beim eigenen Kind
  erlaubt; bei fremdem Kind und nicht verknüpftem Konto 404; Lernstand,
  Pausieren, Lernen, Kartenauswahl des Kindes nicht erreichbar; Elternteil
  unter 18 darf nichts.
- **Teilen:** Kind unter 18 → abgewiesen und Knöpfe fehlen; ab 18 eigene
  Stapel ja; Elternteil für Stapel des Kindes ja; Elternteil für fremdes Kind
  nein; `shared_by` gesetzt.
- **Mit dem Kind geteilt:** Eltern lehnen ab bzw. entfernen; Sperre wirkt wie
  beim Kind.
- **Durchsehen:** Migration markiert genau die geteilten Stapel von Konten
  unter 18; „So lassen“ / „Privat machen“; ohne Eltern beim Admin sichtbar.
- **Duelle:** unverändert, auch für Kinder.

## Annahmen und Risiken

- **Admins und Moderatoren gelten bei der Umstellung als ab 18** – sonst
  könnte niemand mehr teilen oder Eltern zuordnen. Alle anderen vorhandenen
  Konten gelten als unter 18, bis der Admin sie umstellt; bis dahin teilen
  sie nichts Neues (Bestehendes bleibt bis zur Durchsicht).
- **`…Owned` bleibt Besitzer-only.** Für Eltern gibt es eigene Fassungen
  `getManaged`/`updateManaged`/`deleteManaged` (Stapel und Karten). Änderungen
  im Original übernehmen, „Aus anderen Stapeln übernehmen“, Vorschläge
  annehmen, Export und Kopie bleiben beim Kind.
- **Eltern dürfen Stapel des Kindes löschen** („bearbeiten wie das Kind
  selbst“), mit Rückfrage, die das Kind nennt.
- **Lernstand des Elternteils an Kinderstapeln:** Eltern lernen die Stapel
  ihrer Kinder nicht über die Stapelseite; wer mitlernen will, legt eine
  Kopie an. So vermischen sich Lernstände nicht.
- **Nachweis:** „ab 18“ beruht auf der Angabe gegenüber dem Admin; ob das für
  Urheberrecht und Lizenzen genügt, ist eine rechtliche Frage außerhalb der
  Software.
- **Aufwand:** größer als das Teilen allein – die Bearbeitungsrechte der
  Eltern berühren alle Routen, die heute `getOwned` benutzen. Diese werden
  einzeln umgestellt und getestet, keine pauschale Ausweitung.
- **Erstes Ausrollen:** Nach der Migration sind zunächst fast alle Konten
  „unter 18“ und können nichts Neues teilen, bis der Admin umstellt. Die
  Benutzerverwaltung zeigt dafür einen Hinweis mit Anzahl.
