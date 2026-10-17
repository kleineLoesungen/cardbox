<?php

/*
|--------------------------------------------------------------------------
| Daily reminders via Web Push
|--------------------------------------------------------------------------
|
| docs/specs/schreiben-und-erinnern.md, B. Without both VAPID keys the
| feature is off: no section on the account page, nothing is sent. Create
| keys with `php artisan push:keys`.
|
| Reminders go out when something calls POST /api/v1/reminders/run with the
| header "X-Cron-Token: <REMINDER_TOKEN>" (every 15 minutes, e.g. Cronicle
| or cron-job.org) or runs `php artisan reminders:send`. Without a token of
| at least 32 characters the address answers 404.
|
*/

return [

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        // Contact for the push services: "mailto:…" or the app's address.
        // An empty line in .env counts as unset, too.
        'subject' => env('VAPID_SUBJECT') ?: env('APP_URL'),
    ],

    'reminder_token' => env('REMINDER_TOKEN'),

    // Devices are only accepted with an address at one of the browsers' push
    // services, so the server never posts to arbitrary hosts. A host matches
    // itself and any subdomain.
    'endpoint_hosts' => [
        'fcm.googleapis.com',            // Chrome, Android
        'push.apple.com',                // Safari, iOS (web.push.apple.com)
        'push.services.mozilla.com',     // Firefox
        'notify.windows.com',            // Edge on Windows
    ],

    // People reminded per call at most; the rest follow on the next call.
    'batch_size' => 200,

    // Messages per call at most (several devices per person), devices per
    // account (a new one replaces the oldest), and seconds per message to a
    // push service — so one call stays well within a web space's time limit.
    'max_messages' => 100,
    'max_devices' => 5,
    'timeout' => 5,

    // A reminder counts as due this many seconds before its time. Calls come
    // on the quarter hour like the times one can choose; without this a
    // server clock slightly behind the scheduler skips every reminder.
    'early_seconds' => 120,

    // Seconds one call may spend sending; the rest comes on the next call.
    // Below the 60 s a scheduler (Cronicle) waits by default.
    'time_budget' => 40,

    // A reminder more than this many hours late (scheduler down) is dropped
    // for the day rather than buzzing a child late in the evening.
    'catch_up_hours' => 2,

    // Times offered on the account page: from, to, step in minutes.
    'reminder_from' => '06:00',
    'reminder_to' => '21:00',
    'reminder_step' => 15,
    'reminder_default' => '17:00',

];
