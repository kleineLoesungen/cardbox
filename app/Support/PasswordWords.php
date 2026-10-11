<?php

namespace App\Support;

/**
 * Short, child-friendly German words for memorable password suggestions
 * ("Wolke-Tiger-Apfel-47").
 *
 * ASCII only, so the words are easy to type on any phone keyboard. Three
 * words out of ~270 plus a two-digit number give roughly 2^30 combinations —
 * fine behind the login throttle, and far easier to pass on to a child than
 * random characters.
 */
final class PasswordWords
{
    /** @var list<string> */
    public const WORDS = [
        'Abend', 'Adler', 'Affe', 'Ahorn', 'Ameise', 'Ampel', 'Anker', 'Apfel', 'Ast', 'Auto',
        'Bach', 'Backe', 'Bagger', 'Ball', 'Banane', 'Bank', 'Bart', 'Bauer', 'Baum', 'Becher',
        'Beere', 'Berg', 'Besen', 'Biene', 'Birne', 'Blatt', 'Blitz', 'Blume', 'Boot', 'Brei',
        'Brett', 'Brief', 'Brille', 'Brot', 'Brunnen', 'Buch', 'Burg', 'Busch', 'Butter', 'Dach',
        'Dachs', 'Dampf', 'Daumen', 'Decke', 'Delfin', 'Dorf', 'Drache', 'Dose', 'Drossel', 'Duft',
        'Eimer', 'Eis', 'Elch', 'Ente', 'Erbse', 'Erde', 'Esel', 'Eule', 'Falke', 'Farbe',
        'Feder', 'Feld', 'Fels', 'Fenster', 'Ferien', 'Feuer', 'Fisch', 'Flagge', 'Floh', 'Flosse',
        'Flur', 'Fluss', 'Fohlen', 'Frosch', 'Fuchs', 'Gabel', 'Garten', 'Geige', 'Gras', 'Gurke',
        'Hafen', 'Hafer', 'Hahn', 'Hai', 'Hammer', 'Hand', 'Hase', 'Haus', 'Hecht', 'Hecke',
        'Held', 'Helm', 'Hemd', 'Herbst', 'Herz', 'Heu', 'Himmel', 'Hirsch', 'Hof', 'Honig',
        'Horn', 'Hose', 'Huhn', 'Hund', 'Hut', 'Igel', 'Insel', 'Jacke', 'Jaguar', 'Joghurt',
        'Kaffee', 'Kakao', 'Kamel', 'Kamm', 'Kanne', 'Kappe', 'Karte', 'Kater', 'Katze', 'Kekse',
        'Kerze', 'Kette', 'Kiefer', 'Kino', 'Kirsche', 'Kiste', 'Kiwi', 'Klee', 'Knopf', 'Koala',
        'Koffer', 'Komet', 'Krabbe', 'Kran', 'Krone', 'Kuchen', 'Kugel', 'Lampe', 'Land', 'Laterne',
        'Laub', 'Leiter', 'Lerche', 'Licht', 'Linde', 'Lineal', 'Loewe', 'Luchs', 'Luft', 'Mais',
        'Mantel', 'Marder', 'Maus', 'Meer', 'Melone', 'Mond', 'Moos', 'Motor', 'Moewe', 'Muschel',
        'Nadel', 'Nase', 'Nebel', 'Nest', 'Nudel', 'Nuss', 'Oase', 'Ofen', 'Ohr', 'Onkel',
        'Orange', 'Otter', 'Paddel', 'Palme', 'Panda', 'Papier', 'Park', 'Pfad', 'Pferd', 'Pilz',
        'Pinsel', 'Pirat', 'Pizza', 'Planet', 'Pony', 'Puppe', 'Quelle', 'Rabe', 'Rad', 'Rakete',
        'Rasen', 'Raupe', 'Regen', 'Reh', 'Reis', 'Riese', 'Ring', 'Ritter', 'Robbe', 'Rock',
        'Rose', 'Rucksack', 'Saft', 'Salat', 'Sand', 'Schaf', 'Schal', 'Schiff', 'Schnee', 'Schuh',
        'Schule', 'See', 'Segel', 'Seife', 'Sessel', 'Sonne', 'Spatz', 'Spiegel', 'Stern', 'Stiefel',
        'Stift', 'Storch', 'Strand', 'Stuhl', 'Sturm', 'Suppe', 'Tafel', 'Tal', 'Tanne', 'Tasche',
        'Tasse', 'Taube', 'Teich', 'Teller', 'Tiger', 'Tisch', 'Tomate', 'Topf', 'Traube', 'Traum',
        'Trommel', 'Tulpe', 'Turm', 'Uhr', 'Ufer', 'Uhu', 'Vase', 'Vogel', 'Vulkan', 'Waage',
        'Wagen', 'Wald', 'Wal', 'Wand', 'Wasser', 'Welle', 'Wiese', 'Wind', 'Winter', 'Wolf',
        'Wolke', 'Wolle', 'Wurm', 'Wurzel', 'Zange', 'Zebra', 'Zelt', 'Ziege', 'Zirkus', 'Zitrone',
        'Zucker', 'Zug', 'Zwerg', 'Zwiebel',
    ];
}
