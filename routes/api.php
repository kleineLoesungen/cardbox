<?php

use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\NoteController;
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
