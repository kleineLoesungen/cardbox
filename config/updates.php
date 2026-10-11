<?php

/*
|--------------------------------------------------------------------------
| Update-Pruefung
|--------------------------------------------------------------------------
|
| Rohdatei des eigenen CHANGELOG.md. Ist sie gesetzt, vergleicht die Anwendung
| hoechstens einmal taeglich die eigene Version mit dieser Datei und zeigt
| Administratoren unter System neuere Eintraege an. Heruntergeladen oder
| installiert wird nichts.
|
| Leer oder nicht gesetzt = abgeschaltet, kein Netzzugriff. Das ist die
| Vorgabe; ein Projekt aus dem Kit traegt hier sein EIGENES Repository ein.
|
*/

return [

    'check_url' => env('UPDATE_CHECK_URL'),

];
