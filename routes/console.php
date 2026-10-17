<?php

use App\Services\LoginThrottle;
use App\Services\ReminderService;
use Illuminate\Support\Facades\Artisan;
use Minishlink\WebPush\VAPID;

// Alte Eintraege der Anmeldebremse wegraeumen. Laeuft bei jedem Containerstart
// (docker/entrypoint.sh); die Tabelle bleibt dadurch klein.
Artisan::command('login-attempts:prune', function (LoginThrottle $throttle) {
    $this->info($throttle->prune().' alte Einträge der Anmeldebremse entfernt.');
})->purpose('Alte Einträge der Anmeldebremse entfernen');

// Daily reminders, for servers with their own cron (docs/INSTALL-WEBSERVER.md).
// Same as POST /api/v1/reminders/run.
Artisan::command('reminders:send', function (ReminderService $reminders) {
    if (! $reminders->enabled()) {
        $this->warn('Erinnerungen sind aus: VAPID_PUBLIC_KEY und VAPID_PRIVATE_KEY fehlen.');

        return;
    }
    if ($error = $reminders->configError()) {
        $this->error($error);

        return 1;
    }
    $result = $reminders->run();
    $this->info("{$result['sent']} Erinnerungen verschickt, {$result['skipped']} übersprungen, {$result['removed']} Geräte entfernt, {$result['failed']} kommen beim nächsten Mal.");
})->purpose('Fällige Erinnerungen verschicken');

// Keys for Web Push; printed for .env, never stored.
Artisan::command('push:keys', function () {
    $keys = VAPID::createVapidKeys();
    $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
    $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
    $this->comment('Diese zwei Zeilen in die .env übernehmen. Ein neues Paar meldet alle Geräte ab.');
})->purpose('Schlüssel für Erinnerungen (Web-Push) erzeugen');
