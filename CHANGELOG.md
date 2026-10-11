# Änderungen

Neueste Version oben. Eine Überschrift je Version im Format `JJJJ.MM.TT`,
bei mehreren Veröffentlichungen am selben Tag mit angehängter Zählung (`.2`).
Die oberste Überschrift ist die Version, die die Anwendung von sich meldet.

Einträge sind reiner Text — Markdown darin wird nicht ausgewertet.
Zeilen, die mit `Migration:` beginnen, werden getrennt hervorgehoben.

## 2026.10.11.3
- Lernen mit dem Karteikasten: Karten umdrehen, „Gewusst“ oder „Nochmal“, gewusste Karten rücken ein Fach weiter
- Nicht gewusste Karten kommen am Ende der Runde noch einmal (höchstens dreimal)
- „Heute“ zeigt, wie viele Karten fällig sind, startet mit „Los geht's“ eine Runde über alle Stapel und zeigt die Lernserie
- Im Stapel: Richtung wählen, Fächer mit Anzahl (antippen zeigt die Karten), Lernen und Freies Üben ohne Wertung
- Freies Üben auch nur für ein Fach: im Blatt hinter der Fach-Kachel „Diese Karten üben“
- Fortschritt eines Stapels zurücksetzen; beim Kopieren wird der eigene Lernstand mitgenommen
- Fälligkeiten richten sich nach dem Kalendertag in LEARNING_TIMEZONE (Standard Europe/Berlin)
- Startseite nach der Anmeldung ist jetzt /learn; /dashboard leitet dorthin weiter
- Migration: 2026_10_11_110000_create_learning_tables.php

## 2026.10.11.2
- Stapel mit Vokabelkarten: anlegen, umbenennen, Sprachen vorne und hinten, privat oder mit allen geteilt
- Karten schnell eintippen („Speichern & nächste“), mehrere Bedeutungen je Seite, bearbeiten und löschen
- Liste einfügen: viele Karten auf einmal aus Text, Excel/Word oder einer Datei, mit Vorschau und Fehlerhinweisen je Zeile
- Geteilten Stapeln anderer folgen (bleibt aktuell) oder sie als eigenen Stapel kopieren
- Einzelne Karten aus eigenen und geteilten Stapeln übernehmen (als Kopie)
- Moderation: Moderatoren sehen alle geteilten Stapel und können das Teilen beenden oder sie löschen
- Neue Navigation fürs Telefon: Heute, Stapel, Mehr; das Notizen-Beispiel ist aus dem Menü verschwunden
- Eingabefelder auf dem Telefon in 16 px, damit iPhones beim Antippen nicht hineinzoomen
- Migration: 2026_10_11_100000_create_decks_and_cards_tables.php

## 2026.10.11
- Cardbox angelegt: Neuaufbau von vocaBox als Vokabel-Karteikasten fürs Smartphone
- Anmeldung mit Benutzername, Konten haben keine E-Mail-Adresse
- Jede Person kann sich unter /register ein Konto anlegen, nutzbar erst nach Freigabe durch einen Administrator; das erste Konto wird Administrator
- Wartende Konten erscheinen unter Benutzer mit Zähler; nicht freigegebene werden nach 30 Tagen gelöscht
- Knopf „Passwort vorschlagen" erzeugt merkbare Passwörter aus drei Wörtern und einer Zahl
- Migration: 2026_10_11_090000_replace_email_with_username_on_users_table.php
