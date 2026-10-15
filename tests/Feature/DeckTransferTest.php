<?php

use App\Enums\Direction;
use App\Enums\Role;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

/*
 * Admins hand decks to another account (issue #12,
 * docs/specs/woerterbuch-bearbeiten-und-uebertragen.md, part B).
 */

function transfer(User $from, array $input): TestResponse
{
    return test()->post("/admin/users/{$from->id}/decks/transfer", $input);
}

function follows(User $user, Deck $deck): bool
{
    return DB::table('deck_follows')->where('user_id', $user->id)->where('deck_id', $deck->id)->exists();
}

it('zeigt die Stapel eines Kontos ohne Karten', function () {
    $anna = User::factory()->create(['name' => 'Anna']);
    $deck = Deck::factory()->for($anna)->create(['name' => 'Geheimes']);
    Card::factory()->for($deck)->create(['front' => ['Geheimwort'], 'back' => ['secret']]);
    actingAsAdmin();

    $this->get("/admin/users/{$anna->id}")->assertOk()
        ->assertSee('Stapel (1)')
        ->assertSee('Geheimes')->assertSee('privat')->assertSee('1 Karte')
        ->assertSee('name="decks[]"', false)
        ->assertDontSee('Geheimwort');
});

it('überträgt einen geteilten Stapel: die alte Besitzerin folgt, die neue folgt nicht mehr', function () {
    $anna = User::factory()->create();
    $ben = User::factory()->create(['username' => 'ben']);
    $deck = Deck::factory()->for($anna)->shared()->create();
    $deck->followers()->attach($ben);
    $admin = actingAsAdmin();
    Log::spy();

    transfer($anna, ['decks' => [$deck->id], 'to_username' => ' Ben '])
        ->assertRedirect("/admin/users/{$anna->id}")
        ->assertSessionHas('toast', ['kind' => 'success', 'message' => '1 Stapel an ben übertragen.']);

    expect($deck->fresh()->user_id)->toBe($ben->id)
        ->and(follows($ben, $deck))->toBeFalse()
        ->and(follows($anna, $deck))->toBeTrue();
    Log::shouldHaveReceived('info')->with('Stapel übertragen', ['by' => $admin->id, 'deck_id' => $deck->id, 'from' => $anna->id, 'to' => $ben->id]);

    // Anna still sees the deck as a follower, Ben as its owner.
    $this->actingAs($anna)->get("/decks/{$deck->id}")->assertOk();
    $this->actingAs($ben)->get("/decks/{$deck->id}/selection")->assertOk();
});

it('lässt den Lernstand der alten Besitzerin stehen', function (bool $shared) {
    $anna = User::factory()->create();
    User::factory()->create(['username' => 'ben']);
    $deck = Deck::factory()->for($anna)->create(['is_shared' => $shared]);
    $card = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
    $run = app(DeckRunService::class)->forStandard($anna, $deck);
    CardProgress::create(['user_id' => $anna->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
        'box' => 3, 'due_on' => now()->toDateString(), 'last_reviewed_at' => now()->subDay(), 'review_count' => 3, 'correct_count' => 3]);
    actingAsAdmin();

    transfer($anna, ['decks' => [$deck->id], 'to_username' => 'ben'])->assertSessionHasNoErrors();

    expect(CardProgress::where('user_id', $anna->id)->where('card_id', $card->id)->sole()->box)->toBe(3);
    // Shared: she keeps learning it as a follower; private: it is gone from "Heute".
    $heute = $this->actingAs($anna)->get('/learn')->assertOk();
    $shared ? $heute->assertSee($deck->name) : $heute->assertDontSee($deck->name);
})->with(['geteilt' => true, 'privat' => false]);

it('überträgt private Stapel, die danach für die alte Besitzerin unsichtbar sind', function () {
    $anna = User::factory()->create();
    $ben = User::factory()->create(['username' => 'ben']);
    $eins = Deck::factory()->for($anna)->create();
    $zwei = Deck::factory()->for($anna)->create();
    $bleibt = Deck::factory()->for($anna)->create();
    actingAsAdmin();

    transfer($anna, ['decks' => [$eins->id, $zwei->id], 'to_username' => 'ben'])
        ->assertSessionHas('toast', ['kind' => 'success', 'message' => '2 Stapel an ben übertragen.']);

    expect($eins->fresh()->user_id)->toBe($ben->id)
        ->and($zwei->fresh()->user_id)->toBe($ben->id)
        ->and($bleibt->fresh()->user_id)->toBe($anna->id)
        ->and(follows($anna, $eins))->toBeFalse();
    $this->actingAs($anna)->get("/decks/{$eins->id}")->assertNotFound();
});

it('lehnt unbekannte, wartende, gesperrte und gleiche Konten ab', function (Closure $target, string $message) {
    $anna = User::factory()->create(['username' => 'anna']);
    $deck = Deck::factory()->for($anna)->create();
    actingAsAdmin();

    transfer($anna, ['decks' => [$deck->id], 'to_username' => $target()])
        ->assertSessionHasErrors(['to_username' => $message]);

    expect($deck->fresh()->user_id)->toBe($anna->id);
})->with([
    'unbekannt' => [fn () => 'niemand', 'Kein freigegebenes, aktives Konto mit diesem Benutzernamen.'],
    'wartend' => [fn () => User::factory()->pending()->create()->username, 'Kein freigegebenes, aktives Konto mit diesem Benutzernamen.'],
    'gesperrt' => [fn () => User::factory()->inactive()->create()->username, 'Kein freigegebenes, aktives Konto mit diesem Benutzernamen.'],
    'gleich' => [fn () => 'anna', 'Die Stapel gehören schon anna.'],
]);

it('überträgt nichts ohne Auswahl oder mit fremden Stapeln', function () {
    $anna = User::factory()->create();
    $ben = User::factory()->create(['username' => 'ben']);
    $annas = Deck::factory()->for($anna)->create();
    $fremd = Deck::factory()->for(User::factory()->create())->create();
    actingAsAdmin();

    transfer($anna, ['to_username' => 'ben'])->assertSessionHasErrors(['decks' => 'Wähle mindestens einen Stapel.']);
    transfer($anna, ['decks' => ['abc'], 'to_username' => 'ben'])->assertSessionHasErrors('decks.0');
    transfer($anna, ['decks' => [$annas->id, $fremd->id], 'to_username' => 'ben'])
        ->assertSessionHasErrors(['decks' => 'Die Liste hat sich geändert. Bitte neu laden.']);

    expect($annas->fresh()->user_id)->toBe($anna->id)
        ->and($fremd->fresh()->user_id)->not->toBe($ben->id);
});

it('lässt nur Administratoren übertragen', function (Role $role) {
    $anna = User::factory()->create();
    $deck = Deck::factory()->for($anna)->create();
    User::factory()->create(['username' => 'ben']);
    actingAsUser(['role' => $role]);

    transfer($anna, ['decks' => [$deck->id], 'to_username' => 'ben'])->assertForbidden();

    expect($deck->fresh()->user_id)->toBe($anna->id);
})->with([Role::User, Role::Moderator]);
