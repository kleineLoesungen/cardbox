-- Laeuft genau einmal: beim ersten Start des Datenbank-Containers mit leerem Volume.
-- Die Tests benutzen eine eigene Datenbank und leeren sie bei jedem Lauf —
-- die Entwicklungsdaten bleiben dadurch unangetastet.
CREATE DATABASE starter_test OWNER starter;
