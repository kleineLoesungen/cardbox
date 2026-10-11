<?php

/*
|--------------------------------------------------------------------------
| Installierbare App (PWA)
|--------------------------------------------------------------------------
|
| Farben, die ausserhalb der Seite erscheinen: Browser- und Statusleiste,
| Startbildschirm der installierten App, Knoepfe in HTML-Mails. Browser,
| Telefone und Mailprogramme lesen kein Stylesheet — sie brauchen feste Werte.
|
| Beide Werte werden beim Start auf das Format #rgb / #rrggbb geprueft
| (App\Providers\AppServiceProvider). Ein Tippfehler faellt damit sofort auf
| und nicht erst, wenn sich jemand wundert, warum die App grau bleibt.
|
*/

return [

    'theme_color' => env('PWA_THEME_COLOR', '#3370c7'),

    'background_color' => env('PWA_BACKGROUND_COLOR', '#f9fafc'),

];
