<?php

use App\Models\CardSuggestion;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use App\Services\CardService;
use App\Services\LearningOverviewService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

/*
 * The sample data must keep working as the app grows: each feature it
 * shows is checked here (README, "Entwickeln").
 */

it('legt Beispieldaten an, die jede Funktion einmal zeigen', function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));

    $this->seed(DatabaseSeeder::class);

    $bea = User::where('username', 'bea')->sole();
    $englisch = Deck::where('user_id', $bea->id)->where('name', 'Englisch Unit 3')->sole();
    $kopie = Deck::where('user_id', $bea->id)->where('name', 'Reisen')->sole();

    expect(User::pluck('username')->sort()->values()->all())->toBe(['anna', 'bea', 'mia', 'moritz', 'neu.nutzer'])
        ->and(DeckRun::where('deck_id', $englisch->id)->pluck('plan')->sort()->values()->all())->toBe(['normal', 'quick'])
        ->and(app(LearningOverviewService::class)->today($bea))->due->toBeGreaterThan(0)->streak->toBe(3)
        ->and(app(CardService::class)->pendingOriginalCount($bea, $kopie))->toBe(1)
        ->and(CardSuggestion::where('status', 'open')->count())->toBe(1)
        ->and($englisch->cards()->whereNotNull('hint')->count())->toBe(2);

    // Every account can sign in and sees its pages without errors.
    foreach (['anna', 'bea', 'moritz'] as $name) {
        $this->actingAs(User::where('username', $name)->sole());
        $this->get('/learn')->assertOk();
        $this->get('/decks')->assertOk();
    }
    $this->actingAs($bea)->get("/decks/{$englisch->id}")->assertOk()->assertSee('aria-label="Lernplan"', false);
    $this->get("/decks/{$kopie->id}/origin")->assertOk()->assertSee('(Koffer)');
    // Bea is Mia's parent (eltern-konten.md): she edits Mia's deck.
    $mia = User::where('username', 'mia')->sole();
    $tiere = Deck::where('user_id', $mia->id)->sole();
    $this->get("/children/{$mia->id}")->assertOk()->assertSee('Stapel von Mia Kind bearbeiten');
    $this->get("/decks/{$tiere->id}")->assertOk()->assertSee('du bearbeitest ihn als Elternteil');
    $anna = User::where('username', 'anna')->sole();
    $reisen = Deck::where('user_id', $anna->id)->where('name', 'Reisen')->sole();
    $this->actingAs($anna)->get("/decks/{$reisen->id}/suggestions")->assertOk()->assertSee('railway station');
});

it('legt beim zweiten Lauf nichts doppelt an', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(5)->and(Deck::count())->toBe(5);
});
