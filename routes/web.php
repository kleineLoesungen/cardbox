<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TokenController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeckCardController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\DeckImportController;
use App\Http\Controllers\DeckRunController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LearningSettingsController;
use App\Http\Controllers\Moderation\DeckController as ModerationDeckController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\StudyRoundController;
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
    // Open to everyone. The first account becomes admin, every later one
    // waits for approval (UserService::register). Rate-limited per IP.
    Route::get('/register', [AuthController::class, 'showRegister']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,60');
});

// Random memorable password for registration and the admin forms. Returns no
// data, hence public; throttled so it cannot be used to burn CPU.
Route::post('/password-suggestion', [AuthController::class, 'suggestPassword'])->middleware('throttle:30,1');

// --- Angemeldet --------------------------------------------------------------

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // "Heute" lives at /learn; /dashboard stays as an old address.
    Route::redirect('/dashboard', '/learn');
    Route::get('/learn', [LearnController::class, 'today']);
    Route::get('/learn/settings', [LearningSettingsController::class, 'show']);
    Route::post('/learn/settings', [LearningSettingsController::class, 'update']);
    // A controller rather than Route::view, so RouteProtectionTest sees it.
    Route::get('/help', [PageController::class, 'help']);

    // Learning round (state in the session, see StudyRoundController).
    Route::post('/learn/round', [StudyRoundController::class, 'start']);
    Route::get('/learn/round', [StudyRoundController::class, 'show']);
    // block(): answers of one session are handled one after another, so two
    // simultaneous requests cannot both rate the same card.
    Route::post('/learn/round/answer', [StudyRoundController::class, 'answer'])->block(5, 5);

    Route::get('/account', [AccountController::class, 'show']);
    Route::post('/account', [AccountController::class, 'updateProfile']);
    Route::post('/account/password', [AccountController::class, 'updatePassword']);

    // Decks and cards. Ownership and visibility are checked in DeckService /
    // CardService: other people's private decks answer 404.
    Route::get('/decks', [DeckController::class, 'index']);
    Route::post('/decks', [DeckController::class, 'store']);
    Route::whereUuid(['deck', 'card'])->prefix('/decks/{deck}')->group(function () {
        Route::get('/', [DeckController::class, 'show']);
        Route::post('/', [DeckController::class, 'update']);
        Route::post('/share', [DeckController::class, 'share']);
        Route::post('/delete', [DeckController::class, 'destroy']);
        Route::post('/follow', [DeckController::class, 'follow']);
        Route::post('/unfollow', [DeckController::class, 'unfollow']);
        Route::post('/copy', [DeckController::class, 'copy']);
        // Deck runs: a deck learned with one plan. There is deliberately no
        // route that changes a run's plan — reset and start again instead.
        Route::post('/runs', [DeckRunController::class, 'store']);
        Route::post('/runs/{run}/reset', [DeckRunController::class, 'reset'])->whereUuid('run');
        Route::get('/box/{box}', [DeckController::class, 'box'])->where('box', '[0-9]{1,2}');

        Route::post('/cards', [DeckCardController::class, 'store']);
        Route::get('/cards/{card}/edit', [DeckCardController::class, 'edit']);
        Route::post('/cards/{card}', [DeckCardController::class, 'update']);
        Route::post('/cards/{card}/delete', [DeckCardController::class, 'destroy']);
        Route::get('/front-check', [DeckCardController::class, 'frontCheck']);
        Route::get('/adopt', [DeckCardController::class, 'search']);
        Route::post('/adopt/{card}', [DeckCardController::class, 'adopt']);

        Route::get('/import', [DeckImportController::class, 'show']);
        // Throttled: each request parses up to 1 MB of text.
        Route::post('/import/preview', [DeckImportController::class, 'preview'])->middleware('throttle:20,1');
        Route::post('/import', [DeckImportController::class, 'store'])->middleware('throttle:20,1');
    });

    Route::get('/notes', [NoteController::class, 'index']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->whereUuid('note');
});

// --- Ab Moderator ------------------------------------------------------------

Route::middleware(['auth', 'role:moderator'])->group(function () {
    // Shared decks only; moderators end sharing or delete, never edit.
    Route::get('/moderation', [ModerationDeckController::class, 'index']);
    Route::post('/moderation/decks/{deck}/unshare', [ModerationDeckController::class, 'unshare'])->whereUuid('deck');
    Route::post('/moderation/decks/{deck}/delete', [ModerationDeckController::class, 'destroy'])->whereUuid('deck');
});

// --- Nur Administrator -------------------------------------------------------

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/suggest-username', [UserController::class, 'suggestUsername']);
    Route::post('/users/{user}/approve', [UserController::class, 'approve'])->whereUuid('user');
    Route::post('/users/{user}/reject', [UserController::class, 'reject'])->whereUuid('user');
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
