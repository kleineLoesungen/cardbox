# Änderungen

Neueste Version oben. Eine Überschrift je Version im Format `JJJJ.MM.TT`,
bei mehreren Veröffentlichungen am selben Tag mit angehängter Zählung (`.2`).
Die oberste Überschrift ist die Version, die die Anwendung von sich meldet.

Einträge sind reiner Text — Markdown darin wird nicht ausgewertet.
Zeilen, die mit `Migration:` beginnen, werden getrennt hervorgehoben.

## 2026.10.28
- Lernserie: Ein ausgelassener Tag beendet sie nicht mehr, wenn man am Tag danach das Tagesziel zweimal schafft – der ausgelassene Tag zählt dann mit. „Heute“, das Rundenende und die Erinnerung sagen, wie viele Karten dafür noch fehlen, auch nach dem ersten erreichten Ziel. Zwei Tage am Stück ohne Lernen beenden die Serie weiterhin
- Heute: Jeder Stapel zeigt einen Balken mit Farben für „kann ich“, „fast geschafft“ (im Fach direkt vor „Kann ich“), „im Karteikasten“ und „noch nicht gelernt“ – vorher blieb er dunkel, solange noch nichts in „Kann ich“ war. „Fast geschafft“ gibt es jetzt auch in der Ansicht für Eltern
- Wörterbuch: Nach dem Speichern oder Löschen einer Karte schließt sich das Fenster wieder

## 2026.10.23
- Angemeldet bleiben: Eine Anmeldung gilt jetzt 30 Tage und verlängert sich mit jeder Nutzung – wer die App einmal in der Woche öffnet, muss sich nicht mehr neu anmelden (vorher nach 12 Stunden)
- Neues Passwort meldet ab: Wer sein Passwort ändert (oder es von einer Administratorin zurücksetzen lässt), ist auf allen anderen Geräten abgemeldet – das eigene bleibt angemeldet. Hilft, wenn man auf einem fremden Gerät angemeldet geblieben ist
- Betrieb: Steht SESSION_LIFETIME=720 in der .env, auf 43200 ändern oder die Zeile löschen – sonst bleibt es bei 12 Stunden. Sitzungen ohne Anmeldung (Anmeldeseite, Gäste) werden nach einem Tag weggeräumt
- Untere Leiste am iPhone: Solange ein Textfeld aktiv ist, verschwinden Leiste und „+“-Knopf, statt mit der Tastatur zu wandern; nach dem Schließen der Tastatur rückt die Seite wieder zurecht, in der App vom Home-Bildschirm schwingt sie nicht mehr nach
- Duell: Über der Frage und den Antworten steht die Sprache (z. B. „Deutsch“, „Englisch“). Gefragt wird immer die Vorderseite, alle Antworten sind Rückseiten aus dem Stapel

## 2026.10.21.2
- Am Handy leichter zu treffen: Knöpfe, Eingabefelder und Auswahllisten sind mindestens 48 Pixel hoch, große Knöpfe 56. Dazu größer: der Zurück-Link über Unterseiten, kleine Knöpfe wie „Pausieren“, „Alle/Keine“, „bis hier“, Links wie „Alle“ auf Heute oder „Liste einfügen“, die Häkchen bei „Karten auswählen“ und der Knopf zur Dateiauswahl beim Import

## 2026.10.21
- Zeiten stimmen wieder, wenn der Datenbankserver nicht auf UTC läuft (z. B. deutsche Zeit): Vorher lagen gespeicherte Zeitpunkte zwei Stunden daneben – im Duell zählte dadurch jede Karte die volle Minute, Fristen liefen zwei Stunden zu früh ab. Die Verbindung spricht jetzt immer UTC; die Migration rückt vorhandene Zeitpunkte zurecht und tut auf einem UTC-Server nichts. Schon gespielte Duell-Runden behalten ihre Zeit von 10 Minuten
- Stapelseite: „Für den Test üben“, „Knifflige Wörter üben“ und „Duell starten“ sind jetzt Knöpfe mit Symbol; „Nur diesen Stapel lernen“ ist hervorgehoben und größer
- Duell: Wer nur einen Teil eines Stapels lernt, bekommt im Duell nur diese Karten – das Duell-Fenster sagt das jetzt auch
- Duell: Die Duellseite zeigt den Stand von selbst (alle 3 Sekunden, solange das Duell läuft, ausgetauscht wird nur bei Änderungen) – wer wartet, sieht ohne Neuladen, wann eine Runde vorbei ist, die nächste läuft oder wer gewonnen hat
- Duell: „Einladung teilen“ unter dem Code schickt Text und Link per Chat (am Handy über das Teilen-Menü, sonst kopiert). Der Link füllt das Code-Feld aus
- Betrieb: Neue öffentliche Route GET /duell/{Link}/live für Gäste, mit eigener Bremse je Link (120 Abfragen pro Minute), damit offene Tabs nie das Antworten blockieren
- Migration: 2026_10_21_000000_repair_times_written_in_server_zone.php

## 2026.10.20.2
- Duell mit Runden: Beim Anlegen 1 bis 5 Runden wählen, je 10 Wörter. Wörter wiederholen sich erst, wenn der Stapel durch ist. Nach jeder Runde (alle haben gespielt, spätestens nach 24 Stunden) erscheint die Rangliste, dann startet die Erstellerin innerhalb von 24 Stunden die nächste Runde. Wer über alle fertig gespielten Runden die meisten Wörter weiß, gewinnt, bei Gleichstand die kürzere Zeit; eine abgebrochene Runde zählt nicht. Einsteigen nur in Runde 1. „Revanche“ entfällt – stattdessen Runden
- Migration: 2026_10_20_100000_add_rounds_to_duels.php

## 2026.10.20
- Duell: Auf der Seite eines Stapels „Duell starten“ – Spielerzahl (2 bis 10) und Abfrage („Auswahl aus 4“ oder „Eintippen“) wählen, Code weitergeben. Alle bekommen dieselben 10 Wörter, wer mehr weiß, gewinnt, bei Gleichstand die kürzere Zeit. Jede und jeder spielt einmal, innerhalb von 24 Stunden; das Duell endet, sobald alle gespielt haben. Mitspielen unter Mehr → Duelle oder ohne Konto unter /duell (mit Name und persönlichem Link). Die Erstellerin kann Mitspieler entfernen, „Revanche“ startet ein neues Duell
- Betrieb: /duell und die Gast-Links sind öffentlich (gebremst je IP, nur mit gültigem Code und freiem Platz); Gäste bekommen kein Konto. Das Ergebnis bleibt 24 Stunden nach dem Ende sichtbar, auch für alle mit dem Code; danach werden Duell, Code und Ergebnisse gelöscht
- Migration: 2026_10_20_000000_create_duel_tables.php

## 2026.10.19.7
- Handys laden das Aussehen (app.css) nach einem Update jetzt sicher neu. Vorher konnte iOS eine Weile die alte Datei behalten – dann fehlten neue Abstände, und die Knöpfe der Lernrunde standen weiter ganz unten

## 2026.10.19.6
- Lernrunde: Die Knöpfe unten (Umdrehen, Gewusst/Nochmal, Prüfen, Weiter) stehen höher und damit weiter weg vom unteren Rand

## 2026.10.19.5
- Zurück einheitlich: Eine Stapelseite führt dorthin zurück, woher man kam („← Heute“, „← Stapel“, „← Wörterbuch“), und die untere Leiste markiert diesen Bereich. Eine Lernrunde endet mit ✕ oder „Fertig“ dort, wo sie gestartet wurde
- iPhone: Untere Leiste, „Neuer Stapel“ und die Knöpfe der Lernrunde halten Abstand zur Wischleiste am unteren Rand; im Querformat auch zur Notch (Issue #19)

## 2026.10.19.4
- Einfacher: Jeder Stapel ist „lerne ich“, „pausiert“ oder keins von beiden. Nur „lerne ich“ kommt auf Heute, in „Los geht's“, in die Erinnerung und in die Eltern-Ansicht. Umgeschaltet wird direkt oben auf der Stapelseite („Pausieren“, „Wieder lernen“, „Lernen“), nicht mehr im Menü
- Neue Stapel und geteilte Stapel nach „Lernen“ sind gleich „lerne ich“. Üben (Für den Test üben, Knifflige Wörter, Fach üben) ändert nichts
- Stapel → Meine: getrennt nach „Lerne ich“, „Pausiert“ und „Weitere Stapel“; Entdecken zeigt „lerne ich“ bzw. „pausiert“
- Eigene Stapel ohne Lernstand sind nach dem Update „Weitere Stapel“ und nicht mehr auf Heute; Stapel mit Karten in den Fächern und gelernte geteilte Stapel bleiben „lerne ich“
- Migration: 2026_10_19_100000_add_learning_to_deck_preferences.php

## 2026.10.19.3
- Betrieb: Die 20 Migrationen bis Version 2026.10.18 sind zu einer zusammengefasst. Für eine bestehende Datenbank ab Stand 2026.10.18 ist nichts zu tun: Die neue Migration erkennt die vorhandenen Tabellen und wird nur als erledigt vermerkt. Eine ältere Datenbank bricht mit einer Meldung ab und muss erst mit Version 2026.10.19.2 aktualisiert werden. Neue Installationen legen das Schema in einem Schritt an
- Migration: 0001_01_01_000000_create_cardbox_schema.php (ändert bestehende Datenbanken nicht)

## 2026.10.19.2
- Stapel pausieren: Im Stapel unter ⋯ „Gerade nicht lernen“. Pausierte Stapel kommen nicht auf Heute, nicht in „Los geht's“, nicht in die Erinnerung; die Eltern-Ansicht nennt nur ihre Anzahl. Auf der Stapelseite bleiben sie lernbar (Issue #15)
- Neuer Kartentyp „Vorderseite → Rückseite“ für alles, was keine Sprache ist (Hauptstädte, Fachbegriffe …). Kein Vorlesen, Eintippen tolerant wie bei Sprachen (Issue #14)
- Karte hinzufügen: neben „Speichern & nächste“ auch „Speichern & schließen“ (Issue #16)
- Karten auswählen: „Auswahl speichern“ bleibt beim Scrollen sichtbar (Issue #17)
- Eltern-Ansicht: erklärt die Kalenderkacheln, zeigt das Datum darin und je Stapel einen Balken mit „kann“, „im Karteikasten“ und „noch nicht gelernt“ (Issue #18)
- Migration: 2026_10_19_000000_add_paused_to_deck_preferences.php

## 2026.10.19
- Antworten eintippen auf dem Handy: Die Karte ist kleiner und steht oben, das Eingabefeld direkt darunter. So bleibt das Wort lesbar, wenn die Tastatur aufgeht
- Heute: Die grünen Punkte der Woche zeigen, wie oft das Tagesziel erreicht wurde (Ziel 20: bis 39 gewusste Karten eine 1, ab 40 eine 2 usw.), gemessen am aktuellen Tagesziel
- Hellmodus: Das Grün für „geschafft“ ist etwas dunkler und damit besser lesbar

## 2026.10.18.4
- Sicherheitsupdates: Laravel 13.37, Symfony 8.1.9 (manipulierter Cache-Control-Header konnte den Server lange beschäftigen) und league/commonmark 2.10.4. composer audit meldet keine bekannten Lücken mehr

## 2026.10.18.3
- Heute: Sieben Punkte zeigen die letzten 7 Tage – grün mit erreichtem Tagesziel, grau ohne
- Startseite für Gäste: nennt jetzt auch Tagesziel, Antworten eintippen, knifflige Wörter und die Eltern-Ansicht, die Erinnerung nur, wenn sie eingerichtet ist

## 2026.10.18.2
- Eltern sehen den Fortschritt: Das Kind erzeugt unter Mein Konto → Eltern einen Code (gilt 24 Stunden, nur einmal). Ein Elternteil gibt ihn unter „Kind hinzufügen“ ein und sieht dann unter „Meine Kinder“ Lerntage der letzten vier Wochen, Tagesziel, Wortschatz, Stapel mit Fortschritt und knifflige Wörter – nur ansehen, keine einzelnen Antworten. Das Kind sieht, wer zuschaut, und kann es jederzeit beenden. Kein Admin nötig, keine neue Rolle
- Migration: 2026_10_18_100000_create_guardian_tables.php

## 2026.10.18
- Karten je Runde: Unter Mein Konto wählbar (5, 10 oder 20). Kürzere Runden sind schneller geschafft
- Knifflige Wörter: Karten, die dreimal falsch waren, sind im Stapel markiert und lassen sich mit „Knifflige Wörter üben“ gezielt wiederholen. Am Rundenende ein Anstoß zur Eselsbrücke
- Antworten eintippen auf dem iPhone: Nach „Weiter“ bleibt die Tastatur offen, man muss nicht mehr jedes Mal ins Feld tippen
- Erinnerung: kommt jetzt auch, wenn der Aufruf (z. B. Cronicle) genau zur eingestellten Uhrzeit kommt und die Server-Uhr einen Moment nachgeht. Vorher wurde sie dann still übersprungen
- Erinnerung: Ist die Einrichtung fehlerhaft (Schlüssel oder VAPID_SUBJECT), antwortet die Aufruf-Adresse jetzt mit Fehler 503 und dem Grund, statt still nichts zu verschicken. Die Antwort nennt zusätzlich, wie viele Konten Erinnerungen bekommen (scheduled)
- Erinnerung antippen öffnet jetzt „Heute“, auch wenn die App schon offen war (vorher blieb sie z. B. auf dem iPhone auf der zuletzt geöffneten Seite)
- Migration: 2026_10_18_000000_add_round_size_to_learning_settings.php

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
