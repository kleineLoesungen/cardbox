<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\DeckImportController as AdminDeckImportController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TokenController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CardOriginController;
use App\Http\Controllers\CardSuggestionController;
use App\Http\Controllers\DeckBundleController;
use App\Http\Controllers\DeckCardController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\DeckImportController;
use App\Http\Controllers\DeckRunController;
use App\Http\Controllers\DeckSelectionController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LearningSettingsController;
use App\Http\Controllers\Moderation\CardController as ModerationCardController;
use App\Http\Controllers\Moderation\DeckController as ModerationDeckController;
use App\Http\Controllers\Moderation\DictionaryCardController as ModerationDictionaryCardController;
use App\Http\Controllers\Moderation\DuplicateController as ModerationDuplicateController;
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

    // "Wörterbuch": look up words in all visible cards.
    Route::get('/dictionary', [DictionaryController::class, 'index']);
    Route::get('/dictionary/search', [DictionaryController::class, 'search']);
    Route::post('/dictionary/adopt/{card}', [DictionaryController::class, 'adopt'])->whereUuid('card');
    Route::post('/dictionary/learn/{card}', [DictionaryController::class, 'learn'])->whereUuid('card');
    // Editing and deleting from the dictionary (issue #12): own cards only;
    // moderation uses the /moderation/dictionary routes below.
    Route::get('/dictionary/cards/{card}/edit', [DictionaryController::class, 'edit'])->whereUuid('card');
    Route::post('/dictionary/cards', [DictionaryController::class, 'update']);
    Route::post('/dictionary/cards/delete', [DictionaryController::class, 'destroy']);

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
    // Several decks as one ZIP (export and import); static paths, so they
    // never collide with /decks/{deck}.
    Route::get('/decks/export', [DeckBundleController::class, 'exportForm']);
    Route::post('/decks/export', [DeckBundleController::class, 'export']);
    Route::get('/decks/import', [DeckBundleController::class, 'importForm']);
    Route::post('/decks/import/preview', [DeckBundleController::class, 'preview'])->middleware('throttle:20,1');
    Route::post('/decks/import', [DeckBundleController::class, 'store'])->middleware('throttle:20,1');
    Route::whereUuid(['deck', 'card'])->prefix('/decks/{deck}')->group(function () {
        Route::get('/', [DeckController::class, 'show']);
        Route::post('/', [DeckController::class, 'update']);
        Route::post('/share', [DeckController::class, 'share']);
        Route::post('/delete', [DeckController::class, 'destroy']);
        Route::post('/follow', [DeckController::class, 'follow']);
        Route::post('/unfollow', [DeckController::class, 'unfollow']);
        Route::post('/copy', [DeckController::class, 'copy']);
        // Learning only part of a deck (issue #10).
        Route::get('/selection', [DeckSelectionController::class, 'edit']);
        Route::post('/selection', [DeckSelectionController::class, 'update']);
        Route::post('/selection/all', [DeckSelectionController::class, 'all']);
        Route::get('/export', [DeckController::class, 'export']);
        // "Auch rückwärts lernen", per person and deck.
        Route::post('/reverse', [DeckController::class, 'reverse']);
        // Deck runs: a deck learned with one plan. New runs start only with
        // the standard plan (StudyRoundService); here they are only reset.
        Route::post('/runs/{run}/reset', [DeckRunController::class, 'reset'])->whereUuid('run');
        Route::get('/box/{box}', [DeckController::class, 'box'])->where('box', '[0-9]{1,2}');

        Route::post('/cards', [DeckCardController::class, 'store']);
        Route::get('/cards/{card}/edit', [DeckCardController::class, 'edit']);
        Route::post('/cards/{card}', [DeckCardController::class, 'update']);
        Route::post('/cards/{card}/delete', [DeckCardController::class, 'destroy']);
        Route::get('/front-check', [DeckCardController::class, 'frontCheck']);
        Route::get('/adopt', [DeckCardController::class, 'search']);
        Route::post('/adopt/{card}', [DeckCardController::class, 'adopt']);

        // Changes in the originals of copied cards (owner of the copy only).
        Route::get('/origin', [CardOriginController::class, 'index']);
        Route::post('/origin/take-all', [CardOriginController::class, 'takeAll']);
        Route::post('/cards/{card}/origin/take', [CardOriginController::class, 'take']);
        Route::post('/cards/{card}/origin/keep', [CardOriginController::class, 'keep']);

        // "Fehler melden" on other people's shared cards; the owner decides.
        Route::get('/cards/{card}/suggest', [CardSuggestionController::class, 'create']);
        Route::post('/cards/{card}/suggest', [CardSuggestionController::class, 'store'])->middleware('throttle:20,60');
        Route::get('/suggestions', [CardSuggestionController::class, 'index']);
        Route::post('/suggestions/{suggestion}/accept', [CardSuggestionController::class, 'accept'])->whereUuid('suggestion');
        Route::post('/suggestions/{suggestion}/decline', [CardSuggestionController::class, 'decline'])->whereUuid('suggestion');

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
    // Correcting cards in shared decks of other people (issue #6); deleting goes through the dictionary.
    Route::get('/moderation/decks/{deck}/cards/{card}/edit', [ModerationCardController::class, 'edit'])->whereUuid(['deck', 'card']);
    Route::post('/moderation/decks/{deck}/cards/{card}', [ModerationCardController::class, 'update'])->whereUuid(['deck', 'card']);
    // Editing and deleting from the dictionary: own cards and shared decks of others (issue #12).
    Route::post('/moderation/dictionary/cards', [ModerationDictionaryCardController::class, 'update']);
    Route::post('/moderation/dictionary/cards/delete', [ModerationDictionaryCardController::class, 'destroy']);
    // Duplicate cards in shared decks; a group is addressed by any of its cards.
    Route::get('/moderation/duplicates', [ModerationDuplicateController::class, 'index']);
    Route::get('/moderation/duplicates/{card}', [ModerationDuplicateController::class, 'show'])->whereUuid('card');
    Route::post('/moderation/duplicates/{card}/merge', [ModerationDuplicateController::class, 'store'])->whereUuid('card');
});

// --- Nur Administrator -------------------------------------------------------

Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    // Import into any shared deck, also other people's (overwrites their
    // cards from a CSV). Private decks of others stay out of reach.
    Route::whereUuid('deck')->prefix('/decks/{deck}')->group(function () {
        Route::get('/import', [AdminDeckImportController::class, 'show']);
        Route::post('/import/preview', [AdminDeckImportController::class, 'preview'])->middleware('throttle:20,1');
        Route::post('/import', [AdminDeckImportController::class, 'store'])->middleware('throttle:20,1');
    });

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/suggest-username', [UserController::class, 'suggestUsername']);
    Route::post('/users/{user}/approve', [UserController::class, 'approve'])->whereUuid('user');
    Route::post('/users/{user}/reject', [UserController::class, 'reject'])->whereUuid('user');
    Route::get('/users/{user}', [UserController::class, 'show'])->whereUuid('user');
    Route::post('/users/{user}', [UserController::class, 'update'])->whereUuid('user');
    Route::post('/users/{user}/password', [UserController::class, 'updatePassword'])->whereUuid('user');
    Route::post('/users/{user}/delete', [UserController::class, 'destroy'])->whereUuid('user');
    // Hand decks to another account, private ones included (issue #12).
    Route::post('/users/{user}/decks/transfer', [UserController::class, 'transferDecks'])->whereUuid('user');

    Route::get('/tokens', [TokenController::class, 'index']);
    Route::post('/tokens', [TokenController::class, 'store']);
    Route::post('/tokens/{token}/revoke', [TokenController::class, 'revoke'])->whereUuid('token');

    Route::get('/system', [SystemController::class, 'index']);
    Route::post('/system/testmail', [SystemController::class, 'testMail']);
    Route::post('/system/update-check', [SystemController::class, 'updateCheck']);
});
