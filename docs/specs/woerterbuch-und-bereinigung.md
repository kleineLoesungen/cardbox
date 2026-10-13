# Wörterbuch und Bereinigung durch die Moderation

Status: **freigegeben** · Stufe 1 (Wörterbuch) ✅ · Stufen 2–3 offen

Fasst die Issues #5 und #6 zusammen:

- **Stufe 1 – Wörterbuch (#5):** Nachschlagen in allen Karten, die man
  sehen darf, ohne zu lernen.
- **Stufe 2 – Korrigieren (#6):** Moderation und Verwaltung dürfen Karten in
  geteilten Stapeln bearbeiten.
- **Stufe 3 – Doppelte Karten zusammenführen (#6):** Moderation findet
  doppelte Karten in geteilten Stapeln und führt sie zusammen.

Entscheidungen (Rückfragen vom 13.10.2026): Wörterbuch = Nachschlagen (1a),
gesucht wird in allem Sichtbaren (2a), zusammengeführt werden **einzelne
Karten** (3b). Offen gelassen und hier angenommen: Lernstand wandert beim
Zusammenführen mit, der weiter fortgeschrittene gilt (4a).

## Ziel

Wer ein Wort sucht, findet es in allen eigenen und geteilten Stapeln und
kann es vorlesen lassen oder in einen eigenen Stapel übernehmen. Und die
Moderation kann Fehler in geteilten Karten direkt beheben und doppelte
Karten aufräumen, ohne dass jemand Lernstand verliert.

## Nicht dabei

- Ein Wörterbuch zum Blättern (A–Z) oder ein Wörterbuch von außen
  (Wiktionary, DeepL o. Ä.).
- Lernen aus dem Wörterbuch heraus; dafür gibt es Stapel.
- Ganze Stapel zusammenführen (3a) und Stapel einer anderen Besitzerin
  übertragen.
- Zusammenführen in privaten Stapeln – auch nicht für Moderation oder
  Verwaltung, private Stapel anderer bleiben für alle unsichtbar.
- Karten als **Verweise** in mehreren Stapeln (bewusst ausgeschlossen in
  Issue #2, siehe [lernplaene-und-karten.md](lernplaene-und-karten.md)).
- Benachrichtigungen an Besitzerinnen über Korrekturen der Moderation.
- Automatisches Zusammenführen ohne Entscheidung eines Menschen.

---

## Verhalten

### Stufe 1: Wörterbuch (#5)

- Neuer Menüpunkt **„Wörterbuch“** unter Mehr (in `nav-links`), Seite
  `/dictionary`.
- Oben ein Suchfeld, Ergebnisse erscheinen beim Tippen (ab 2 Zeichen, kurz
  verzögert, wie die Suche beim Übernehmen). Optional ein Filter
  **Sprache** (alle Sprachen, die in sichtbaren Stapeln vorkommen).
- Gesucht wird in **jeder einzelnen Bedeutung** beider Seiten, ohne
  Groß/Klein, in eigenen, gefolgten und **allen geteilten** Stapeln –
  dieselbe Menge wie beim „Übernehmen“. Fremde private Stapel nie.
- **Ein Treffer** ist ein Wortpaar, nicht eine Karte: Karten mit gleicher
  Hauptbedeutung vorne und hinten (gleiches Sprachpaar, auch umgekehrt)
  werden zusammengefasst. Je Treffer:
  - Hauptbedeutung groß, Sprache klein darüber, das Gegenstück darunter,
    weitere Bedeutungen als „auch: …“, Hinweis klein;
  - **Vorlesen** für beide Seiten (wie in der Lernrunde, nur wenn das Gerät
    eine Stimme hat);
  - „aus *Englisch Unit 3* von Anna“ bzw. „in 3 Stapeln“ (aufklappbar);
  - **„In Stapel übernehmen“**: Blatt mit den eigenen Stapeln passender
    Sprachen; übernimmt die Karte wie „Aus anderen Stapeln übernehmen“
    (mit Herkunft, Spec B3). Gibt es keinen passenden Stapel, steht dort
    „Lege zuerst einen Stapel Deutsch → Englisch an.“
- Höchstens 30 Treffer, älteste Karten zuerst; darüber „Mehr als 30 Treffer
  – genauer suchen“.
- Leerzustand vor der Suche: ein Satz, was das Wörterbuch kann.

### Stufe 2: Korrigieren durch die Moderation (#6)

- Moderatoren und Administratoren sehen in **fremden geteilten** Stapeln an
  jeder Karte (aufgeklappte Kartenzeile) **„Bearbeiten (Moderation)“**. Es
  öffnet das bekannte Bearbeiten-Blatt (Bedeutungen, ☆ Hauptbedeutung,
  Hinweis) mit dem Zusatz „Du änderst eine Karte von *Anna*.“
- Speichern ändert die Karte für alle. Lernende sehen „geändert“, Kopien
  „Original geändert“ – wie bei jeder Änderung durch die Besitzerin.
- **Löschen** einzelner Karten darf die Moderation nicht (nur ganze Stapel,
  wie bisher). Doppelte Karten verschwinden über Stufe 3.
- Offene Vorschläge zu der Karte bleiben offen; die Besitzerin entscheidet
  weiter über sie.
- Jede Korrektur wird geloggt (`Log::info`, wer, welche Karte), wie die
  übrigen Moderationsaktionen.

### Stufe 3: Doppelte Karten zusammenführen (#6)

**Was als doppelt gilt:** Karten in geteilten Stapeln mit **gleichem
Sprachpaar** (auch umgekehrt) und **gleicher Hauptbedeutung vorne und
hinten**, ohne Groß/Klein und Leerraum. „Hund – dog“ in drei geteilten
Stapeln ist eine Gruppe mit drei Karten.

**Finden:** Unter **Moderation** ein neuer Bereich **„Doppelte Karten“**:
Liste der Gruppen (Wortpaar, Anzahl Karten, Anzahl Stapel), meiste zuerst,
höchstens 50 je Seite.

**Entscheiden:** Eine Gruppe öffnet eine Seite mit allen Karten der Gruppe:
Stapel, Besitzerin, alle Bedeutungen, Hinweis, wie viele Personen sie
lernen. Die Moderation

1. wählt die Karte, die **bleibt** (damit auch, wessen Fassung gilt und wer
   Besitzerin bleibt),
2. kann deren Inhalt vorher korrigieren (z. B. Bedeutungen der anderen
   Karten ergänzen – je Bedeutung ein Knopf „übernehmen“),
3. bestätigt „Zusammenführen“ mit einer Rückfrage, die nennt, was
   passiert.

**Was beim Zusammenführen passiert** – abhängig davon, wo die anderen
Karten liegen:

| Andere Karte liegt … | Ergebnis |
|---|---|
| im **selben Stapel** wie die bleibende | Sie wird **gelöscht**. Ihr Lernstand wandert auf die bleibende Karte (siehe unten). |
| in einem **anderen Stapel** | Sie bleibt in ihrem Stapel, bekommt den Inhalt der bleibenden Karte und wird mit ihr **verknüpft**: Die bleibende ist ihr Original (Herkunft, Spec B3). Ändert sich das Original später, sieht die Besitzerin „Original geändert“ und übernimmt mit einem Tipp. Lernstand bleibt unverändert. |

Das ist das „Folgen“ aus dem Issue, auf Ebene einer einzelnen Karte: Da
Folgen nur für ganze Stapel gibt und Verweise auf fremde Karten bewusst
ausgeschlossen sind, wird die doppelte Karte zur **verknüpften Kopie** der
bleibenden. Die Besitzerin des anderen Stapels behält ihren Stapel; ihre
Karte entspricht danach der bleibenden und bekommt künftige Korrekturen
angeboten.

**Lernstand beim Löschen im selben Stapel:** Je Person, Durchgang und
Richtung bleibt der **weiter fortgeschrittene** Stand (höheres Fach; bei
gleichem Fach der mit dem späteren Fälligkeitstag). Zählungen
(`review_count`, `correct_count`) werden addiert. Lernserien bleiben
unberührt.

**Weitere Folgen beim Löschen:**
- Kopien, deren Original die gelöschte Karte war, zeigen danach auf die
  bleibende Karte.
- Offene Vorschläge zur gelöschten Karte wandern zur bleibenden, sofern die
  Person dort nicht schon einen offenen hat; sonst werden sie als
  „abgelehnt“ geschlossen.

**Nach dem Zusammenführen** zeigt die Seite das Ergebnis („2 Karten
verknüpft, 1 gelöscht, Lernstand von 4 Personen übernommen“) und die Gruppe
ist aus der Liste verschwunden (sie hat nur noch verknüpfte Karten mit
gleichem Inhalt – solche Gruppen werden nicht mehr als doppelt gezeigt).

### Fehlerfälle

- Karte oder Stapel nicht (mehr) geteilt, unbekannt oder ungültige ID →
  **404**.
- Zusammenführen einer Gruppe, die sich inzwischen geändert hat (Karte
  gelöscht, nicht mehr doppelt) → Meldung „Diese Gruppe hat sich geändert.
  Bitte neu laden.“, nichts passiert.
- Zwei Moderatoren führen gleichzeitig zusammen → das Zusammenführen läuft
  in einer Transaktion mit Sperre auf den Karten der Gruppe; der zweite
  bekommt die Meldung oben.
- Wörterbuch: Suchbegriff unter 2 Zeichen → keine Suche, Hinweis.

## Berechtigungen

| Was | Route | Gruppe | Prüfung im Service |
|---|---|---|---|
| Wörterbuch | `GET /dictionary`, `GET /dictionary/search` | `auth` | `DeckService::visibleTo` |
| Treffer übernehmen | bestehendes `POST /decks/{deck}/adopt/{card}` | `auth` | `CardService::adopt` (eigener Zielstapel) |
| Karte korrigieren | `GET /moderation/decks/{deck}/cards/{card}/edit`, `POST …/cards/{card}` | `['auth', 'role:moderator']` | neue systemweite Fassung `CardService::updateShared` (nur geteilte Stapel) |
| Doppelte Karten | `GET /moderation/duplicates`, `GET /moderation/duplicates/{key}`, `POST /moderation/duplicates/{key}/merge` | `['auth', 'role:moderator']` | `CardMergeService` (nur geteilte Stapel) |

Administratoren erben die Moderatorrechte (`role:moderator` gilt ab
Moderator). Keine neuen `PUBLIC_ROUTES`.

**Abweichung vom Grundsatz „nur die Besitzerin bearbeitet“:** Stufe 2 und 3
ändern fremde Inhalte. Das ist mit Issue #6 ausdrücklich gewünscht und war
für Admins schon beim CSV-Import geöffnet. Begrenzt auf geteilte Stapel und
die Moderationsrouten; jede Aktion wird geloggt.

## Datenmodell

**Keine Migration.** Genutzt werden `cards.source_card_id` /
`source_updated_at` (Verknüpfung), `card_progress` (Lernstand umhängen) und
`card_suggestions` (Vorschläge umhängen).

Eine Gruppe wird über einen Schlüssel aus Sprachpaar und normalisierten
Hauptbedeutungen adressiert (z. B. `de-en:hund:dog`, URL-kodiert); er wird
bei jedem Aufruf neu berechnet, nichts wird gespeichert.

## Betroffene Stellen

**Stufe 1** ✅ – umgesetzt wie beschrieben, mit diesen Präzisierungen:
- „In Stapel übernehmen“ klappt in der Trefferkarte auf (kein eigenes
  Blatt) und zeigt je passendem eigenen Stapel einen Knopf; nach dem Tipp
  steht dort „übernommen in …“. Route `POST /dictionary/adopt/{card}`,
  dahinter `CardService::adopt`.
- Ohne JavaScript funktioniert die Seite als normales Suchformular
  (`/dictionary?q=…`).
- Je Suche werden höchstens 200 Karten gelesen und zu Treffern
  zusammengefasst; der Schlüssel dafür (`DictionaryService::pairKey`) dient
  in Stufe 3 auch für doppelte Karten.
- Das Vorlese-Skript liegt jetzt in `partials/speak-script` und kann Knöpfe
  mit festem Text (`data-speak-text`, `data-speak-lang`).
- Tests: `tests/Feature/DictionaryTest.php`.

Geplante Stellen:
- `app/Services/DictionaryService.php` (neu): `search(User, term,
  ?language)` → gruppierte Treffer; `languages(User)`.
- `app/Http/Controllers/DictionaryController.php` (neu).
- `resources/views/pages/dictionary.blade.php`,
  `resources/views/partials/dictionary-results.blade.php`, Blatt zum
  Übernehmen.
- Vorlesen: das Skript aus `layouts/focus` in eine Partial auslagern
  (`partials/speak-script`), damit Lernrunde und Wörterbuch es teilen.
- `nav-links.blade.php`: Menüpunkt; Hilfe-Abschnitt „Wörterbuch“.

**Stufe 2**
- `CardService::getShared`, `updateShared` (systemweit, nur geteilte
  Stapel; dieselbe Validierung wie `updateOwned`).
- `Moderation/CardController` (neu), Routen in der Moderationsgruppe.
- `components/card-row`: Knopf für Moderation; `partials/card-edit` mit
  Formularziel und Hinweis „Du änderst eine Karte von …“.

**Stufe 3**
- `app/Services/CardMergeService.php` (neu): `groups(page)`,
  `group(key)`, `merge(key, keepCardId, content)`.
- `Moderation/DuplicateController` (neu), Routen.
- `resources/views/moderation/duplicates.blade.php`,
  `moderation/duplicate.blade.php`; Unterpunkt auf `pages/moderation`.

Zu allen Stufen: `CHANGELOG.md`, Hilfe, `docs/specs/vokabelkarten.md`
(Berechtigungen der Moderation).

## Tests

**Stufe 1**
- findet eigene, gefolgte und geteilte Karten, nie fremde private;
- sucht in einzelnen Bedeutungen beider Seiten, ohne Groß/Klein, `%`/`_`
  wörtlich; unter 2 Zeichen keine Suche;
- fasst gleiche Wortpaare aus mehreren Stapeln zusammen (auch bei
  umgekehrtem Sprachpaar) und nennt die Stapel;
- Sprachfilter; höchstens 30 Treffer mit Hinweis;
- „Übernehmen“ nur in eigene Stapel passender Sprachen, mit Herkunft;
- Antwort auf die Suche ist ein Fragment; Seite nur für Angemeldete.

**Stufe 2**
- Moderator und Admin ändern Karte in fremdem geteiltem Stapel; Benutzer
  bekommt 403, Karte in fremdem privatem Stapel 404;
- dieselbe Validierung wie beim Bearbeiten; Lernende sehen „geändert“;
- Korrektur wird geloggt.

**Stufe 3**
- Gruppenbildung: gleiche Hauptbedeutungen, Groß/Klein, Leerraum,
  umgekehrtes Sprachpaar; private Stapel nie; verknüpfte Karten mit
  gleichem Inhalt keine Gruppe mehr;
- Zusammenführen im selben Stapel: Karte gelöscht, Lernstand je Person/
  Durchgang/Richtung nach der Regel übernommen, Zählungen addiert;
- in anderem Stapel: Inhalt übernommen, verknüpft, Lernstand unverändert;
  spätere Änderung am Original → „Original geändert“ bei der Besitzerin;
- Kopien und offene Vorschläge der gelöschten Karte umgehängt, Konflikte
  geschlossen;
- veränderte Gruppe → Meldung, nichts passiert; Benutzer 403.

## Annahmen und Risiken

- **Lernstand wandert mit (4a)** – offen gelassen, angenommen. Ohne diese
  Regel würde eine Bereinigung Lernenden Fortschritt wegnehmen.
- **„Folgen“ auf Kartenebene = verknüpfte Kopie.** Das ist die zentrale
  Annahme dieser Spezifikation: Die doppelte Karte in einem anderen Stapel
  bleibt dort, gleicht sich an und bekommt künftige Korrekturen angeboten,
  statt zu verschwinden. Wer stattdessen erwartet, dass sie aus dem anderen
  Stapel **entfernt** wird, bräuchte Verweise auf fremde Karten – die sind
  bewusst ausgeschlossen. Bitte bestätigen.
- **Doppelt = gleiche Hauptbedeutung vorne und hinten.** „Bank – bench“ und
  „Bank – bank“ sind verschieden und werden nicht vorgeschlagen. Tippfehler
  („Hnud – dog“) findet die Liste nicht; die korrigiert man über das
  Wörterbuch und Stufe 2.
- **Gruppen werden bei jedem Aufruf berechnet.** Bei sehr vielen geteilten
  Karten (zehntausende) kann die Liste langsam werden; dann bräuchte es eine
  gespeicherte Hauptbedeutung mit Index (eigene Migration, später).
- **Wörterbuch zeigt Anzeigenamen** der Besitzerinnen geteilter Stapel – wie
  „Entdecken“ heute schon.
- **Moderation ändert fremde Karten** – siehe Berechtigungen; geloggt, nur
  geteilte Stapel.
