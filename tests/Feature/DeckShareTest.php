<?php

use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use App\Services\DeckService;
use App\Services\LeitnerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * Sharing a deck with single persons (docs/specs/stapel-mit-einzelnen-teilen.md).
 */

function shareDeck(User $owner, string $name = 'Klassenstapel'): Deck
{
    $deck = Deck::factory()->for($owner)->create(['name' => $name, 'front_language' => 'de', 'back_language' => 'en']);
    Card::factory()->for($deck)->create(['front' => ['Fluss'], 'back' => ['river']]);

    return $deck;
}

function shareWith(Deck $deck, string $username): TestResponse
{
    return test()->post("/decks/{$deck->id}/shares", ['username' => $username]);
}

it('teilt mit einer Person per Benutzername; nur sie sieht den Stapel und kann ihn lernen oder ablehnen', function () {
    $lena = actingAsUser(['name' => 'Lena']);
    $deck = shareDeck($lena);
    $mia = User::factory()->create(['name' => 'Mia', 'username' => 'mia']);
    $fremd = User::factory()->create();

    shareWith($deck, ' MIA ')->assertRedirect("/decks/{$deck->id}")->assertSessionHas('toast.message', 'Mit Mia geteilt.');
    $this->get("/decks/{$deck->id}")->assertSee('geteilt mit 1 Person')->assertSee('Mia')->assertSee('noch nicht angenommen');

    // Mia: waiting under "Mit dir geteilt", with a badge on "Stapel"; not on "Heute" yet.
    $this->actingAs($mia)->get('/decks')
        ->assertSee('Mit dir geteilt')->assertSee('Klassenstapel')->assertSee('von Lena')
        ->assertSee('1 Stapel mit dir geteilt');
    $this->get("/decks/{$deck->id}")->assertOk()->assertSee('mit dir geteilt')->assertSee('Fehler melden');
    $this->get('/dictionary/search?q=fluss')->assertSee('river');
    expect(app(DeckService::class)->active($mia))->toHaveCount(0);

    // Somebody else: nothing, as for any private deck.
    $this->actingAs($fremd)->get("/decks/{$deck->id}")->assertNotFound();
    $this->get('/dictionary/search?q=fluss')->assertDontSee('river');
    $this->get('/decks?tab=discover')->assertDontSee('Klassenstapel');

    // "Lernen": "Lerne ich", on "Heute", no longer waiting.
    $this->actingAs($mia)->post("/decks/{$deck->id}/follow")->assertRedirect("/decks/{$deck->id}");
    expect(app(DeckService::class)->active($mia)->modelKeys())->toBe([$deck->id])
        ->and(app(DeckService::class)->pendingShareCount($mia))->toBe(0);
    $this->get('/decks')->assertDontSee('Mit dir geteilt');
    $this->actingAs($lena)->get("/decks/{$deck->id}")->assertSee('lernt mit');
});

it('weist unbekannte, gesperrte und wartende Konten gleich ab, dazu sich selbst, Doppelte und die 51. Person', function () {
    $lena = actingAsUser();
    $deck = shareDeck($lena);
    User::factory()->create(['username' => 'gesperrt', 'is_active' => false]);
    User::factory()->pending()->create(['username' => 'wartet']);
    $mia = User::factory()->create(['username' => 'mia']);

    foreach (['niemand', 'gesperrt', 'wartet'] as $name) {
        shareWith($deck, $name)->assertSessionHasErrorsIn('share', ['username' => 'Diesen Benutzernamen gibt es nicht.']);
    }
    shareWith($deck, $lena->username)->assertSessionHasErrorsIn('share', ['username' => 'Das bist du selbst.']);
    shareWith($deck, 'mia');
    shareWith($deck, 'mia')->assertSessionHasErrorsIn('share', ['username' => 'Mit '.$mia->name.' ist der Stapel schon geteilt.']);

    foreach (User::factory()->count(DeckService::MAX_SHARES - 1)->create() as $user) {
        DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $user->id, 'created_at' => now()]);
    }
    User::factory()->create(['username' => 'zuviel']);
    shareWith($deck, 'zuviel')->assertSessionHasErrorsIn('share', 'username');
    expect(DB::table('deck_shares')->where('deck_id', $deck->id)->count())->toBe(DeckService::MAX_SHARES);
});

it('lässt nur den Ersteller teilen und entfernen; Ablehnen nur bei eigenen Freigaben', function () {
    $lena = User::factory()->create();
    $deck = shareDeck($lena);
    $mia = User::factory()->create(['username' => 'mia']);
    DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $mia->id, 'created_at' => now()]);

    actingAsUser();
    $this->post("/decks/{$deck->id}/shares", ['username' => 'mia'])->assertNotFound();
    $this->post("/decks/{$deck->id}/shares/{$mia->id}/delete")->assertNotFound();
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all'])->assertNotFound();
    $this->post("/decks/{$deck->id}/decline")->assertNotFound();
    // Mia cannot share further.
    $this->actingAs($mia)->post("/decks/{$deck->id}/shares", ['username' => 'mia'])->assertNotFound();
});

it('behält beim Ablehnen, Entfernen und Privatmachen den Lernstand für ein erneutes Teilen', function () {
    $lena = actingAsUser(['name' => 'Lena']);
    $deck = shareDeck($lena);
    $mia = User::factory()->create(['username' => 'mia']);
    $progress = function () use ($mia, $deck) {
        $run = app(DeckRunService::class)->forStandard($mia, $deck);
        CardProgress::create(['user_id' => $mia->id, 'deck_run_id' => $run->id, 'card_id' => $deck->cards()->sole()->id, 'direction' => Direction::Forward,
            'box' => 3, 'due_on' => app(LeitnerService::class)->today()->toDateString(), 'review_count' => 3, 'correct_count' => 3]);
    };

    shareWith($deck, 'mia');
    $this->actingAs($mia)->post("/decks/{$deck->id}/follow");
    $progress();

    // Lena takes her off the list: gone for Mia, progress kept.
    $this->actingAs($lena)->post("/decks/{$deck->id}/shares/{$mia->id}/delete")->assertRedirect("/decks/{$deck->id}");
    $this->actingAs($mia)->get("/decks/{$deck->id}")->assertNotFound();
    expect(app(DeckService::class)->mine($mia))->toHaveCount(0)
        ->and(CardProgress::where('user_id', $mia->id)->count())->toBe(1);

    // Lena shares again: waiting again, the progress is back after "Lernen".
    $this->actingAs($lena);
    shareWith($deck, 'mia');
    $this->actingAs($mia)->get('/decks')->assertSee('Mit dir geteilt');
    $this->post("/decks/{$deck->id}/follow");
    expect(CardProgress::where('user_id', $mia->id)->sole()->box)->toBe(3);

    // Mia removes it herself: gone, progress kept.
    $this->post("/decks/{$deck->id}/decline")->assertRedirect('/decks');
    $this->get("/decks/{$deck->id}")->assertNotFound();
    expect(CardProgress::where('user_id', $mia->id)->count())->toBe(1);

    // "Privat machen" ends every share.
    $ben = User::factory()->create(['username' => 'ben']);
    $this->actingAs($lena);
    shareWith($deck, 'ben');
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'private']);
    $this->actingAs($ben)->get("/decks/{$deck->id}")->assertNotFound();
    expect(CardProgress::where('user_id', $mia->id)->count())->toBe(1);
});

it('lässt einen abgelehnten Stapel nicht noch einmal zuschieben', function () {
    $lena = actingAsUser(['name' => 'Lena']);
    $deck = shareDeck($lena);
    $mia = User::factory()->create(['name' => 'Mia', 'username' => 'mia']);
    shareWith($deck, 'mia');

    $this->actingAs($mia)->post("/decks/{$deck->id}/decline")->assertRedirect('/decks');
    $this->get('/decks')->assertDontSee('Mit dir geteilt')->assertDontSee('Stapel mit dir geteilt');
    $this->post("/decks/{$deck->id}/decline")->assertNotFound();

    $this->actingAs($lena);
    shareWith($deck, 'mia')->assertSessionHasErrorsIn('share', ['username' => 'Mia hat den Stapel abgelehnt.']);
    $this->get("/decks/{$deck->id}")->assertSee('privat')->assertDontSee('noch nicht angenommen');
    $this->actingAs($mia)->get("/decks/{$deck->id}")->assertNotFound();
});

it('behält die Liste beim Teilen mit allen und lässt sie danach wieder gelten', function () {
    $lena = actingAsUser();
    $deck = shareDeck($lena);
    $mia = User::factory()->create(['username' => 'mia']);
    $ben = User::factory()->create();
    shareWith($deck, 'mia');

    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all']);
    $this->actingAs($ben)->get("/decks/{$deck->id}")->assertOk();
    $this->get('/decks?tab=discover')->assertSee('Klassenstapel');
    $this->post("/decks/{$deck->id}/follow");

    // Back to the list: Ben is out, Mia still in.
    $this->actingAs($lena)->post("/decks/{$deck->id}/visibility", ['visibility' => 'people']);
    expect($deck->refresh()->is_shared)->toBeFalse();
    $this->actingAs($ben)->get("/decks/{$deck->id}")->assertNotFound();
    expect(app(DeckService::class)->mine($ben))->toHaveCount(0);
    $this->actingAs($mia)->get("/decks/{$deck->id}")->assertOk();
});

it('lässt nur Personen auf der Liste kopieren, übernehmen und Fehler melden', function () {
    $lena = User::factory()->create();
    $deck = shareDeck($lena);
    $card = $deck->cards()->sole();
    $mia = User::factory()->create();
    DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $mia->id, 'created_at' => now()]);

    foreach ([$mia, User::factory()->create()] as $i => $person) {
        $listed = $i === 0;
        $this->actingAs($person);
        $ziel = Deck::factory()->for($person)->create(['front_language' => 'de', 'back_language' => 'en']);
        $this->get("/decks/{$ziel->id}/adopt?q=fluss")->{$listed ? 'assertSee' : 'assertDontSee'}('river');
        $this->get("/decks/{$deck->id}/cards/{$card->id}/suggest")->assertStatus($listed ? 200 : 404);
        $this->post("/decks/{$deck->id}/copy")->{$listed ? 'assertRedirect' : 'assertNotFound'}();
    }
});

it('entfernt nur Personen, die auf der Liste stehen', function () {
    $lena = actingAsUser();
    $deck = shareDeck($lena);
    $fremd = User::factory()->create();

    $this->post("/decks/{$deck->id}/shares/{$fremd->id}/delete")->assertNotFound();
});

it('prüft die Sichtbarkeit', function () {
    $lena = actingAsUser();
    $deck = shareDeck($lena);

    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'geheim'])->assertSessionHasErrors('visibility');
    expect($deck->refresh()->is_shared)->toBeFalse();
});

it('bremst das Hinzufügen und zeigt einzeln geteilte Stapel nicht in der Moderation', function () {
    $route = collect(app('router')->getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'decks/{deck}/shares');
    expect($route->gatherMiddleware())->toContain('throttle:30,60,deck-share');

    $lena = User::factory()->create();
    $deck = shareDeck($lena);
    $mia = User::factory()->create();
    DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $mia->id, 'created_at' => now()]);

    actingAsUser(['role' => Role::Moderator]);
    $this->get("/decks/{$deck->id}")->assertNotFound();
    $this->post("/moderation/decks/{$deck->id}/unshare")->assertNotFound();
});

it('gibt Moderatoren auf der Liste keine Moderationsrechte über den Stapel', function () {
    $lena = User::factory()->create();
    $deck = shareDeck($lena);
    $card = $deck->cards()->sole();
    $moderator = actingAsUser(['role' => Role::Moderator]);
    DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $moderator->id, 'created_at' => now()]);

    $this->get("/decks/{$deck->id}")->assertOk();
    $this->post('/moderation/dictionary/cards', ['cards' => [$card->id], 'base' => $card->id, 'front' => ['Bach'], 'back' => ['creek']])->assertNotFound();
    $this->post('/moderation/dictionary/cards/delete', ['cards' => [$card->id]])->assertNotFound();
    expect($card->refresh()->front)->toBe(['Fluss']);
});

it('beendet beim Eingriff der Moderation auch das Teilen mit Einzelnen', function () {
    $lena = User::factory()->create();
    $deck = shareDeck($lena);
    $deck->forceFill(['is_shared' => true])->save();
    $mia = User::factory()->create();
    DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $mia->id, 'created_at' => now()]);

    actingAsUser(['role' => Role::Moderator]);
    $this->post("/moderation/decks/{$deck->id}/unshare")->assertRedirect();

    $this->actingAs($mia)->get("/decks/{$deck->id}")->assertNotFound();
});

it('nimmt die neue Besitzerin beim Übertragen von der Liste', function () {
    $lena = User::factory()->create();
    $deck = shareDeck($lena);
    $mia = User::factory()->create(['username' => 'mia']);
    DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $mia->id, 'created_at' => now()]);

    app(DeckService::class)->transfer($lena, [$deck->id], 'mia');

    expect(DB::table('deck_shares')->where('deck_id', $deck->id)->count())->toBe(0)
        ->and($deck->refresh()->user_id)->toBe($mia->id);
});
