<?php

use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

/*
 * Moderation corrects cards in shared decks (docs/specs/woerterbuch-und-bereinigung.md,
 * stage 2).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

function correct(Deck $deck, Card $card, array $input): TestResponse
{
    return test()->withHeaders(['HX-Request' => 'true'])->post("/moderation/decks/{$deck->id}/cards/{$card->id}", $input);
}

it('lässt Moderation und Verwaltung Karten in fremden geteilten Stapeln korrigieren', function (Role $role) {
    $anna = User::factory()->create(['name' => 'Anna']);
    $deck = Deck::factory()->for($anna)->shared()->create();
    $card = Card::factory()->for($deck)->create(['front' => ['Hnud'], 'back' => ['dog']]);
    actingAsUser(['role' => $role]);

    $this->get("/decks/{$deck->id}")->assertSee('Bearbeiten (Moderation)');
    $this->get("/moderation/decks/{$deck->id}/cards/{$card->id}/edit")->assertOk()
        ->assertSee('Du änderst eine Karte von Anna')
        ->assertSee('hx-post="/moderation/decks/', false)
        ->assertDontSee('Karte löschen');

    correct($deck, $card, ['front' => ['Hund'], 'back' => ['dog'], 'hint' => ''])
        ->assertOk()
        ->assertHeader('HX-Trigger', 'sheet-close')
        ->assertSee('Karte korrigiert');

    expect($card->fresh()->front)->toBe(['Hund']);
})->with([Role::Moderator, Role::Admin]);

it('prüft wie beim Bearbeiten durch die Besitzerin', function () {
    $deck = Deck::factory()->shared()->create();
    $card = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
    actingAsUser(['role' => Role::Moderator]);

    correct($deck, $card, ['front' => ['Hund'], 'back' => ['  ']])->assertStatus(422)->assertSee('Hinten fehlt noch ein Wort.');
    correct($deck, $card, ['front' => ['Hund'], 'back' => ['dog'], 'hint' => str_repeat('x', 101)])->assertStatus(422);

    expect($card->fresh()->back)->toBe(['dog']);
});

it('lässt Benutzer nicht ran und fremde private Stapel nicht anfassen', function () {
    $geteilt = Deck::factory()->shared()->create();
    $karte = Card::factory()->for($geteilt)->create(['front' => ['Hund'], 'back' => ['dog']]);
    $privat = Deck::factory()->create();
    $geheim = Card::factory()->for($privat)->create(['front' => ['Katze'], 'back' => ['cat']]);

    actingAsUser();
    $this->get("/decks/{$geteilt->id}")->assertDontSee('Bearbeiten (Moderation)');
    correct($geteilt, $karte, ['front' => ['X'], 'back' => ['y']])->assertForbidden();

    actingAsUser(['role' => Role::Admin]);
    $this->get("/moderation/decks/{$privat->id}/cards/{$geheim->id}/edit")->assertNotFound();
    correct($privat, $geheim, ['front' => ['X'], 'back' => ['y']])->assertNotFound();
    // A card addressed through a deck it does not belong to.
    correct($geteilt, $geheim, ['front' => ['X'], 'back' => ['y']])->assertNotFound();
    correct($geteilt, $karte, ['front' => ['X'], 'back' => ['y']])->assertOk();

    expect($geheim->fresh()->front)->toBe(['Katze']);
});

it('zeigt Lernenden danach „geändert“ und lässt ihren Lernstand', function () {
    $deck = Deck::factory()->shared()->create();
    $card = Card::factory()->for($deck)->create(['front' => ['Hnud'], 'back' => ['dog']]);
    $ben = User::factory()->create();
    $run = app(DeckRunService::class)->forStandard($ben, $deck);
    CardProgress::create(['user_id' => $ben->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
        'box' => 2, 'due_on' => '2026-10-14', 'last_reviewed_at' => now()->subDay(), 'review_count' => 2, 'correct_count' => 2]);

    actingAsUser(['role' => Role::Moderator]);
    correct($deck, $card, ['front' => ['Hund'], 'back' => ['dog']])->assertOk();

    $this->actingAs($ben)->post('/learn/round', ['deck' => $deck->id]);
    $this->get('/learn/round')->assertSee('Hund')->assertSee('>geändert<', false);
    expect(CardProgress::where('card_id', $card->id)->sole()->box)->toBe(2);
});

it('schreibt jede Korrektur ins Log', function () {
    Log::spy();
    $deck = Deck::factory()->shared()->create();
    $card = Card::factory()->for($deck)->create();
    $mod = actingAsUser(['role' => Role::Moderator]);

    correct($deck, $card, ['front' => ['Hund'], 'back' => ['dog']]);

    Log::shouldHaveReceived('info')->with('Karte korrigiert (Moderation)', ['by' => $mod->id, 'card_id' => $card->id, 'deck_id' => $deck->id]);
});
