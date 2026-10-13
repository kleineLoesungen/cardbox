# CSV-Import und -Export, Lizenz und Produktseite

Status: **freigegeben** · Stufe 1 (CSV) ✅ · Stufe 2 (Lizenz, Icon, Produktseite) ✅

Fasst die Issues #3 und #4 zusammen:

- **Stufe 1 – CSV (#3):** Karten eines Stapels als CSV exportieren und
  wieder importieren. Ein bearbeiteter Export ändert die Karten an Ort und
  Stelle; Stapel und Lernstand bleiben.
- **Stufe 2 – Produktseite (#4):** MIT-Lizenz, ein eigenes Icon für App und
  Produktseite, eine statische Produktseite unter `cardbox.se-wi.com` –
  technisch wie vizu.se-wi.com, inhaltlich wie manager.se-wi.com.

## Ziel

Wer viele Karten pflegt, kann sie in einer Tabellenkalkulation bearbeiten
und zurückspielen, ohne Lernstand zu verlieren. Und wer Cardbox noch nicht
kennt, findet eine Seite, die erklärt, was es ist, und wie man es selbst
betreibt.

## Nicht dabei

- **Löschen per CSV.** Fehlt eine Karte in der Datei, bleibt sie im Stapel.
  Gelöscht wird weiter nur in der App.
- Stapel per CSV anlegen, umbenennen oder Sprachen ändern. Import geht
  immer in einen bestehenden Stapel.
- Lernstand exportieren oder importieren.
- Andere Formate (Excel `.xlsx`, Anki, JSON).
- Einen Link von der Produktseite auf eine laufende Installation
  (`cards.se-wi.com` bleibt unerwähnt).
- Analyse, Cookies, Schriften oder Skripte von Dritten auf der
  Produktseite.
- Eine englische Fassung der Produktseite.

---

## Verhalten

### Stufe 1: CSV (#3)

#### Export

- Im Stapel-Menü (⋯) steht **„Als CSV exportieren“** – für jeden, der den
  Stapel sehen kann: eigene und geteilte Stapel, wie „Kopieren“.
- Die Datei heißt nach dem Stapel und seinen Sprachen, in Kleinbuchstaben
  ohne Umlaute (damit jeder Browser und jedes System sie gleich nennt),
  z. B. `englisch-unit-3_de-en.csv`.
- Format, so gewählt, dass Excel, Numbers und LibreOffice sie mit
  deutscher Einstellung direkt richtig öffnen:
  - UTF-8 mit BOM, Zeilenende CRLF, Trennzeichen `;`, Felder bei Bedarf in
    `"…"` (übliche CSV-Regeln, Anführungszeichen verdoppelt).
  - Kopfzeile: `ID;<Sprache vorne>;<Sprache hinten>;Hinweis` mit den
    Sprachcodes des Stapels, z. B. `ID;de;en;Hinweis`, bei Rechenstapeln
    `ID;formula;result;Hinweis`. Dieselben Codes wie in der vocaBox-
    Kopfzeile `de;en;tags`, die der Import schon kennt.
  - Mehrere Bedeutungen einer Seite in einer Zelle, getrennt durch ` | `
    (z. B. `fast | quick`). Die erste ist die Hauptbedeutung.
  - Eine Zeile je Karte, älteste zuerst.
- **Schutz vor Formeln:** Beginnt ein Feld mit `=`, `+`, `-`, `@`, Tab oder
  Wagenrücklauf, wird beim Export ein `'` vorangestellt, damit eine
  Tabellenkalkulation es nicht als Formel ausführt. Der Import entfernt
  genau dieses eine `'` wieder.

#### Import

Über die bekannte Seite **„Liste einfügen“** (eigener Stapel). Erkennt sie
eine CSV mit Kopfzeile `ID;…`, gilt der CSV-Modus; ohne diese Kopfzeile
bleibt alles wie heute (eine Bedeutung je Seite, `=`/Tab/`;`).

- Trennzeichen wird aus der Kopfzeile erkannt: `;`, `,` oder Tab (Excel
  speichert je nach Spracheinstellung verschieden). Die Kopfzeile darf
  groß/klein beliebig geschrieben sein; die Spalte `Hinweis` ist
  freiwillig.
- **Sprachen** stehen in der Kopfzeile:

| Spalten 2 und 3 | Ergebnis |
|---|---|
| Codes des Stapels (`de;en` bei Deutsch → Englisch) | wird so übernommen |
| Codes umgekehrt (`en;de`) | Seiten werden getauscht, wie heute beim vocaBox-Import |
| andere Sprachen (`de;fr`) | Abbruch: „Die Datei ist für Deutsch → Französisch, der Stapel ist Deutsch → Englisch.“ |
| `Vorne;Hinten` | ohne Prüfung übernommen (für selbst angelegte Tabellen) |

- Eine vocaBox-Datei mit `de;en;tags` ohne `ID`-Spalte läuft weiter über
  den bisherigen Import.
- Je Zeile:

| Zeile | Ergebnis |
|---|---|
| `ID` einer Karte **dieses** Stapels, Inhalt anders | Karte wird **geändert** (Vorne, Hinten, Hinweis). ID und Lernstand bleiben |
| `ID` einer Karte dieses Stapels, Inhalt gleich | **unverändert**, nichts wird geschrieben |
| `ID` leer | **neue** Karte |
| `ID` einer Karte, die die Person in einem **anderen** Stapel sehen kann | **neue** Karte mit Herkunft (wie „Aus anderen Stapeln übernehmen“, Spec B3) |
| `ID` unbekannt oder ungültig | **neue** Karte; die Vorschau sagt „unbekannte ID – wird neu angelegt“ |
| dieselbe `ID` ein zweites Mal in der Datei | **Fehler**, Zeile wird übersprungen |
| Vorne oder Hinten leer, zu viele/zu lange Bedeutungen, Hinweis zu lang | **Fehler**, Zeile wird übersprungen |

- **Vorschau** wie heute, oben die Summen: „3 neu · 5 geändert · 12
  unverändert · 1 Fehler“. Geänderte Zeilen zeigen alt → neu. Erst
  „Importieren“ schreibt.
- **Stapel und Lernstand bleiben:** Name, Sprachen, Teilen, Folgende,
  Durchgänge und Lernstand werden nicht angefasst. Geänderte Karten
  behalten ihre ID, also ihren Lernstand. Weil sie danach als geändert
  gelten, sehen Folgende beim Lernen „geändert“ und Kopien „Original
  geändert“ – gewollt, wie bei jeder anderen Bearbeitung.
- Grenzen wie heute: 1000 Zeilen, 1 MB.

#### Administratoren

- Administratoren dürfen in **jeden geteilten Stapel** importieren, also
  auch fremde Karten ändern („überschreiben“). Im Stapel-Menü eines fremden
  geteilten Stapels sehen sie unter „Verwaltung“ den Punkt **„Liste
  einfügen“**.
- Fremde **private** Stapel bleiben auch für Administratoren unsichtbar
  (bestehende Regel, abgesichert in `DeckTest`) – dort gibt es weder
  Export noch Import.
- Die Besitzerin bekommt keine eigene Nachricht; sie sieht die Änderungen
  an den Karten.

### Stufe 2: Lizenz, Icon und Produktseite (#4)

#### Lizenz

- `LICENSE` mit der **MIT-Lizenz**, „Copyright (c) 2026 Sebastian Willer“ –
  wie vizu-notion-desktop. MIT erfüllt „kostenlos, frei nutzbar, Open
  Source“ am einfachsten: jede Nutzung erlaubt, nur der Hinweis muss
  bleiben.
- `composer.json` bekommt `"license": "MIT"`, die README einen Abschnitt
  „Lizenz“.
- **team-manager** hat keine Lizenz, wirbt aber mit „Open Source“. Dort
  wird ein Issue „MIT-Lizenz ergänzen“ vorgeschlagen (Text unten unter
  „Annahmen und Risiken“). vizu-notion-desktop hat schon MIT – nichts zu
  tun.

#### Icon

- Erster Schritt der Stufe: **drei Entwürfe** als SVG zur Auswahl, alle in
  der Markenfarbe (`#3370c7`, unverändert, damit `PWA_THEME_COLOR` und das
  Theme passen) und auf 16 px noch erkennbar. Richtungen:
  1. zwei versetzte Karteikarten, die vordere mit einem Haken,
  2. ein Karteikasten von vorn mit drei Karten unterschiedlicher Höhe,
  3. eine umgedrehte Karte mit Ecke, darauf „A / あ“ angedeutet.
- Nach der Wahl: `resources/icons/icon.svg` und `icon-maskable.svg`
  ersetzen, `./scripts/icons.sh` erzeugt die PNGs, `public/favicon.svg`
  und `site/icon.svg` bekommen dasselbe Motiv.

#### Produktseite

- **Technik wie vizu:** eine einzelne `site/index.html` mit eingebettetem
  CSS und wenigen Zeilen JavaScript, dazu `site/icon.svg`. Kein Build,
  keine Abhängigkeiten. Abweichend von vizu **Systemschriften statt Google
  Fonts** – keine Anfragen an Dritte.
- **Veröffentlichung:** GitHub Pages über `.github/workflows/pages.yml`,
  nur der Ordner `site/`, nur bei Änderungen darin (wie vizu). Einmalig von
  Hand: GitHub → Settings → Pages → Source „GitHub Actions“, Domain
  `cardbox.se-wi.com`; beim Hoster ein CNAME `cardbox` →
  `kleineloesungen.github.io`.
- **Inhalt wie manager.se-wi.com**, Aussehen im Stil der Karten (wie die
  Startseite der App):
  1. Kopf: „Kostenlos · Open Source · fürs Smartphone“, Überschrift,
     ein Satz, Knöpfe **„Selbst betreiben“** (springt nach unten) und
     **„Code auf GitHub“**. Daneben/darunter ein Telefon-Mockup in HTML/CSS
     mit einer Lernkarte und den Knöpfen „Nochmal“/„Gewusst“.
  2. Funktionen, je ein Abschnitt mit kleinem Mockup und Stichworten:
     Karteikasten und Fächer · Lernpläne je Stapel · Stapel teilen: folgen
     oder kopieren · Fehler melden · Vorlesen · Liste einfügen und CSV ·
     Hauptbedeutung und Hinweis · auch Mathe.
  3. „In drei Schritten“: Admin richtet ein und gibt Konten frei → Stapel
     anlegen oder teilen → Lernen, jeden Tag ein paar Minuten.
  4. „Schlank betrieben“: PHP und Laravel, PostgreSQL, drei Wege
     (Docker, Webserver mit SSH, Webspace nur per FTP), Web-App zum
     Startbildschirm, keine Tracker, Konten ohne E-Mail-Adresse.
  5. „Zuletzt verbessert“: die letzten drei Versionen aus `CHANGELOG.md`,
     direkt von GitHub geladen (`raw.githubusercontent.com`, Zweig
     `main`), ohne `Migration:`-Zeilen, mit Link „Alle Änderungen auf
     GitHub“. Ohne Netz oder bei Fehler bleibt nur der Link stehen.
  6. Fuß: MIT-Lizenz, © 2026 Sebastian Willer, Link zum Repository.
- Mobil zuerst, ab 320 px ohne waagerechtes Scrollen, hell und dunkel
  (`prefers-color-scheme`), „Bewegung reduzieren“ respektiert.
- `site/` gehört **nicht** zur App: `deploy.sh` lädt es nicht hoch, das
  Docker-Abbild enthält es nicht.

### Fehlerfälle

- Export oder Import für einen nicht sichtbaren Stapel, eine ungültige ID
  oder (Import, Nicht-Admin) einen fremden Stapel → **404**.
- Datei mit Kopfzeile `ID;…`, aber Spalte 2 oder 3 ist weder ein
  Sprachcode noch `Vorne`/`Hinten` → Meldung „In der Kopfzeile fehlen die
  Sprachen, z. B. ID;de;en;Hinweis.“
- Sprachen der Datei passen nicht zum Stapel (auch nicht umgekehrt) →
  Abbruch mit beiden Sprachpaaren im Klartext.
- Datei in Windows-1252 statt UTF-8 → wird wie heute umgewandelt.
- Die Produktseite lädt das Changelog nicht (offline, GitHub langsam) →
  Abschnitt zeigt nur den Link, kein Fehler.

## Berechtigungen

| Was | Route | Gruppe | Prüfung im Service |
|---|---|---|---|
| Export | `GET /decks/{deck}/export` | `auth` | `DeckService::getVisible` |
| Import (Vorschau, Speichern) | bestehende `/decks/{deck}/import…` | `auth` | `getOwned`, wie heute |
| Import durch Admin | `GET /admin/decks/{deck}/import`, `POST …/preview`, `POST …/import` | `['auth', 'role:admin']` | systemweite Fassung `getShared` (nur geteilte Stapel) |

Keine neuen `PUBLIC_ROUTES`. Die Produktseite ist keine Route der App.

## Datenmodell

**Keine Migration.** CSV nutzt `cards.id`, `front`, `back`, `hint`,
`source_card_id` und `source_updated_at`, die es alle schon gibt.

## Betroffene Stellen

**Stufe 1** ✅ – umgesetzt wie beschrieben. Abweichungen und Präzisierungen:
- Dateiname in Kleinbuchstaben (`Str::slug`), siehe oben.
- Der Export ist ein Eintrag im Stapel-Menü für alle; der Admin-Eintrag
  heißt „Liste einfügen (Karten überschreiben)“ und steht unter
  „Verwaltung“. Die Import-Seite warnt Admins „Du änderst Karten von …“.
- `Admin\DeckImportController` erbt vom normalen Import-Controller und
  tauscht nur Stapel (`getShared`), Formularziel und Import-Fassung
  (`importShared`).
- Tests: `tests/Feature/CardCsvTest.php`.

Geplante Stellen:
- `app/Services/CardCsvService.php` (neu): `export(Deck)` liefert den
  CSV-Text; `parse(Deck, text)` mit Ergebnis je Zeile
  (neu/geändert/unverändert/Fehler); `import(...)` in einer Transaktion.
  Die Eigentumsprüfung bleibt bei den Aufrufern: `…Owned` für die
  Weboberfläche, `…Shared` für Admins.
- `CardImportService`: erkennt die `ID`-Kopfzeile und gibt an
  `CardCsvService` ab; sonst unverändert.
- `DeckController::export` (neu) bzw. eigener `DeckExportController`;
  `DeckImportController` zeigt die neue Vorschau;
  `Admin/DeckImportController` (neu) für Admins.
- `routes/web.php`: Export in `auth`, Admin-Import in `role:admin`.
- Views: Menüpunkt im Stapel-Menü, CSV-Vorschau
  (`decks/import-preview`), Admin-Abschnitt im Menü.
- `CHANGELOG.md`, Hilfe-Abschnitt „Viele Karten auf einmal“ ergänzt.

**Stufe 2** ✅ – umgesetzt wie beschrieben:
- Icon: Entwurf **B** (Karteikasten von vorn) gewählt; die maskierbare
  Fassung verkleinert das Motiv auf 68 % für die Android-Schutzzone.
- Issue im team-manager angelegt:
  [kleineLoesungen/team-manager#18](https://github.com/kleineLoesungen/team-manager/issues/18).
- Drei Aussagen der Produktseite beim Gegenlesen an die App angepasst
  (Offline-Seite statt „offline-fest“, 11 Sprachen, Docker Compose).
- `.claude/launch.json` hat einen Eintrag `site` (PHP-Server auf Port 8003)
  zum Ansehen der Seite.
- Tests: `tests/Feature/ProductSiteTest.php` (Lizenz, keine Fremd-Ressourcen,
  Icon gleich, Migrationen ausgeblendet).

Geplante Stellen:
- `LICENSE`, `composer.json`, `README.md`.
- `resources/icons/*.svg`, `public/icons/*`, `public/favicon.svg`.
- `site/index.html`, `site/icon.svg`, `.github/workflows/pages.yml`.
- `deploy.sh` (schließt `site/` und `.github/` aus), `.dockerignore`.
- `CLAUDE.md`: kurzer Abschnitt „Produktseite“ (statisch, kein Laravel,
  Pages-Workflow ist Veröffentlichung, keine CI).

## Tests

**Stufe 1**
- Export: Kopfzeile mit Sprachcodes (`ID;de;en;Hinweis`,
  `ID;formula;result;Hinweis`), BOM, `;`, ` | ` für Bedeutungen, Hinweis, Quoting von
  `;` und `"`, Formelschutz mit `'`, Dateiname; fremder privater Stapel
  404; geteilter fremder Stapel erlaubt.
- Rundreise: Export → unverändert importieren → „0 neu · 0 geändert · N
  unverändert“, keine Schreibvorgänge, `updated_at` unverändert.
- Geänderte Zeile ändert die Karte, ID und Lernstand (Durchgänge,
  Fächer) bleiben; neue Zeile legt an; fehlende Karte bleibt erhalten.
- ID aus anderem sichtbarem Stapel → neue Karte mit Herkunft; unbekannte
  und ungültige ID → neue Karte; doppelte ID → Fehler.
- Trennzeichen `,` und Tab; Kopfzeile groß/klein; ohne Spalte Hinweis;
  `'`-Schutz wird beim Import entfernt.
- Sprachen: passende Codes, umgekehrte Codes tauschen die Seiten (auch
  bei geänderten Karten), fremde Codes brechen mit Meldung ab,
  `Vorne;Hinten` ohne Prüfung, fehlende Sprachen → Meldung.
- Alter Import ohne `ID`-Kopfzeile verhält sich wie bisher.
- Grenzen: 1000 Zeilen, Bedeutungen je Seite, Hinweislänge.
- Admin: Import in fremden geteilten Stapel erlaubt, in fremden privaten
  404; Nicht-Admin auf Admin-Route 403/Weiterleitung;
  `RouteProtectionTest` grün.

**Stufe 2**
- `LICENSE` enthält MIT, `composer.json` `"license": "MIT"`.
- `site/index.html`: keine externen Ressourcen außer
  `raw.githubusercontent.com` (Test liest die Datei und sucht `src=`/
  `href=` auf fremde Hosts); Changelog-Ausschnitt ohne `Migration:`.
- Manuell im Browser: 320/375 px, hell/dunkel, Changelog geladen und
  offline.

## Annahmen und Risiken

- **Trennzeichen ` | ` für Bedeutungen.** Kommas kommen in Wendungen vor
  („Guten Morgen, wie geht's?“), senkrechte Striche in Vokabeln praktisch
  nie. Ein `|` innerhalb einer Bedeutung ist damit nicht darstellbar – ein
  bewusster Kompromiss.
- **Geänderte Karten gelten als „geändert“** für Folgende und Kopien. Ein
  großer Import mit vielen kleinen Korrekturen löst entsprechend viele
  Hinweise aus.
- **Admin-Import ändert fremde Inhalte.** Das ist neu und weicht vom
  Grundsatz „nur die Besitzerin bearbeitet“ ab – ausdrücklich gewünscht.
  Begrenzung: nur geteilte Stapel, nur über die Admin-Routen.
- **GitHub Pages ist ein Workflow im Repository.** `CLAUDE.md` sagt „Es
  gibt keine CI“ – das bleibt wahr: Der Workflow veröffentlicht nur
  `site/`, er prüft keinen Code. `just check` bleibt die einzige Prüfung.
- **Changelog von GitHub** setzt ein öffentliches Repository voraus (ist
  es) und zeigt, was auf `main` steht – auch wenn es noch nicht
  ausgerollt ist.
- **Icon-Wechsel** ändert das Symbol installierter Web-Apps erst, wenn
  Geräte das Manifest neu laden; das kann Tage dauern.
- **Issue-Vorschlag für team-manager** (wird nach Freigabe angelegt bzw.
  vorbereitet):

  > **MIT-Lizenz ergänzen**
  >
  > Die Produktseite wirbt mit „Free · Open Source“, das Repository hat
  > aber keine Lizenz – ohne Lizenz darf rechtlich niemand den Code
  > nutzen. Vorschlag: `LICENSE` mit der MIT-Lizenz („Copyright (c) 2026
  > Sebastian Willer“) wie bei vizu-notion-desktop und cardbox, dazu ein
  > Abschnitt „Lizenz“ in der README.
