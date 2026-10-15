<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Services\DeckRunService;
use Illuminate\Support\Carbon;

/*
 * Learning only part of a deck, and "Folgen und diese Karte lernen" in the
 * dictionary (issue #10).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** A shared "book" with cards in book order. */
function book(int $cards = 5): array
{
    $deck = Deck::factory()->shared()->create(['name' => 'Buch', 'front_language' => 'de', 'back_language' => 'en']);
    $list = [];
    foreach (range(1, $cards) as $i) {
        $list[] = Card::factory()->for($deck)->create(['front' => ["Wort {$i}"], 'back' => ["word {$i}"], 'created_at' => now()->subMinutes(100 - $i)]);
    }

    return [$deck, $list];
}

function startDeckRound(Deck $deck): array
{
    test()->post('/learn/round', ['deck' => $deck->id]);

    return array_column(session('study_round')['queue'], 'card');
}

it('lernt nur ausgewählte Karten und zeigt die anderen als pausiert', function () {
    $ich = actingAsUser();
    [$deck, $cards] = book();
    $ich->followedDecks()->attach($deck);

    $this->post("/decks/{$deck->id}/selection", ['cards' => [$cards[0]->id, $cards[1]->id]])
        ->assertRedirect("/decks/{$deck->id}");

    expect(startDeckRound($deck))->toBe([$cards[0]->id, $cards[1]->id]);
    $this->get("/decks/{$deck->id}")->assertSee('2 von 5')->assertSee('pausiert')->assertSee('Nur diesen Stapel lernen')->assertSee('(2)');
});

it('lässt neue Karten der Besitzerin pausiert, bis sie angehakt werden', function () {
    $ich = actingAsUser();
    [$deck, $cards] = book(3);
    $ich->followedDecks()->attach($deck);
    $this->post("/decks/{$deck->id}/selection", ['cards' => [$cards[0]->id]]);

    $neu = Card::factory()->for($deck)->create(['front' => ['Kapitel 2'], 'back' => ['chapter 2']]);

    expect(startDeckRound($deck))->not->toContain($neu->id);
});

it('beendet die Auswahl, wenn alle Karten angehakt sind, sodass neue wieder aktiv sind', function () {
    $ich = actingAsUser();
    [$deck, $cards] = book(3);
    $ich->followedDecks()->attach($deck);

    $this->post("/decks/{$deck->id}/selection", ['cards' => array_map(fn ($c) => $c->id, $cards)]);
    $neu = Card::factory()->for($deck)->create(['front' => ['Neu'], 'back' => ['new']]);

    expect(startDeckRound($deck))->toContain($neu->id);
    $this->get("/decks/{$deck->id}")->assertDontSee('pausiert');
});

it('verlangt mindestens eine Karte und ignoriert fremde Karten', function () {
    $ich = actingAsUser();
    [$deck] = book(2);
    $fremd = Card::factory()->create();

    $this->from("/decks/{$deck->id}/selection")->post("/decks/{$deck->id}/selection", ['cards' => [$fremd->id, 'abc']])
        ->assertSessionHasErrors(['cards' => 'Bitte mindestens eine Karte wählen.']);
});

it('zählt pausierte Karten nicht als fällig und behält ihren Lernstand', function () {
    $ich = actingAsUser();
    [$deck, $cards] = book(2);
    $ich->followedDecks()->attach($deck);
    $run = app(DeckRunService::class)->forStandard($ich, $deck);
    foreach ($cards as $card) {
        CardProgress::create(['user_id' => $ich->id, 'deck_run_id' => $run->id, 'card_id' => $card->id,
            'direction' => Direction::Forward, 'box' => 2, 'due_on' => '2026-10-14', 'review_count' => 2, 'correct_count' => 2]);
    }
    $this->get('/learn')->assertSee('2 Karten');

    $this->post("/decks/{$deck->id}/selection", ['cards' => [$cards[0]->id]]);

    $this->get('/learn')->assertSee('1 Karte');
    $this->post('/learn/round');
    expect(array_column(session('study_round')['queue'], 'card'))->toBe([$cards[0]->id])
        ->and(CardProgress::count())->toBe(2);

    // Switching it on again continues where it was.
    $this->post("/decks/{$deck->id}/selection/all");
    $this->get('/learn')->assertSee('2 Karten');
});

it('gilt auch für eigene Stapel und nur für die eigene Person', function () {
    $ich = actingAsUser();
    $deck = Deck::factory()->for($ich)->create();
    [$a, $b] = Card::factory()->for($deck)->count(2)->create()->all();
    $this->post("/decks/{$deck->id}/selection", ['cards' => [$a->id]]);
    expect(startDeckRound($deck))->toBe([$a->id]);

    $deck->forceFill(['is_shared' => true])->save();
    actingAsUser();
    expect(startDeckRound($deck))->toEqualCanonicalizing([$a->id, $b->id]);
});

it('verweigert die Auswahl bei fremden privaten Stapeln', function () {
    actingAsUser();
    $privat = Deck::factory()->create();

    $this->get("/decks/{$privat->id}/selection")->assertNotFound();
    $this->post("/decks/{$privat->id}/selection/all")->assertNotFound();
});

it('folgt im Wörterbuch dem Stapel und lernt erst einmal nur diese Karte', function () {
    $ich = actingAsUser();
    [$deck, $cards] = book(3);

    $this->withHeaders(['HX-Request' => 'true'])->get('/dictionary/search?q=wort+2')->assertSee('Lernen')->assertSee('lernst du erst einmal nur dieses Wort');
    $this->withHeaders(['HX-Request' => 'true'])->post("/dictionary/learn/{$cards[1]->id}")->assertOk()->assertSee('lernst du');

    expect($ich->followedDecks()->pluck('decks.id')->all())->toBe([$deck->id])
        ->and(startDeckRound($deck))->toBe([$cards[1]->id]);

    // A paused card of a followed deck: "Lernen" adds it, without the hint about following.
    $this->withHeaders(['HX-Request' => 'true'])->get('/dictionary/search?q=wort+3')->assertSee('Lernen')->assertDontSee('erst einmal nur dieses Wort');
    $this->withHeaders(['HX-Request' => 'true'])->post("/dictionary/learn/{$cards[2]->id}");
    $this->withHeaders(['HX-Request' => 'true'])->get('/dictionary/search?q=wort+3')->assertSee('lernst du');
    expect(startDeckRound($deck))->toBe([$cards[1]->id, $cards[2]->id]);
});

it('ändert im Wörterbuch nichts an einem Stapel, den man ganz lernt', function () {
    $ich = actingAsUser();
    [$deck, $cards] = book(2);
    $ich->followedDecks()->attach($deck);

    $this->withHeaders(['HX-Request' => 'true'])->get('/dictionary/search?q=wort+1')->assertSee('lernst du')->assertDontSee('erst einmal nur dieses Wort');
    $this->withHeaders(['HX-Request' => 'true'])->post("/dictionary/learn/{$cards[0]->id}")->assertOk();

    expect(startDeckRound($deck))->toHaveCount(2);
});

it('lässt im Wörterbuch keine Karten aus fremden privaten Stapeln lernen', function () {
    actingAsUser();
    $privat = Card::factory()->for(Deck::factory()->create())->create();

    $this->withHeaders(['HX-Request' => 'true'])->post("/dictionary/learn/{$privat->id}")->assertNotFound();
});
