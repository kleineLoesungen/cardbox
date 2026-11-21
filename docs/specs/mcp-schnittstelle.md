# KI-Assistenten verbinden (MCP mit OAuth)

## Ziel

Wer ein freigegebenes Cardbox-Konto hat, kann Cardbox mit **Claude** (Web
und Desktop), **ChatGPT** und **Gemini** verbinden. Dort fragt man dann
„Wie weit bin ich in Englisch?“ oder sagt „Leg mir einen Stapel ‚Unit 4‘ mit
diesen Wörtern an“, und der Assistent liest und pflegt die eigenen Stapel
über eine MCP-Schnittstelle. Die Anmeldung läuft über OAuth mit dem normalen
Cardbox-Login.

Entscheidungen vom 15.11.2026:

- **Alle freigegebenen Konten** dürfen verbinden, auch Kinderkonten (siehe
  Annahmen und Risiken: Datenschutz).
- **Lesen und eigene Stapel ändern:** Lernstand, Stapel und Wörterbuch
  lesen; eigene Stapel und Karten anlegen, ändern und löschen. Fremde
  Stapel nur lesen.
- **Nur eigene Daten:** keine Daten von Kindern über die Elternverknüpfung.
- **`laravel/mcp` mit Laravel Passport** statt eigenem OAuth-Server.

## Nicht dabei

- Lernen über den Assistenten (Karten beantworten, Runden, Duelle). Der
  Lernstand wird nur gelesen; „Kann ich“ entsteht weiter nur beim Lernen.
- Teilen, Nicht-mehr-Teilen, Folgen, Kopieren, Übernehmen aus fremden
  Stapeln, Vorschläge an fremde Stapel, Übertragen.
- Daten anderer Konten, auch nicht die der eigenen Kinder.
- Moderation und Verwaltung (Rollen, Freigaben, Tokens).
- Konto-Einstellungen (Passwort, Tagesziel, Erinnerungen).
- Ein lokaler MCP-Server (stdio) und feste Tokens im Header für
  Entwicklerwerkzeuge — Claude Code, Cursor und Gemini CLI können sich über
  denselben OAuth-Weg verbinden.
- Die bestehende JSON-API unter `/api/v1` mit App-Tokens bleibt, wie sie ist.

## Verhalten

### Verbinden

1. Im Assistenten fügt man einen eigenen Connector mit der Adresse
   `https://<installation>/mcp` hinzu (Claude: Einstellungen → Connectors;
   ChatGPT: Entwicklermodus → Connector; Gemini im Web: Verbundene Apps →
   eigene App). Unter **Mein Konto → KI-Assistenten** und in der Hilfe steht
   die Adresse zum Kopieren und eine Schritt-für-Schritt-Anleitung je
   Assistent.
2. Der Assistent meldet sich selbst als OAuth-Client an (dynamische
   Registrierung) und schickt die Person zu Cardbox.
3. Wer nicht angemeldet ist, sieht die normale Anmeldeseite und landet danach
   wieder im Ablauf.
4. **Zustimmungsseite** in Cardbox-Gestalt (eine Hauptaktion, einfache
   Sprache):
   - „**Claude** möchte auf dein Cardbox-Konto zugreifen.“ Der Name kommt vom
     Client und ist nicht geprüft; deshalb steht die Zieladresse darunter
     („Weiterleitung an claude.ai“).
   - Was der Assistent darf: „Deine Stapel, Karten und deinen Lernstand
     sehen. Deine eigenen Stapel und Karten anlegen, ändern und löschen.“
   - Hinweis: „Was du dem Assistenten zeigst, geht an den Anbieter
     (Anthropic, OpenAI, Google).“
   - Knöpfe **„Erlauben“** und „Abbrechen“.
5. Danach ist der Assistent verbunden. Zugriffstokens gelten eine Stunde,
   der Assistent erneuert sie selbst; die Verbindung hält 30 Tage ab der
   letzten Nutzung (wie die Anmeldung, Issue #24).

### Verbundene Assistenten verwalten

- **Mein Konto → KI-Assistenten** listet die verbundenen Assistenten mit
  Name, Zieladresse, verbunden seit und zuletzt genutzt. **„Trennen“**
  widerruft alle Tokens dieses Assistenten sofort.
- Ein neues Passwort trennt alle Assistenten (wie bei den Sitzungen).
- Gesperrte oder nicht mehr freigegebene Konten: Jede Anfrage wird
  abgewiesen, wie `LogoutInactiveUsers` im Web.
- Administratoren sehen auf der Kontoseite unter `/admin/users/{id}`, wie
  viele Assistenten verbunden sind, und können alle trennen.

### Werkzeuge

Bezeichner englisch, Beschreibungen und Antworten deutsch. Jedes Werkzeug
läuft als die verbundene Person und ruft dieselben Services wie die
Weboberfläche auf — immer die `Owned`- bzw. sichtbarkeitsprüfenden
Fassungen. Fremde oder unbekannte IDs ergeben „nicht gefunden“, nie „keine
Berechtigung“.

Lesen:

| Werkzeug | Was es liefert |
|---|---|
| `list_decks` | Eigene und gelernte Stapel: Name, Sprachen, Kartenzahl, „lerne ich“/pausiert/eigen, fällig, Fortschritt (kann ich · fast · im Kasten · neu) |
| `get_deck` | Ein sichtbarer Stapel mit seinen Karten (Vorderseite, Rückseite, Hinweis), höchstens 500 Karten. Sichtbar heißt wie in der App: eigen, mit allen geteilt oder mit mir geteilt ([stapel-mit-einzelnen-teilen.md](stapel-mit-einzelnen-teilen.md)) |
| `get_learning_status` | Heute: Tagesziel, geschafft, fällig, neu, Lernserie samt Rettung; Wortschatz; die letzten 7 Tage |
| `get_hard_words` | Knifflige Wörter mit Stapel |
| `search_dictionary` | Wörterbuch-Suche wie in der App (akzentfrei) |

Ändern (nur eigene Stapel):

| Werkzeug | Was es tut |
|---|---|
| `create_deck` | Stapel anlegen (Name, Sprachen, Beschreibung); privat, „lerne ich“ wie in der App |
| `update_deck` | Name, Beschreibung, Sprachen ändern |
| `delete_deck` | Stapel löschen (als zerstörend markiert, damit der Assistent nachfragt) |
| `add_cards` | Bis zu 100 Karten auf einmal; schon vorhandene Vorderseiten werden übersprungen und gemeldet |
| `update_card` | Eine Karte ändern |
| `delete_card` | Eine Karte löschen (als zerstörend markiert) |

- Validierung kommt aus den Services; Fehler gehen als verständlicher Text an
  den Assistenten zurück („Der Name fehlt.“), nicht als Ausnahme.
- Grenzen: 60 Werkzeugaufrufe pro Minute je Konto, 100 Karten je
  `add_cards`.

### Fehlerfälle

- Ohne gültiges Token: 401 mit dem Hinweis auf die OAuth-Metadaten, damit
  der Assistent die Anmeldung startet.
- Abgebrochene Zustimmung: Rückkehr zum Assistenten mit `access_denied`.
- Ist die Schnittstelle auf einer Installation abgeschaltet
  (`MCP_ENABLED=false`, die Vorgabe), antworten `/mcp` und die OAuth-Routen
  mit 404, und „KI-Assistenten“ fehlt unter „Mein Konto“.

## Berechtigungen

- `/mcp`: Middleware `auth:api` (Passport) und Passport-Scope `mcp:use`,
  dazu eine eigene Middleware, die gesperrte und nicht freigegebene Konten
  abweist. Keine Rollenprüfung: alle freigegebenen Konten.
- Zustimmungsseite (`/oauth/authorize`): `auth` (normale Sitzung).
- „Mein Konto → KI-Assistenten“ und „Trennen“: `auth`, nur eigene
  Verbindungen. Trennen für andere: `role:admin`.
- Öffentlich (bewusst in `PUBLIC_ROUTES`, mit Begründung):
  `/.well-known/oauth-protected-resource`,
  `/.well-known/oauth-authorization-server`, Client-Registrierung (gebremst
  je IP, 10 pro Stunde) und Token-Endpunkt (gebremst).
- **Abweichung von CLAUDE.md:** Dort steht „Sanctum-Tokens: nicht“ und
  „App-Tokens gehören niemandem“. Die OAuth-Tokens hier **gehören einer
  Person** — das ist gewollt: Ein Assistent handelt im Namen genau dieses
  Kontos und sieht nur dessen Daten. Die App-Tokens unter `/api/v1` bleiben
  davon getrennt und unverändert. CLAUDE.md bekommt dazu einen Abschnitt
  „KI-Assistenten (MCP)“, und die Tabelle „Nicht benutzt“ nennt Passport als
  Ausnahme nur für MCP.
- `RouteProtectionTest` prüft bisher nur Routen eigener Controller. Er wird
  erweitert: Auch die Routen von Passport und `laravel/mcp` müssen geschützt
  oder ausdrücklich öffentlich sein.

## Datenmodell

- Die Passport-Tabellen `oauth_clients`, `oauth_auth_codes`,
  `oauth_access_tokens`, `oauth_refresh_tokens` (ohne Device-Codes), mit
  **UUID-Fremdschlüssel** auf `users` und `cascadeOnDelete`, Zeitstempel mit
  Zeitzone. Migration: `2026_11_16_000000_create_oauth_tables.php` (aus den
  Passport-Vorlagen übernommen und angepasst, nicht veröffentlicht und
  vergessen).
- Keine Änderung an bestehenden Tabellen. „Zuletzt genutzt“ kommt aus den
  Tokens.
- Aufräumen ohne Scheduler: abgelaufene Tokens und Codes sowie registrierte
  Clients ohne Token nach einem Tag werden beim Aufruf von „KI-Assistenten“
  und der Benutzerverwaltung gelöscht (wie `pruneGuestSessions`, höchstens
  einmal pro Stunde).
- Schlüssel: Passport braucht ein Schlüsselpaar. Es kommt aus `.env`
  (`PASSPORT_PRIVATE_KEY`, `PASSPORT_PUBLIC_KEY`, mit Anführungszeichen),
  nicht aus Dateien im Abbild. Der Deploy-Hook prüft, dass beide gesetzt
  sind, wenn `MCP_ENABLED=true`.

## Betroffene Stellen

- **composer.json:** `laravel/mcp` (^1.2), `laravel/passport` (^13).
- **config/:** `passport.php` (Lebensdauer: Access 1 h, Refresh 30 Tage,
  Schlüssel aus der Umgebung), `mcp.php`, `cardbox.php` oder vorhandene
  Datei: `mcp_enabled` aus `MCP_ENABLED`.
- **app/Mcp/:** `CardboxServer` (Name, Anweisungen auf Deutsch) und je
  Werkzeug eine Klasse unter `app/Mcp/Tools/`. Dünn wie Controller: Eingabe
  annehmen, Service aufrufen, Antwort formen.
- **app/Services/:** `AssistantService` (verbundene Assistenten auflisten,
  trennen, alle trennen, aufräumen); `UserService::changePassword` trennt
  Assistenten. Fehlende Service-Methoden ergänzen statt Logik in Werkzeuge zu
  schreiben (z. B. `CardService::createManyOwned` für `add_cards`).
- **app/Models/User.php:** `HasApiTokens`, `OAuthenticatable`.
- **app/Http/Middleware/:** Abweisen gesperrter/nicht freigegebener Konten
  für den `api`-Guard.
- **app/Http/Controllers/:** `AssistantController` (Mein Konto:
  Liste, Trennen), Admin-Ergänzung in `Admin/UserController`.
- **routes/:** `routes/ai.php` (`Mcp::web('/mcp', …)`, `Mcp::oauthRoutes()`),
  Ergänzungen in `routes/web.php`; alles hinter `mcp_enabled`.
- **resources/views/:** `oauth/authorize.blade.php` (Zustimmung),
  `account/assistants.blade.php` (oder Abschnitt in `pages/account`), Hilfe
  „KI-Assistenten verbinden“, Navigation unverändert.
- **bootstrap/app.php:** Routen aus `routes/ai.php` laden; der
  `api`-Guard in `config/auth.php`.
- **deploy:** Deploy-Hook prüft Schlüssel; `deploy/webspace/htaccess-root`
  und `public/.htaccess` müssen den `Authorization`-Header durchreichen.
- **Doku:** diese Datei, `docs/DEPLOY.md` (Schlüssel erzeugen, `MCP_ENABLED`),
  `.env.example`, CLAUDE.md (Abschnitt und Ausnahme), CHANGELOG mit
  Migration, Produktseite gegenlesen.

## Tests

- **OAuth-Ablauf:** Metadaten-Endpunkte; Client registrieren; Autorisierung
  ohne Anmeldung → Login → Zustimmung → Code; Code gegen Token mit PKCE;
  falscher Verifier, falsche Redirect-URI, abgelaufener Code → abgewiesen;
  Refresh-Token tauscht; „Abbrechen“ → `access_denied`.
- **Zugriff:** ohne Token 401 mit `WWW-Authenticate`-Hinweis; gesperrtes,
  nicht freigegebenes oder gelöschtes Konto → abgewiesen; neues Passwort
  trennt; „Trennen“ widerruft sofort; Admin trennt alle.
- **Werkzeuge:** jedes Werkzeug mit eigenen Daten; fremde private Stapel und
  fremde Karten → „nicht gefunden“, auch bei `get_deck`, `update_card`,
  `delete_card`, `delete_deck`; ungültige IDs (keine UUID) → „nicht
  gefunden“ statt Datenbankfehler; Validierungsfehler als Text; `add_cards`
  mit Dubletten und mehr als 100 Karten; geteilte fremde Stapel lesbar, nicht
  änderbar; Kinderdaten nie in einer Antwort eines Elternkontos.
- **Grenzen:** Werkzeugbremse greift; Client-Registrierung gebremst.
- **Abschaltbar:** `MCP_ENABLED=false` → alle neuen Routen 404, kein Eintrag
  unter „Mein Konto“.
- **Routentest:** Paket-Routen werden mitgeprüft.
- **Aufräumen:** abgelaufene Tokens und verwaiste Clients verschwinden.

## Annahmen und Risiken

- **Datenschutz, besonders bei Kindern:** Alle freigegebenen Konten dürfen
  verbinden; dann gehen Lerndaten an Anthropic, OpenAI oder Google. Die
  Zustimmungsseite sagt das deutlich. Betreiber sollten das in ihre
  Datenschutzerklärung aufnehmen; die Installation kann die Schnittstelle mit
  `MCP_ENABLED=false` ganz abschalten (Vorgabe). Die Anbieter selbst setzen
  teils Altersgrenzen (z. B. Gemini nur für Erwachsene mit privatem Konto) —
  das liegt außerhalb von Cardbox.
- **Webspace (größtes technisches Risiko):** Der Hetzner-Webspace hat den
  `Authorization`-Header schon einmal verworfen (Deploy-Hook nutzt deshalb
  `X-Deploy-Token`). MCP-Clients schicken das Token nur dort. **Erster
  Schritt der Umsetzung** ist deshalb ein Durchstich auf dem Webspace: Header
  kommt an, Passport-Schlüssel aus der Umgebung, eine einfache Werkzeug-
  Anfrage über HTTP (ohne dauerhafte Verbindung) läuft. Scheitert das, wird
  neu entschieden, bevor weitergebaut wird.
- **Gefälschte Client-Namen:** Bei dynamischer Registrierung kann sich jeder
  „ChatGPT“ nennen. Deshalb zeigt die Zustimmung die Zieladresse; Redirects
  nur über HTTPS (außer `localhost` für Entwicklerwerkzeuge).
- **Neue Abhängigkeiten:** Passport bringt `league/oauth2-server` und eigene
  Tabellen mit. Sie werden mit festen Hauptversionen eingebunden; Updates wie
  bei Laravel nur innerhalb der Hauptversion.
- **`laravel/mcp`-Eigenheiten:** Bekannt ist, dass `Mcp::oauthRoutes()`
  eigene `/.well-known`-Routen überschreibt; Cardbox hat keine. Ob die
  Zustimmungsseite von Passport sich vollständig durch eine eigene ersetzen
  lässt, wird im Durchstich geprüft.
- **Unterschiede zwischen den Assistenten:** Claude und ChatGPT nutzen
  dynamische Registrierung oder Client-Metadaten-Dokumente; ChatGPT braucht
  den Entwicklermodus eines passenden Abos; Gemini bindet eigene Server nur
  über gemini.google.com und nicht mit Arbeits- oder Schulkonten an. Getestet
  wird mit allen dreien, bevor die Funktion als fertig gilt.
- **Aufwand:** mehrere Tage. Die Werkzeuge sind dank der Services
  überschaubar; OAuth, Zustimmung, Verwaltung, Tests und eine gründliche
  Sicherheitsprüfung machen den größten Teil aus.
- **Annahmen ohne Rückfrage:** Lebensdauer der Tokens (1 h / 30 Tage), Liste
  und Umfang der Werkzeuge, 60 Aufrufe pro Minute, 100 Karten je Aufruf,
  `MCP_ENABLED` standardmäßig aus, Admins können Verbindungen trennen.
