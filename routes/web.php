<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TokenController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HTML-Oberflaeche
|--------------------------------------------------------------------------
|
| Wer eine Route aufrufen darf, entscheidet die GRUPPE, in der sie steht —
| nie eine if-Abfrage im Controller. Eine neue Route also immer in die
| passende Gruppe schreiben:
|
|   Oeffentlich        ganz oben, ohne Middleware
|   Angemeldet         middleware('auth')
|   Ab Moderator       middleware(['auth', 'role:moderator'])
|   Nur Administrator  middleware(['auth', 'role:admin'])
|
| tests/Feature/RouteProtectionTest.php schlaegt fehl, sobald eine Route ohne
| Schutz dasteht, die nicht ausdruecklich als oeffentlich gilt.
|
*/

// --- Oeffentlich -------------------------------------------------------------

Route::get('/', [PageController::class, 'landing']);
Route::get('/styleguide', [PageController::class, 'styleguide']);

Route::get('/manifest.webmanifest', [PwaController::class, 'manifest']);
Route::get('/sw.js', [PwaController::class, 'serviceWorker']);
Route::get('/offline', [PwaController::class, 'offline']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    // Nur fuer die Ersteinrichtung: greift, solange es kein Konto gibt.
    Route::get('/register', [AuthController::class, 'showRegister']);
    Route::post('/register', [AuthController::class, 'register']);
});

// --- Angemeldet --------------------------------------------------------------

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/dashboard', [PageController::class, 'dashboard']);

    Route::get('/account', [AccountController::class, 'show']);
    Route::post('/account', [AccountController::class, 'updateProfile']);
    Route::post('/account/password', [AccountController::class, 'updatePassword']);

    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->whereUuid('note');
});

// --- Ab Moderator ------------------------------------------------------------

Route::middleware(['auth', 'role:moderator'])->group(function () {
    Route::get('/moderation', [PageController::class, 'moderation']);
});

// --- Nur Administrator -------------------------------------------------------

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show'])->whereUuid('user');
    Route::post('/users/{user}', [UserController::class, 'update'])->whereUuid('user');
    Route::post('/users/{user}/password', [UserController::class, 'updatePassword'])->whereUuid('user');
    Route::post('/users/{user}/delete', [UserController::class, 'destroy'])->whereUuid('user');

    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::post('/tokens/{token}/revoke', [TokenController::class, 'revoke'])->whereUuid('token');

    Route::get('/system', [SystemController::class, 'index']);
    Route::post('/system/testmail', [SystemController::class, 'testMail']);
    Route::post('/system/update-check', [SystemController::class, 'updateCheck']);
});
