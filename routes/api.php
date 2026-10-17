<?php

use App\Http\Controllers\Api\V1\DeployController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NoteController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| JSON-Schnittstelle unter /api/v1
|--------------------------------------------------------------------------
|
| Fuer Maschinen, nicht fuer Menschen. Authentifizierung ueber App-Tokens,
| Berechtigung ueber Scopes — nie ueber Rollen:
|
|   ->middleware('api.token')          irgendein gueltiges Token
|   ->middleware('scope:notes:read')   Token mit diesem Scope
|
| Doku aller Endpunkte: docs/API.md. Neuen Endpunkt dort mit eintragen.
|
*/

Route::get('/health', HealthController::class);

// Deploy hook for web space without a shell (deploy.sh, docs/INSTALL-WEBSPACE.md).
// Not an app token: it must work on the first deploy with an empty database.
// Off (404) unless DEPLOY_HOOK_TOKEN is set; every wrong token is 404 too.
Route::post('/deploy', DeployController::class)->middleware(['deploy.throttle:10,1', 'deploy.token']);

// Daily reminders (docs/specs/schreiben-und-erinnern.md, B3): called every 15
// minutes by Cronicle, cron-job.org or the host's cron with X-Cron-Token.
// Off (404) unless REMINDER_TOKEN is set; every wrong token is 404 too.
Route::post('/reminders/run', ReminderController::class)->middleware(['throttle:10,1', 'reminder.token']);

Route::get('/token', TokenController::class)->middleware('api.token');

Route::get('/users', [UserController::class, 'index'])->middleware('scope:users:read');

Route::middleware('scope:notes:read')->group(function () {
    Route::get('/notes', [NoteController::class, 'index']);
    Route::get('/notes/{id}', [NoteController::class, 'show']);
});

Route::middleware('scope:notes:write')->group(function () {
    Route::post('/notes', [NoteController::class, 'store']);
    Route::put('/notes/{id}', [NoteController::class, 'update']);
    Route::delete('/notes/{id}', [NoteController::class, 'destroy']);
});
