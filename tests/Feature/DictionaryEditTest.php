<?php

use App\Enums\Role;
use App\Models\Card;
use App\Models\CardSuggestion;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

/*
 * Editing and deleting cards from the dictionary (issue #12,
 * docs/specs/woerterbuch-bearbeiten-und-uebertragen.md, part A).
 */

function dictDeck(User $owner, array $attributes = []): Deck
{
    return Deck::factory()->for($owner)->create(['front_language' => 'de', 'back_language' => 'en'] + $attributes);
}

function dictPost(string $url, array $input): TestResponse
{
    return test()->withHeaders(['HX-Request' => 'true'])->post($url, $input);
}

it('bearbeitet eine eigene Karte direkt aus dem Wörterbuch', function () {
    $ich = actingAsUser();
    $card = Card::factory()->for(dictDeck($ich, ['name' => 'Meins']))->create(['front' => ['Hnud'], 'back' => ['dog']]);

    $this->withHeaders(['HX-Request' => 'true'])->get('/dictionary/search?q=hnud')
        ->assertSee('hx-get="/dictionary/cards/'.$card->id.'/edit"', false)
        ->assertSee('Bearbeiten');

    $this->get("/dictionary/cards/{$card->id}/edit")->assertOk()
        ->assertSee('hx-post="/dictionary/cards"', false)
        ->assertSee('value="Hnud"', false)
        ->assertSee('Karte löschen')
        ->assertDontSee('Welche Karte meinst du');

    dictPost('/dictionary/cards', ['cards' => [$card->id], 'base' => $card->id, 'front' => ['Hund'], 'back' => ['dog'], 'hint' => ''])
        ->assertOk()
        ->assertHeader('HX-Trigger', 'sheet-close, dictionary-refresh')
        ->assertSee('Karte gespeichert.');

    expect($card->fresh()->front)->toBe(['Hund']);
});

it('bietet bei mehreren Karten erst eine Auswahl und ändert auf Wunsch alle, auch umgekehrte', function () {
    $ich = actingAsUser();
    $anna = User::factory()->create(['name' => 'Anna']);
    $a = Card::factory()->for(dictDeck($ich, ['name' => 'Eins']))->create(['front' => ['Hund'], 'back' => ['dog']]);
    $b = Card::factory()->for(Deck::factory()->for($ich)->create(['name' => 'Zwei', 'front_language' => 'en', 'back_language' => 'de']))
        ->create(['front' => ['dog'], 'back' => ['Hund'], 'hint' => 'Tier']);
    $fremd = Card::factory()->for(dictDeck($anna, ['name' => 'Annas', 'is_shared' => true]))->create(['front' => ['Hund'], 'back' => ['dog']]);

    $this->get("/dictionary/cards/{$a->id}/edit")->assertOk()
        ->assertSee('Welche Karte meinst du')
        ->assertSee('Alle (2) bearbeiten')
        ->assertSee('„Annas“', false)->assertSee('von Anna')
        ->assertSee("/decks/{$fremd->deck_id}/cards/{$fremd->id}/suggest?context=dictionary", false)
        ->assertSee('Abweichend: Hund – dog (Tier)');

    $this->get("/dictionary/cards/{$a->id}/edit?selected=all")->assertOk()
        ->assertSee('Du änderst 2 Karten in 2 Stapeln')
        ->assertSee('Alle 2 speichern')
        ->assertSee('hx-post="/dictionary/cards"', false);

    dictPost('/dictionary/cards', ['cards' => [$a->id, $b->id], 'base' => $a->id, 'front' => ['Hund', 'Köter'], 'back' => ['dog'], 'hint' => ''])
        ->assertOk()->assertSee('2 Karten gespeichert.');

    expect($a->fresh()->front)->toBe(['Hund', 'Köter'])
        ->and($b->fresh()->front)->toBe(['dog'])
        ->and($b->fresh()->back)->toBe(['Hund', 'Köter'])
        ->and($b->fresh()->hint)->toBeNull()
        ->and($fremd->fresh()->front)->toBe(['Hund']);
});

it('ändert nichts, wenn eine fremde Karte dabei ist', function () {
    $ich = actingAsUser();
    $eigen = Card::factory()->for(dictDeck($ich))->create(['front' => ['Hund'], 'back' => ['dog']]);
    $fremd = Card::factory()->for(dictDeck(User::factory()->create(), ['is_shared' => true]))->create(['front' => ['Hund'], 'back' => ['dog']]);

    dictPost('/dictionary/cards', ['cards' => [$eigen->id, $fremd->id], 'base' => $eigen->id, 'front' => ['X'], 'back' => ['y']])->assertNotFound();
    dictPost('/dictionary/cards/delete', ['cards' => [$eigen->id, $fremd->id]])->assertNotFound();
    dictPost('/moderation/dictionary/cards', ['cards' => [$fremd->id], 'base' => $fremd->id, 'front' => ['X'], 'back' => ['y']])->assertForbidden();
    dictPost('/moderation/dictionary/cards/delete', ['cards' => [$fremd->id]])->assertForbidden();

    expect($eigen->fresh()->front)->toBe(['Hund'])
        ->and($fremd->fresh()->front)->toBe(['Hund']);
});

it('weist ungültige, fehlende und gelöschte Karten mit 404 ab, ohne Datenbankfehler', function () {
    $ich = actingAsUser();
    $card = Card::factory()->for(dictDeck($ich))->create(['front' => ['Hund'], 'back' => ['dog']]);
    $input = ['base' => $card->id, 'front' => ['X'], 'back' => ['y']];

    dictPost('/dictionary/cards', ['cards' => ['abc'], 'base' => 'abc', 'front' => ['X'], 'back' => ['y']])->assertNotFound();
    dictPost('/dictionary/cards', ['front' => ['X'], 'back' => ['y']])->assertNotFound();
    dictPost('/dictionary/cards', ['cards' => [['nested']]] + $input)->assertNotFound();
    // The base must be one of the cards.
    dictPost('/dictionary/cards', ['cards' => [$card->id], 'base' => (string) str()->uuid()] + $input)->assertNotFound();
    dictPost('/dictionary/cards', ['cards' => [$card->id, (string) str()->uuid()]] + $input)->assertNotFound();
    $this->get('/dictionary/cards/'.str()->uuid().'/edit')->assertNotFound();

    expect($card->fresh()->front)->toBe(['Hund']);
});

it('prüft den Inhalt wie beim Bearbeiten im Stapel', function () {
    $ich = actingAsUser();
    $card = Card::factory()->for(dictDeck($ich))->create(['front' => ['Hund'], 'back' => ['dog']]);

    dictPost('/dictionary/cards', ['cards' => [$card->id], 'base' => $card->id, 'front' => ['Hund'], 'back' => ['  ']])
        ->assertStatus(422)->assertSee('Hinten fehlt noch ein Wort.');

    expect($card->fresh()->back)->toBe(['dog']);
});

it('löscht eigene Karten samt Lernstand und nennt Folgende in der Rückfrage', function () {
    $ich = actingAsUser();
    $deck = dictDeck($ich, ['is_shared' => true]);
    $card = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
    $deck->followers()->attach(User::factory()->count(2)->create());

    $this->get("/dictionary/cards/{$card->id}/edit")
        ->assertSee('Karte löschen? 2 Personen, die damit lernen, verlieren sie samt Lernstand.');

    dictPost('/dictionary/cards/delete', ['cards' => [$card->id]])
        ->assertOk()->assertHeader('HX-Trigger', 'sheet-close, dictionary-refresh')->assertSee('Karte gelöscht.');

    expect(Card::find($card->id))->toBeNull();
});

it('lässt Moderation und Verwaltung fremde geteilte Karten bearbeiten und löschen', function (Role $role) {
    $anna = User::factory()->create(['name' => 'Anna']);
    $deck = dictDeck($anna, ['is_shared' => true]);
    $card = Card::factory()->for($deck)->create(['front' => ['Hnud'], 'back' => ['dog']]);
    $andere = Card::factory()->for($deck)->create(['front' => ['Katze'], 'back' => ['cat']]);
    $mod = actingAsUser(['role' => $role]);
    Log::spy();

    $this->withHeaders(['HX-Request' => 'true'])->get('/dictionary/search?q=hnud')->assertSee('Bearbeiten');
    $this->get("/dictionary/cards/{$card->id}/edit")
        ->assertSee('Du änderst eine Karte von Anna')
        ->assertSee('hx-post="/moderation/dictionary/cards"', false);

    dictPost('/moderation/dictionary/cards', ['cards' => [$card->id], 'base' => $card->id, 'front' => ['Hund'], 'back' => ['dog']])->assertOk();
    expect($card->fresh()->front)->toBe(['Hund']);
    Log::shouldHaveReceived('info')->with('Karte korrigiert (Moderation)', ['by' => $mod->id, 'card_id' => $card->id, 'deck_id' => $deck->id]);

    dictPost('/moderation/dictionary/cards/delete', ['cards' => [$andere->id]])->assertOk()->assertSee('Karte gelöscht.');
    expect(Card::find($andere->id))->toBeNull();
    Log::shouldHaveReceived('info')->with('Karte gelöscht (Moderation)', ['by' => $mod->id, 'card_id' => $andere->id, 'deck_id' => $deck->id]);
})->with([Role::Moderator, Role::Admin]);

it('lässt auch die Moderation nicht an fremde private Karten', function () {
    $geheim = Card::factory()->for(dictDeck(User::factory()->create()))->create(['front' => ['Hund'], 'back' => ['dog']]);
    actingAsUser(['role' => Role::Admin]);

    $this->get("/dictionary/cards/{$geheim->id}/edit")->assertNotFound();
    dictPost('/moderation/dictionary/cards', ['cards' => [$geheim->id], 'base' => $geheim->id, 'front' => ['X'], 'back' => ['y']])->assertNotFound();
    dictPost('/moderation/dictionary/cards/delete', ['cards' => [$geheim->id]])->assertNotFound();

    expect($geheim->fresh()->front)->toBe(['Hund']);
});

it('bietet normalen Nutzern bei fremden Karten „Fehler melden“ und schickt den Vorschlag ohne Kartenzeile', function () {
    $anna = User::factory()->create(['name' => 'Anna']);
    $card = Card::factory()->for(dictDeck($anna, ['is_shared' => true]))->create(['front' => ['Hnud'], 'back' => ['dog']]);
    $ich = actingAsUser();

    $this->withHeaders(['HX-Request' => 'true'])->get('/dictionary/search?q=hnud')
        ->assertSee('Fehler melden')
        ->assertSee("/decks/{$card->deck_id}/cards/{$card->id}/suggest?context=dictionary", false);
    $this->get("/dictionary/cards/{$card->id}/edit")->assertOk()
        ->assertDontSee('hx-post="/dictionary/cards"', false)
        ->assertSee('Fehler melden');
    $this->get("/decks/{$card->deck_id}/cards/{$card->id}/suggest?context=dictionary")
        ->assertSee('name="context" value="dictionary"', false)
        ->assertDontSee('hx-target="#card-', false);

    dictPost("/decks/{$card->deck_id}/cards/{$card->id}/suggest", ['type' => 'typo', 'front' => ['Hund'], 'back' => ['dog'], 'context' => 'dictionary'])
        ->assertOk()
        ->assertHeader('HX-Trigger', 'sheet-close')
        ->assertSee('Dein Vorschlag ist bei Anna')
        ->assertDontSee('id="card-', false);

    expect(CardSuggestion::where('user_id', $ich->id)->count())->toBe(1);
});

it('findet die gezeigte Karte auch bei vielen ähnlichen und fasst Leerraum zusammen', function () {
    $ich = actingAsUser();
    $deck = dictDeck($ich);
    // Many older cards that contain the word but are no match.
    Card::factory()->for($deck)->count(210)->sequence(fn ($s) => ['front' => ["in {$s->index}"], 'back' => ["x {$s->index}"]])->create();
    $card = Card::factory()->for($deck)->create(['front' => ['in'], 'back' => ['ice  cream']]);
    $gleich = Card::factory()->for(dictDeck($ich, ['name' => 'Zwei']))->create(['front' => ['In'], 'back' => ['ice cream']]);

    $this->get("/dictionary/cards/{$card->id}/edit")->assertOk()
        ->assertSee('Alle (2) bearbeiten')
        ->assertSee("selected={$card->id}", false)
        ->assertSee("selected={$gleich->id}", false);
});

it('protokolliert bei gemischter Auswahl nur fremde Karten', function () {
    $mod = actingAsUser(['role' => Role::Moderator]);
    $eigen = Card::factory()->for(dictDeck($mod))->create(['front' => ['Hund'], 'back' => ['dog']]);
    $fremd = Card::factory()->for(dictDeck(User::factory()->create(), ['is_shared' => true]))->create(['front' => ['Hund'], 'back' => ['dog']]);
    Log::spy();

    dictPost('/moderation/dictionary/cards', ['cards' => [$eigen->id, $fremd->id], 'base' => $eigen->id, 'front' => ['Hund'], 'back' => ['dog', 'hound']])
        ->assertOk()->assertSee('2 Karten gespeichert.');

    expect($eigen->fresh()->back)->toBe(['dog', 'hound'])->and($fremd->fresh()->back)->toBe(['dog', 'hound']);
    Log::shouldHaveReceived('info')->once();
    Log::shouldHaveReceived('info')->with('Karte korrigiert (Moderation)', ['by' => $mod->id, 'card_id' => $fremd->id, 'deck_id' => $fremd->deck_id]);
});

it('nimmt höchstens 50 Karten auf einmal', function () {
    $ich = actingAsUser();
    $cards = Card::factory()->for(dictDeck($ich))->count(51)->create();

    dictPost('/dictionary/cards/delete', ['cards' => $cards->modelKeys()])->assertNotFound();
    expect(Card::count())->toBe(51);
});
