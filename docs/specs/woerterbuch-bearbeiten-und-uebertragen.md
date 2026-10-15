# Karten im Wörterbuch bearbeiten, Stapel übertragen

Status: **umgesetzt** (15.10.2026)

Issue #12, dazu eine Erweiterung aus der Rückfrage vom 15.10.2026.

Entscheidungen (Rückfragen vom 15.10.2026):

- Ein Treffer mit mehreren Karten zeigt vor dem Bearbeiten eine **Auswahl:
  ein Stapel oder alle**.
- Wer eine Karte nicht ändern darf, kann sie weiter **melden**
  („Fehler melden“).
- **Löschen** geht auch im Wörterbuch: Besitzerinnen ihre eigenen Karten,
  Moderation und Verwaltung auch Karten in fremden **geteilten** Stapeln.
  Damit fällt die bisherige Regel „Moderation löscht nie einzelne Karten“
  aus [woerterbuch-und-bereinigung.md](woerterbuch-und-bereinigung.md).
- Administratoren können **Stapel übertragen**, auch private. Das geschieht
  auf der Kontoseite unter `/admin/users/{id}`. Die bisherige Besitzerin
  folgt einem geteilten Stapel danach automatisch.

## Ziel

Wer im Wörterbuch einen Fehler sieht, behebt ihn dort, wo er ihn gefunden
hat: Eigene Karten und, für die Moderation, Karten in geteilten Stapeln
lassen sich direkt bearbeiten oder löschen, auch in mehreren Stapeln auf
einmal. Administratoren können die Stapel eines Kontos einem anderen Konto
übergeben, zum Beispiel bevor sie ein Konto löschen.

## Nicht dabei

- Bearbeiten oder Löschen fremder Karten durch normale Nutzer. Für sie
  bleibt „Fehler melden“.
- Fremde **private** Stapel im Wörterbuch. Sie bleiben unsichtbar, auch für
  Moderation und Verwaltung. Das Übertragen auf der Kontoseite zeigt nur
  Name und Zahlen, keine Karten.
- Übertragen durch Moderatoren oder durch die Besitzerin selbst („Stapel
  verschenken“).
- Benachrichtigungen an die alte oder die neue Besitzerin.
- Übertragen über die JSON-Schnittstelle.
- Ein gemeinsamer Inhalt, der bei „alle“ aus mehreren Karten zusammengeführt
  wird. Dafür gibt es „Doppelte Karten“ in der Moderation.

---

## Verhalten

### A. Bearbeiten und Löschen im Wörterbuch

**Was eine Person an einer Karte darf** (je Karte eines Treffers):

| Karte liegt in … | normale Person | Moderator / Admin |
|---|---|---|
| eigenem Stapel | bearbeiten, löschen | bearbeiten, löschen |
| fremdem geteiltem Stapel | melden | bearbeiten, löschen (und melden) |

Gefolgte Stapel sind immer geteilt, sie fallen also in die zweite Zeile.

**Am Treffer:** Unter den bisherigen Knöpfen steht ein schlichter Knopf
(`btn-ghost`):

- **„Bearbeiten“**, wenn die Person mindestens eine Karte des Treffers
  ändern darf,
- sonst **„Fehler melden“**. Bei nur einem Stapel öffnet es direkt das
  Melden-Formular, sonst die Auswahl unten.

Die Hauptaktion bleibt „Diese Karte lernen“, damit gilt weiter: eine
Hauptaktion je Bildschirm.

**Das Blatt „Karte“** öffnet sich von unten. Bearbeiten, Auswahl und
Melden teilen sich dieses eine Blatt, damit nie zwei Blätter übereinander
liegen:

1. **Nur eine Karte im Treffer, die man ändern darf:** Das Formular steht
   sofort da. Es ist dasselbe wie auf der Stapelseite (Bedeutungen,
   ☆ Hauptbedeutung, Hinweis, „Karte löschen“). Bei einer fremden Karte
   steht darüber, wie bisher, „Moderation: Du änderst eine Karte von
   *Anna* …“.
2. **Mehrere Karten im Treffer:** Zuerst kommt eine **Auswahl**, eine Liste
   der Stapel:
   - je Stapel: Name, „dein Stapel“ oder „von *Anna*“, die Bedeutungen der
     Karte, falls sie von der angezeigten abweichen;
   - Stapel, die man ändern darf, wählt man mit einem Tipp aus. Ganz oben
     steht **„Alle (3)“**, falls es mindestens zwei davon gibt;
   - Stapel, die man nicht ändern darf, zeigen stattdessen „Fehler melden“.
     Das zeigt im selben Blatt das bekannte Melden-Formular für genau diese
     Karte.

   Nach der Wahl erscheint das Formular. Bei **„Alle“** ist es mit der Karte
   gefüllt, deren Treffer angezeigt wurde. Darüber steht: „Du änderst
   3 Karten in 3 Stapeln. Alle bekommen diesen Inhalt.“ Sind fremde darunter,
   steht dabei auch „davon 2 von anderen“. Der Knopf heißt dann „Alle
   3 speichern“ bzw. „Alle 3 löschen“.

**Speichern** ändert die gewählten Karten. Liegt eine Karte in einem Stapel
mit vertauschten Sprachen (Englisch → Deutsch statt Deutsch → Englisch),
werden die Seiten passend getauscht. Danach schließt das Blatt, eine Meldung
erscheint („Karte gespeichert.“ bzw. „3 Karten gespeichert.“), und die
Trefferliste lädt mit derselben Suche neu. Ein geänderter Treffer kann dabei
verschwinden, wenn er nicht mehr zum Suchwort passt. Das ist so gewollt.

Für Lernende gilt dasselbe wie bei jeder Änderung: Folgende sehen
„geändert“, Kopien „Original geändert“.

**Löschen** fragt nach. Die Rückfrage nennt die Folgen, z. B. „3 Karten
löschen? 12 Personen, die diesen Stapeln folgen, verlieren sie samt
Lernstand.“ Danach gibt es eine Meldung, und die Liste lädt neu.

**Melden** aus dem Wörterbuch schickt den Vorschlag wie bisher. Statt die
Kartenzeile auszutauschen, schließt das Blatt und eine Meldung erscheint
(„Danke! Dein Vorschlag ist bei *Anna*.“).

**Protokoll:** Jede Änderung und jede Löschung durch die Moderation an einer
fremden Karte wird mit `Log::info` festgehalten (wer, welche Karte, welcher
Stapel), wie die übrigen Moderationsaktionen.

### B. Stapel übertragen (Admin)

Auf **`/admin/users/{id}`** gibt es einen neuen Abschnitt **„Stapel“**:

- Liste aller Stapel des Kontos: Name, Sprachpaar, „geteilt“ oder „privat“,
  Zahl der Karten, Zahl der Folgenden. Karten selbst sind nicht zu sehen.
- Je Stapel ein Kontrollkästchen, darüber **„Alle auswählen“**.
- Darunter ein Feld **„An Konto (Benutzername)“** (`to_username`) und der
  Knopf **„Übertragen“**. Die Rückfrage lautet „Die gewählten Stapel an das
  angegebene Konto übertragen?“; Zahl und Ziel nennt die Meldung danach.
- Die Löschen-Rückfrage des Kontos weist zusätzlich darauf hin, dass sich
  Stapel vorher übertragen lassen.

**Was beim Übertragen passiert:**

| Wer | Folge |
|---|---|
| neue Besitzerin | Der Stapel gehört ihr: bearbeiten, teilen, löschen. Ist sie ihm vorher gefolgt, fällt das Folgen weg. Ihr Lernstand, ihre Auswahl („nur ein Teil“) und ihre Durchgänge bleiben – auch eine Auswahl aus „Folgen und diese Karte lernen“ mit nur einer Karte; neue Karten bleiben dann pausiert, bis sie sie anhakt. |
| alte Besitzerin, Stapel **geteilt** | Sie **folgt** dem Stapel danach und lernt mit ihrem Lernstand, ihrer Auswahl und ihren Durchgängen weiter. |
| alte Besitzerin, Stapel **privat** | Sie sieht den Stapel nicht mehr. Ihr Lernstand bleibt gespeichert und gilt wieder, falls der Stapel geteilt oder zurückübertragen wird. |
| Folgende, Kopien, Vorschläge | Bleiben unverändert. Offene Vorschläge entscheidet ab jetzt die neue Besitzerin. |

Danach führt die Seite zurück auf die Kontoseite, mit der Meldung „2 Stapel
an *ben* übertragen.“ Jede Übertragung wird mit `Log::info` festgehalten
(wer, welcher Stapel, von wem, an wen).

### Fehlerfälle

- Karte unbekannt, ungültige ID, fremd und privat, oder fremd und geteilt
  ohne Moderationsrolle → **404**, nichts wird geändert. Das gilt für den
  **ganzen** Vorgang: Ist unter „Alle“ eine Karte nicht (mehr) erlaubt,
  bleibt auch an den übrigen nichts geändert.
- Eine gewählte Karte ist inzwischen gelöscht → ebenfalls **404**, nichts
  passiert. Gelöscht und verboten sehen gleich aus, damit Antworten nichts
  darüber verraten, welche IDs es gibt.
- Validierung wie beim Bearbeiten im Stapel (leere Seite, zu lang …). Die
  Meldung erscheint, das Blatt bleibt offen.
- Übertragen: Benutzername unbekannt, Konto nicht freigegeben oder gesperrt
  → Fehlermeldung am Feld. Ziel gleich bisheriger Besitzerin → „Die Stapel
  gehören schon *ben*.“ Kein Stapel gewählt → „Wähle mindestens einen
  Stapel.“ Ein gewählter Stapel gehört nicht (mehr) diesem Konto → Meldung
  „Die Liste hat sich geändert. Bitte neu laden.“, nichts wird übertragen.

---

## Berechtigungen

Rollenprüfungen stehen an der Route, nicht im Controller.

| Route | Gruppe | Service-Fassung |
|---|---|---|
| `GET /dictionary/cards/{card}/edit` – Auswahl oder Formular | `auth` | Gruppe aus sichtbaren Karten; was änderbar ist, nur zur Anzeige |
| `POST /dictionary/cards` – eigene Karten speichern (`cards[]`) | `auth` | `CardService::updateManyOwned` – nur eigene Stapel |
| `POST /dictionary/cards/delete` – eigene Karten löschen | `auth` | `CardService::deleteManyOwned` |
| `POST /moderation/dictionary/cards` – speichern | `role:moderator` | `CardService::updateManyModerated` – eigene und geteilte Stapel |
| `POST /moderation/dictionary/cards/delete` – löschen | `role:moderator` | `CardService::deleteManyModerated` |
| `GET/POST /decks/{deck}/cards/{card}/suggest` mit `context=dictionary` | `auth` (bestehend) | unverändert |
| `POST /admin/users/{user}/decks/transfer` | `role:admin` | `DeckService::transfer` – systemweit, auch private |

- Welches Formularziel das Blatt benutzt (`/dictionary/…` oder
  `/moderation/dictionary/…`), entscheidet die Ansicht nach der Rolle. Das
  ist nur Anzeige: Schickt eine normale Person fremde Karten an
  `/dictionary/cards`, gibt es 404. Schickt sie an `/moderation/…`, gibt es
  403 von der Routengruppe.
- Die `…Moderated`-Fassungen kommen nie an fremde **private** Stapel, so wie
  `getShared` heute.
- Die Auswahl im Wörterbuch zeigt nur Karten aus `DeckService::visibleTo`.

## Datenmodell

**Keine Migration.** Übertragen ändert `decks.user_id` (mit `forceFill`,
weil das Feld nicht befüllbar ist und es auch nicht werden soll) und legt
bzw. löscht Zeilen in `deck_follows`. Lernstand (`card_progress`,
`deck_runs`) und Auswahl (`deck_selections`, `card_selections`) hängen an
der Person und nicht an der Besitzerin. Sie bleiben deshalb gültig.

Löschen einer Karte entfernt wie bisher Lernstand, Auswahl und Vorschläge
dazu (`cascadeOnDelete`). Kopien, deren Original sie war, behalten ihre
`source_card_id`; sie zeigen danach kein „Original geändert“ mehr, wie beim
Löschen auf der Stapelseite.

## Betroffene Stellen

- **app/Services/**
  - `DictionaryService`: `group(User, cardId)` – alle sichtbaren Karten mit
    demselben `pairKey` wie die genannte (Vergleich in SQL mit derselben
    Normalisierung), mit Stapel und Besitzerin; die genannte Karte steht
    immer zuerst. Ob eine Karte änderbar angeboten wird, entscheidet die
    Ansicht (siehe „Rollenlogik in der Ansicht“).
  - `CardService`: `updateManyOwned`, `deleteManyOwned`,
    `updateManyModerated`, `deleteManyModerated`. Je eine Transaktion, die
    erst die Stapel und dann die Karten sperrt (so kann das Teilen nicht
    zwischen Prüfung und Schreiben enden), mit
    Sperre auf die Karten. Seiten werden bei vertauschtem Sprachpaar
    getauscht. Validiert einmal wie `validate()`.
  - `DeckService`: `transfer(User $from, mixed $deckIds, mixed $username)`
    in einer Transaktion: Ziel prüfen, Stapel sperren, `user_id` setzen,
    Folgen der neuen Besitzerin entfernen, die alte Besitzerin bei geteilten
    Stapeln folgen lassen. Dazu `listForAdmin(User)` für die Liste auf
    der Kontoseite.
- **app/Http/Controllers/**
  - `DictionaryController`: `edit`, `update`, `destroy`. Antwort:
    Meldung + `HX-Trigger: sheet-close, dictionary-refresh`.
  - `Moderation/DictionaryCardController` (neu): `update`, `destroy` mit
    `Log::info`.
  - `CardSuggestionController::store`: Bei `context=dictionary` antwortet es
    mit Meldung + `sheet-close` statt mit der Kartenzeile.
  - `Admin/UserController`: `show` reicht die Stapelliste durch, neu ist
    `transferDecks`.
- **routes/web.php**: die Routen aus „Berechtigungen“ in den bestehenden
  Gruppen.
- **resources/views/**
  - `pages/dictionary.blade.php`: ein Blatt `dictionary-sheet` für
    Bearbeiten, Auswahl und Melden. Die Suche lädt bei
    `dictionary-refresh from:body` neu.
  - `partials/dictionary-results.blade.php`: Knopf „Bearbeiten“ bzw.
    „Fehler melden“.
  - neu `partials/dictionary-edit.blade.php` (Auswahl und Formular mit
    `cards[]`). Die Felder für Bedeutungen und Hinweis kommen aus den vorhandenen Partials
    (`meaning-row`, `card-hint-field`).
  - `decks/partials/suggest-form.blade.php`: optionaler Kontext
    `dictionary`.
  - `admin/user.blade.php`: Abschnitt „Stapel“ mit Übertragen-Formular
    (`decks[]` – Fallstrick 3).
- **lang/de/validation.php**: Attribute `decks` und `to_username`.
- **docs/specs/woerterbuch-und-bereinigung.md**: Hinweis bei „Löschen
  einzelner Karten darf die Moderation nicht“ und bei „Stapel einer anderen
  Besitzerin übertragen“, dass dieses Dokument das ablöst.
- **resources/views/pages/help.blade.php**, **site/index.html**: gegenlesen,
  ob Aussagen zu Moderation und Löschen noch stimmen.
- **CHANGELOG.md**: neuer Eintrag (ohne Migration).

## Tests

**Wörterbuch – Bearbeiten (Feature):**
- Eigene Karte: „Bearbeiten“ sichtbar, Formular lädt direkt, Speichern
  ändert die Karte, die Antwort enthält `dictionary-refresh`.
- Treffer in 3 Stapeln (2 eigene, 1 fremd geteilt): Die Auswahl zeigt
  „Alle (2)“ und beim fremden „Fehler melden“.
- „Alle“ ändert beide eigenen Karten. In einem Stapel mit vertauschtem
  Sprachpaar sind die Seiten richtig herum.
- Normale Person schickt eine fremde geteilte Karte an `/dictionary/cards`
  → 404, **keine** Karte geändert, auch nicht die eigenen aus derselben
  Anfrage.
- Normale Person an `/moderation/dictionary/cards` → 403.
- Moderator ändert eine fremde geteilte Karte → geändert, Log-Eintrag.
- Moderator mit einer fremden **privaten** Karte → 404.
- Ungültige UUID in `cards[]` → 404, kein 500.
- Validierung (leere Vorderseite) → 422-Meldung, nichts geändert.

**Wörterbuch – Löschen:**
- Besitzerin löscht eine eigene Karte. Lernstand von Folgenden ist weg.
- Moderator löscht eine fremde geteilte Karte → gelöscht, Log-Eintrag.
- Normale Person löscht eine fremde Karte → 404, Karte bleibt.

**Melden aus dem Wörterbuch:**
- `context=dictionary` → Antwort ohne Kartenzeile, mit `sheet-close`,
  Vorschlag gespeichert.

**Übertragen (Feature):**
- Admin überträgt einen geteilten Stapel: neue Besitzerin, alte folgt,
  deren Lernstand zählt weiter (erscheint unter „Heute“).
- Die neue Besitzerin folgte vorher → kein Eintrag mehr in `deck_follows`.
- Privater Stapel: Die alte Besitzerin bekommt 404 auf den Stapel, ihr
  Lernstand liegt noch in `card_progress`.
- Mehrere Stapel auf einmal (`decks[]` – alle angekreuzten kommen an).
- Ziel unbekannt, nicht freigegeben, gesperrt, gleich bisheriger
  Besitzerin → Fehler, nichts übertragen.
- Stapel eines anderen Kontos in `decks[]` → nichts übertragen.
- Moderator und normale Person → 403 (deckt `RouteProtectionTest` mit ab).

## Annahmen und Risiken

- **„Alle“ überschreibt.** Abweichende Bedeutungen oder Hinweise der
  anderen Karten gehen verloren. Die Auswahl zeigt Abweichungen deshalb vor
  dem Speichern an. Wer zusammenführen will, nimmt „Doppelte Karten“.
- **Eine Gruppe ist eine Momentaufnahme.** Gespeichert wird genau für die
  übergebenen `cards[]`, nicht für die Gruppe, wie sie beim Speichern
  aussieht. Kommt in der Zwischenzeit eine Karte hinzu, bleibt sie
  unverändert. Das ist bewusst so, damit nichts geändert wird, was die
  Person nicht gesehen hat.
- **Löschen durch die Moderation** kostet Folgende ihren Lernstand und lässt
  sich nicht rückgängig machen. Die Rückfrage nennt die Zahl, und es gibt
  ein Protokoll. Ein Papierkorb ist nicht vorgesehen.
- **Private Stapel beim Übertragen:** Administratoren sehen dafür Namen und
  Zahlen fremder privater Stapel. Das war bisher nicht so (nur die
  Gesamtzahl in der Löschen-Rückfrage). Die Karten selbst bleiben
  unsichtbar.
- **Gleichnamige Stapel:** Die neue Besitzerin kann danach zwei Stapel mit
  demselben Namen haben. Es gibt keine Eindeutigkeitsregel für Namen, also
  wird nichts umbenannt.
- **Rollenlogik in der Ansicht:** Ob „Bearbeiten“ einen Moderationsteil
  enthält, entscheidet die Ansicht anhand der Rolle (wie heute
  `canModerate` in `card-row`). Das ist nur Anzeige. Die Rechte prüfen die
  Routengruppen und die Service-Fassungen.
- **Auswahl der neuen Besitzerin bleibt:** Ist sie dem Stapel über
  „Folgen und diese Karte lernen“ gefolgt, lernt sie nach dem Übertragen
  weiter nur diese eine Karte, bis sie unter „Karten auswählen“ mehr
  anhakt. Bewusst so gelassen: Eine selbst gewählte Auswahl soll das
  Übertragen nicht stillschweigend verwerfen.
