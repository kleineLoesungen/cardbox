<?php

use App\Services\LoginThrottle;
use Illuminate\Support\Facades\Artisan;

// Alte Eintraege der Anmeldebremse wegraeumen. Laeuft bei jedem Containerstart
// (docker/entrypoint.sh); die Tabelle bleibt dadurch klein.
Artisan::command('login-attempts:prune', function (LoginThrottle $throttle) {
    $this->info($throttle->prune().' alte Einträge der Anmeldebremse entfernt.');
})->purpose('Alte Einträge der Anmeldebremse entfernen');
