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

    // "Liste einfügen": lines per import and file size in kilobytes.
    'import_max_lines' => 1000,
    'import_max_kilobytes' => 1024,

    // Search results in "Aus anderen Stapeln übernehmen".
    'search_limit' => 30,

];
