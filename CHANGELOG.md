# Änderungen

Neueste Version oben. Eine Überschrift je Version im Format `JJJJ.MM.TT`,
bei mehreren Veröffentlichungen am selben Tag mit angehängter Zählung (`.2`).
Die oberste Überschrift ist die Version, die die Anwendung von sich meldet.

Einträge sind reiner Text — Markdown darin wird nicht ausgewertet.
Nach der Version darf Zusatztext stehen (`## 2026.10.11 — Herbstausgabe`).
Zeilen, die mit `Migration:` beginnen, werden getrennt hervorgehoben — sie
müssen vor dem Deployment eingespielt werden (das erledigt der Start selbst).

## 2026.10.11.2
- Festes Vorgehen für KI-Assistenten: /spec und /deploy, zwei Subagents zur Durchsicht
- Ein Hook führt vor jedem Commit just check aus und weist datenlöschende Befehle ab
- Spezifikationen unter docs/specs/

## 2026.10.11
- Version und Änderungen unter System sichtbar, Version zusätzlich in der Fußzeile
- Hinweis auf neuere Versionen, wenn eine Prüfadresse hinterlegt ist
- Migration: 2026_10_11_000000_create_settings_table.php

## 2026.09.14
- Erste Fassung des Kits: Anmeldung, Rollen, Benutzerverwaltung
- App-Tokens mit Scopes und JSON-Schnittstelle unter /api/v1
- Installierbare App (PWA), E-Mail-Versand, Systemseite, Styleguide
