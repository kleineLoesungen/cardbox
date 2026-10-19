# Zurück und Bereiche

Status: **umgesetzt** (19.10.2026)

Auftrag: „Bei Zurück-Buttons springe ich zwischen Heute- und Stapel-Ansicht.
Implementiere ein stringentes Konzept für die komplette App.“ Dazu Issue #19
(„Menü und Buttons unten zu knapp am Rand“).

## Die Regel

1. **Bereiche** sind die Seiten aus der unteren Leiste und dem Menü:
   Heute, Stapel, Wörterbuch, Hilfe, Mein Konto, Meine Kinder, Moderation,
   Benutzer, Tokens, System. Sie haben **keinen** Zurück-Link; man wechselt
   über Leiste und Menü. Seiten mit festem Platz (Lernplan unter Mein Konto,
   Konten unter Benutzer, Moderations- und Kinder-Unterseiten) markieren
   immer ihren Bereich.
2. **Zurück führt dorthin, woher man kam.** Wer einen Bereich öffnet, dessen
   Adresse samt Ansicht (z. B. „Entdecken“) merkt sich die App. Eine Seite
   darunter, vor allem die **Stapelseite**, führt mit „← Heute“, „← Stapel“
   oder „← Wörterbuch“ genau dorthin zurück. Gemerkt wird nur eine Seite,
   die wirklich angezeigt wurde (kein 403, keine Weiterleitung), und der
   Bereich gilt, bis ein anderer geöffnet wird – auch über einen geteilten
   Link. Ohne gemerkten Bereich (erste Seite nach dem Anmelden) gilt der
   natürliche: „← Stapel“.
3. **Leiste und Menü markieren denselben Bereich.** Eine Stapelseite, die
   man von „Heute“ aus geöffnet hat, zeigt „Heute“ als aktiv, nicht
   „Stapel“. Für Bereiche aus dem Menü ist „Mehr“ markiert.
4. **Seiten unter einer Seite** führen zu dieser Seite zurück, mit deren
   Namen: „Karten auswählen“, „Liste einfügen“, „Vorschläge“, „Änderungen im
   Original“ → „← Name des Stapels“; „Lernplan“ → „← Mein Konto“;
   „Doppelte Karten“ → „← Moderation“; ein Konto → „← Alle Benutzer“.
5. **Lernrunde:** ✕ und „Fertig“ führen dorthin, wo die Runde gestartet
   wurde: „Los geht's“ → Heute, alles auf der Stapelseite (Lernen, Üben,
   Fach üben) → Stapelseite. „Zum Stapel“ am Ende entfällt dadurch.
6. **Meine Kinder:** Bei einem Kind öffnet „Meine Kinder“ direkt dessen
   Ansicht; dort gibt es kein Zurück. Bei mehreren: „← Meine Kinder“.

Gemerkt wird nur beim Aufruf einer ganzen Seite (GET, kein htmx-Fragment)
in der Sitzung; der Zurück-Weg ist also je Gerät und Anmeldung.

## Rand unten (Issue #19)

- `viewport-fit=cover` im Viewport-Tag: Erst damit meldet iOS den Bereich
  der Wischleiste (`env(safe-area-inset-bottom)`). Vorher war er 0, und die
  untere Leiste, der Knopf „Neuer Stapel“ und die Knöpfe der Lernrunde lagen
  direkt an der Wischzone.
- Ein gemeinsamer Abstand `--safe-bottom` (mindestens 0,5 rem, auf dem
  iPhone die Wischleiste) für Leiste, schwebende Knöpfe, Meldungen, Blätter
  und die Lernrunde (dort zusätzlich 2,5 rem, weil keine Leiste darunter
  liegt). Seitlich halten Inhalt, Leiste, schwebender Knopf und
  Blätter im Querformat Abstand zur Notch; Kopfzeile und Lernrunde oben
  Abstand zur Statusleiste, falls ein Gerät dort einen Rand meldet.

## Betroffene Stellen

- `app/Support/Navigation.php` (Bereiche, merken, aktueller Bereich,
  Zurück-Ziel), `app/Http/Middleware/RememberSection.php` (Web-Gruppe).
- `AppServiceProvider`: `$navSection` für `layouts/app`.
- `components/back-link.blade.php`, `components/nav-links.blade.php`,
  `layouts/app.blade.php` (Leiste, Menü, Abstand unten),
  `layouts/base.blade.php` (Viewport), `decks/show`, `children/show`,
  `learn/partials/round-body` (✕, Fertig), `StudyRoundController`
  (`study_round_return`).
- `resources/css/components.css`: `--safe-bottom`.

Neue Menüpunkte gehören zusätzlich als Bereich in `Navigation::SECTIONS`.

## Tests

`tests/Feature/NavigationTest.php`: Zurück und Markierung von Heute, Stapel,
Entdecken und Wörterbuch aus; ohne gemerkten Bereich; Fragmente und
Unterseiten ändern nichts; Lernrunde endet am Start; Meine Kinder mit einem
und mehreren Kindern; Viewport-Tag.
