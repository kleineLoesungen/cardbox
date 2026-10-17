# Änderungen

Neueste Version oben. Eine Überschrift je Version im Format `JJJJ.MM.TT`,
bei mehreren Veröffentlichungen am selben Tag mit angehängter Zählung (`.2`).
Die oberste Überschrift ist die Version, die die Anwendung von sich meldet.

Einträge sind reiner Text — Markdown darin wird nicht ausgewertet.
Zeilen, die mit `Migration:` beginnen, werden getrennt hervorgehoben.

## 2026.10.17.3
- Erinnerung: Unter Mein Konto lässt sich eine tägliche Mitteilung einschalten („12 Karten warten auf dich“), zu einer wählbaren Uhrzeit. Sie kommt nur, wenn Karten warten und das Tagesziel offen ist. Auf dem iPhone nur, wenn Cardbox auf dem Home-Bildschirm liegt
- Betrieb: Für Erinnerungen braucht es VAPID-Schlüssel (php artisan push:keys), REMINDER_TOKEN und einen Aufruf alle 15 Minuten (z. B. Cronicle → POST /api/v1/reminders/run mit X-Cron-Token). Ohne Einrichtung bleibt die Funktion aus. Anleitung in docs/INSTALL-WEBSPACE.md
- Migration: 2026_10_17_100000_create_push_subscriptions_table.php

## 2026.10.17.2
- Antworten eintippen: Im Stapel unter ⋯ einschaltbar. Statt umzudrehen schreibt man die Antwort; Groß/Klein ist egal, jede Bedeutung zählt, Teile in Klammern sind freiwillig. Bei kleinen Tippfehlern sagt die App „Fast!“ und zeigt die richtige Schreibweise – beim Lernen zählt das als gewusst, beim Test üben kommt das Wort noch einmal
- Migration: 2026_10_17_000000_add_type_answers_to_deck_preferences.php

## 2026.10.17
- Neue Wörter zeigt die App zuerst offen (vorne und hinten, zum Anhören) und fragt sie ein paar Karten später ab – niemand muss mehr ein Wort raten, das er nie gesehen hat
- Tagesziel: Ein Ring unter „Heute“ zeigt, wie viele Karten heute gewusst wurden (auch beim Test üben; eine Karte zählt je Runde einmal, „Nochmal“ zählt nicht). Das Ziel – 10, 20 oder 30 Karten – steht unter Mein Konto → Lernplan
- Die Lernserie zählt Tage mit erreichtem Tagesziel – oder an denen es nichts mehr zu lernen gab. Bisherige Lerntage zählen weiter
- „Du kannst schon 124 Wörter“ unter „Heute“, und am Rundenende die Wörter, die neu in „Kann ich“ sind
- Migration: 2026_10_16_000000_add_daily_goal_and_goal_met.php

## 2026.10.15.2
- Einfacher lernen: „Heute“ → „Los geht's“ bringt jetzt auch neue Karten aus allen Stapeln, bis zu 20 am Tag – mehr mit „Noch 20 neue Karten“. Gelernt werden muss nicht mehr im Stapel
- Geteilte Stapel und Wörter im Wörterbuch lernt man mit einem Knopf „Lernen“ (statt „Folgen“). Eine eigene Fassung („Kopieren“) und Übernehmen stehen im ⋯-Menü bzw. unter „Mehr …“
- Stapelseite aufgeräumt: kein Lernplan-Wechsel und keine Richtungswahl mehr. Neu: „Nur diesen Stapel lernen“, „Für den Test üben“ (alle Karten, bis jede einmal gewusst ist – zählt nicht) und im ⋯-Menü „Auch rückwärts lernen“
- Der Lernplan steht jetzt unter „Mein Konto“ statt im Menü. Schon gestartete zusätzliche Lernpläne eines Stapels laufen weiter
- Wer schon rückwärts gelernt hat, behält das: „Auch rückwärts lernen“ ist für diese Stapel eingeschaltet
- Migration: 2026_10_15_000000_create_deck_preferences_table.php

## 2026.10.15
- Wörterbuch: Eigene Karten lassen sich direkt dort bearbeiten und löschen. Steht ein Wort in mehreren Stapeln, wählt man einen Stapel oder alle auf einmal. Bei fremden Karten gibt es „Fehler melden“
- Moderation und Verwaltung können im Wörterbuch auch Karten in geteilten Stapeln anderer bearbeiten und löschen; jede solche Änderung wird protokolliert
- Verwaltung: Auf der Seite eines Kontos lassen sich dessen Stapel – auch private – an ein anderes Konto übertragen, z. B. bevor man das Konto löscht. Bei geteilten Stapeln folgt die bisherige Besitzerin danach und lernt weiter

## 2026.10.14.2
- Nur einen Teil eines Stapels lernen: Unter ⋯ → „Karten auswählen“ Karten anhaken (mit „bis hier“ für ganze Kapitel). Die anderen sind pausiert – sie kommen nicht dran, ihr Lernstand bleibt. Neue Karten der Besitzerin bleiben pausiert, bis man sie anhakt. Für eigene und gefolgte Stapel
- Wörterbuch: Die erste Aktion ist jetzt „Folgen und diese Karte lernen“ – man folgt dem Stapel und lernt erst einmal nur diese Karte. Die Kopie in einen eigenen Stapel gibt es weiter als zweite Möglichkeit
- Migration: 2026_10_14_100000_create_deck_selections_tables.php

## 2026.10.14
- Mehrere Stapel auf einmal: Unter Stapel → Meine → „Stapel exportieren“ eigene und gefolgte Stapel ankreuzen und als ZIP laden (je Stapel eine Tabelle plus Übersicht mit Name, Beschreibung und Sprachen)
- „Stapel importieren“ nimmt die ZIP wieder an: eigene Stapel und eigene Kopien werden aktualisiert (nichts gelöscht, Lernstand bleibt), alle anderen kommen als neue private Stapel dazu. Ein zweiter Import derselben Datei legt keine Doppelten an
- Server: die PHP-Erweiterung zip ist dafür nötig; der Deploy zeigt, ob sie da ist (freiwillig)
- Migration: 2026_10_14_000000_add_source_deck_id_to_decks_table.php

## 2026.10.13.13
- Stapel suchen: Name und Beschreibung werden auch ohne Bindestriche und Leerzeichen verglichen – eine ISBN wie 978-3-12-834211-5 findet man auch als 9783128342115. Entdecken zeigt die Beschreibung in der Trefferliste
- Suchfeld auch unter „Meine“ (eigene und gefolgte Stapel)
- Hilfe: neuer Abschnitt „Tabellen (CSV)“ mit Format, Beispiel, Regeln und allen Sprachkürzeln; „Liste einfügen“ verlinkt dorthin

## 2026.10.13.12
- Wörterbuch auch in der unteren Leiste auf dem Telefon
- Die Kopfzeile der App zeigt das Icon statt des Anfangsbuchstabens

## 2026.10.13.11
- Moderation: „Doppelte Karten“ listet gleiche Wortpaare in geteilten Stapeln. Die Moderation wählt die Karte, die bleibt, kann ihren Inhalt ergänzen und führt zusammen: Doppelte im selben Stapel werden gelöscht (der weiter fortgeschrittene Lernstand bleibt), Doppelte in anderen Stapeln übernehmen den Inhalt und bekommen spätere Korrekturen angeboten

## 2026.10.13.10
- Moderation und Verwaltung können Karten in fremden geteilten Stapeln korrigieren („Bearbeiten (Moderation)“ an der Karte). Wer mit dem Stapel lernt, sieht „geändert“, der Lernstand bleibt. Einzelne Karten löschen können sie nicht; jede Korrektur wird protokolliert

## 2026.10.13.9
- Wörterbuch unter Mehr: ein Wort nachschlagen, ohne zu lernen – in eigenen Stapeln und allen geteilten. Treffer zeigen Übersetzung, weitere Bedeutungen und Hinweis, lassen sich vorlesen und in einen eigenen Stapel übernehmen

## 2026.10.13.8
- Neues Icon: ein Karteikasten mit Karten. Installierte Web-Apps zeigen es, sobald das Gerät das Manifest neu lädt
- Cardbox steht jetzt unter der MIT-Lizenz: kostenlos und frei nutzbar
- Produktseite unter cardbox.se-wi.com (eigene statische Seite, nicht Teil der App)

## 2026.10.13.7
- CSV: Stapel lassen sich unter ⋯ → „Als CSV exportieren“ als Tabelle herunterladen (Spalten ID, Sprachen, Hinweis; mehrere Bedeutungen mit |). Öffnet sich in Excel und Numbers direkt richtig
- Eine bearbeitete CSV über „Liste einfügen“ wieder hochladen: bekannte Karten werden geändert und behalten ihren Lernstand, neue Zeilen werden neue Karten, gelöscht wird nichts. Die Vorschau zeigt neu, geändert und unverändert
- Administratoren können über „Liste einfügen (Karten überschreiben)“ auch in fremde geteilte Stapel importieren

## 2026.10.13.6
- Neue Startseite für Gäste im Stil der Karten: eine Lernkarte zum Umdrehen mit Beispielwörtern, „So geht's“ als Karteikarten, der Karteikasten mit einer wandernden Karte und die Funktionen im Überblick

## 2026.10.13.5
- Deploy (FTP und Webserver) prüft die .env vor dem Ausrollen: Werte mit # oder Leerzeichen ohne Anführungszeichen, die Laravel abgeschnitten lesen würde, brechen mit dem Namen des Eintrags ab. Typischer Fall: ein Mail-Passwort mit #, das zu „535 Incorrect authentication data“ führt

## 2026.10.13.4
- Fehler melden: Wer mit einem geteilten Stapel lernt, kann bei einer Karte einen Fehler melden oder eine Verbesserung vorschlagen (Art, neuer Text, Kommentar). Höchstens ein offener Vorschlag je Person und Karte
- Die Besitzerin sieht offene Vorschläge am Stapel und unter Meine Stapel, übernimmt sie mit einem Tipp in die Karte oder lehnt sie ab. Wer vorgeschlagen hat, sieht den Stand an der Karte
- Die Hilfe erklärt „Fehler melden“, Änderungen im Original, Hauptbedeutung und Hinweis
- Migration: 2026_10_13_200000_create_card_suggestions_table.php

## 2026.10.13.3
- Kopierte und übernommene Karten merken sich ihr Original. Ändert die Besitzerin das Original, zeigt der eigene Stapel „… Karten wurden im Original geändert“; je Karte lässt sich die Änderung übernehmen oder die eigene Fassung behalten, oder alle auf einmal übernehmen. Der Lernstand bleibt
- Beim Lernen zeigt „geändert“, dass eine Karte seit dem letzten Lernen bearbeitet wurde
- Migration: 2026_10_13_150000_add_source_to_cards_table.php

## 2026.10.13.2
- Hauptbedeutung: Die erste Bedeutung einer Kartenseite ist die Hauptbedeutung. Auf der Lernkarte steht sie groß, weitere klein als „auch: …“; Vorlesen liest nur die Hauptbedeutung
- Beim Bearbeiten einer Karte rückt ☆ eine andere Bedeutung nach oben und macht sie zur Hauptbedeutung
- Hinweis zur Karte (freiwillig, bis 100 Zeichen), z. B. „(Zug)“ oder „ugs.“: steht beim Lernen unter der Frage und in der Kartenliste; Kopieren und Übernehmen nehmen ihn mit
- Migration: 2026_10_13_100000_add_hint_to_cards_table.php

## 2026.10.13
- Lernplan je Stapel: Ein Stapel lässt sich mit einem anderen Lernplan starten (Normal, Schnell, Gründlich), z. B. „Schnell“ vor einer Arbeit, ohne den Standard unter Mehr → Lernplan zu ändern
- Ein Stapel kann mit mehreren Plänen gleichzeitig laufen; jeder Plan hat seinen eigenen Lernstand. In der Stapelansicht schaltet eine Leiste zwischen ihnen um
- Der Lernplan eines begonnenen Stapels bleibt fest. Ein geänderter Standard gilt nur für neu gestartete Stapel
- „Fortschritt zurücksetzen“ löscht den Lernstand des gewählten Plans; danach lässt sich der Stapel neu starten, auch mit anderem Plan
- „Los geht's“ fragt eine Karte, die in zwei Plänen fällig ist, nur einmal; die Antwort zählt für beide
- Vorhandener Lernstand wird beim Update übernommen: je begonnenem Stapel ein Plan mit dem bisherigen Standard
- Migration: 2026_10_13_000000_create_deck_runs_table.php

## 2026.10.12.5
- Deploy per FTP: Der erste Deploy auf eine leere Datenbank scheiterte mit einem Serverfehler, weil die Drosselung des Deploy-Aufrufs eine Tabelle brauchte, die erst die Migration anlegt. Die Drosselung zählt jetzt in Dateien unter storage/
- deploy.sh zeigt Fehlermeldungen des Servers an und sagt, wo das Log liegt

## 2026.10.12.4
- Deploy per FTP prüft nach dem Hochladen, ob die Domain wirklich auf FTP_DIR zeigt, und sagt sonst, was zu tun ist
- Deploy per FTP lässt den Ordner .well-known auf dem Server stehen (Let's-Encrypt-Prüfung des Hosters)

## 2026.10.12.3
- Deploy per FTP: Das Token kommt auch bei Hostern an, die den Authorization-Header verwerfen (PHP über CGI/FastCGI). Bei 404 sagt deploy.sh, ob das Token nicht passt oder die Adresse Cardbox gar nicht erreicht

## 2026.10.12.2
- Webspace: Die Domain darf jetzt direkt auf den Upload-Ordner zeigen (z. B. public_html/cardbox), wie es Hoster mit nur public_html verlangen. Eine .htaccess dort reicht alle Anfragen an public/ weiter; .env und Code bleiben unerreichbar
- FTP_DIR in .env.deploy muss jetzt gesetzt sein, Vorlage: public_html/cardbox
- Die Let's-Encrypt-Prüfung unter /.well-known funktioniert auch in diesem Ordner

## 2026.10.12
- Eigenes PostgreSQL-Schema über DB_SCHEMA (Standard: public), z. B. wenn der Hoster ein Schema vorgibt oder sich mehrere Anwendungen eine Datenbank teilen
- Der Deploy per FTP legt ein fehlendes Schema an, wenn das Datenbankkonto das darf, und meldet ungültige Namen

## 2026.10.11.4
- Lernplan unter Mehr: Normal, Schnell (vor Prüfungen), Gründlich oder ein eigener Plan mit 2–10 Fächern und eigenen Abständen
- Hilfe unter Mehr: So funktioniert der Karteikasten, Folgen oder kopieren, Listen einfügen, App auf den Startbildschirm
- In der Lernrunde: Vorlesen (wenn das Gerät eine passende Stimme hat) und Wischen – rechts gewusst, links nochmal
- Hilfe „Vorlesen funktioniert nicht?“: wie man auf iPhone, Android, Mac und Windows fehlende Stimmen lädt; die Lernrunde verlinkt dorthin, wenn eine Stimme fehlt
- Rückfragen vor dem Löschen als eigenes Fenster mit klar benanntem Knopf statt der Browser-Rückfrage
- „Zurück zur Liste“ in der Import-Vorschau behält den eingefügten Text
- Installationsanleitung für einen eigenen Server in der README, mit fertiger Konfiguration in deploy/ (Cardbox, PostgreSQL, Caddy mit automatischem HTTPS)
- Installationsanleitung ohne Container für nginx/Apache mit vorhandener PostgreSQL (docs/INSTALL-WEBSERVER.md) und Skript scripts/deploy-webserver.sh für Installation und Updates
- Deploy auf Webspace nur mit FTP: ./deploy.sh mit .env.deploy baut, lädt per FTPS hoch und migriert über einen per DEPLOY_HOOK_TOKEN geschützten Aufruf (docs/INSTALL-WEBSPACE.md)
- Mindestversion PHP 8.4.1 (vorher fälschlich 8.3 angegeben; die Abhängigkeiten brauchen 8.4.1)
- Behoben: Auf einer leeren Datenbank startete der Container nicht (Migrationen brauchten eine Sperr-Tabelle, die erst durch die Migrationen entsteht)

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
