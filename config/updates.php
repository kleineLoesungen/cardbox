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
| Vorgabe. Fuer Cardbox: .../kleineLoesungen/cardbox/main/CHANGELOG.md
|
*/

return [

    'check_url' => env('UPDATE_CHECK_URL'),

];
