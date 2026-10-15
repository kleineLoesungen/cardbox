<?php

/*
|--------------------------------------------------------------------------
| Decks, cards and learning
|--------------------------------------------------------------------------
|
| Limits for decks and cards and for the "paste a list" import. Values that
| operators might want to change come from .env; the rest are fixed product
| decisions documented in docs/specs/vokabelkarten.md.
|
*/

return [

    // Per card side: how many meanings, and how long each may be.
    'max_meanings' => 10,
    'max_meaning_length' => 500,

    // Optional hint per card, shown under the question before flipping.
    'max_hint_length' => 100,

    // "Fehler melden": length of the comment to the owner.
    'max_suggestion_comment' => 300,

    // "Liste einfügen": lines per import and file size in kilobytes.
    'import_max_lines' => 1000,
    'import_max_kilobytes' => 1024,

    // Several decks as one ZIP (export and import, docs/specs/stapel-export.md):
    // file size, size once unpacked (against "ZIP bombs"), number of decks.
    'bundle_max_kilobytes' => 5120,
    'bundle_max_unpacked_kilobytes' => 20480,
    'bundle_max_decks' => 50,

    // Search results in "Aus anderen Stapeln übernehmen".
    'search_limit' => 30,

    // "Today" for due dates is the calendar day in this time zone, not UTC —
    // otherwise cards would become due at 1 or 2 a.m. local time.
    'timezone' => env('LEARNING_TIMEZONE', 'Europe/Berlin'),

    // Default plan ("Normal"): boxes 0 (new) … box_count (mastered), and the
    // interval in days for each box 1 … box_count-1.
    'default_box_count' => 5,
    'default_day_gaps' => [1, 2, 4, 7],

    // A round holds at most this many cards; a card not known comes back at
    // the end of the round at most this many times.
    'round_size' => 20,
    'round_repeats' => 3,

    // "Heute" adds at most this many new cards a day by itself; more on
    // request ("Noch 20 neue Karten"). Counts every card started that day.
    'new_per_day' => 20,

    // "Für den Test üben": most questions in one round (cards × directions).
    'test_max' => 200,

];
