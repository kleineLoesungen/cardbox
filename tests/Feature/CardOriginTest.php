<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use Illuminate\Support\Carbon;

/*
 * Originals of copied cards and "geändert" for followers
 * (docs/specs/lernplaene-und-karten.md, B3 and B4).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** A shared deck by somebody else with one card, and my copy of it. */
function copiedDeck(User $ich): array
{
    $geteilt = Deck::factory()->shared()->create(['front_language' => 'de', 'back_language' => 'en']);
    $original = Card::factory()->for($geteilt)->create(['front' => ['verpassen'], 'back' => ['miss']]);
    test()->actingAs($ich)->post("/decks/{$geteilt->id}/copy");
    $kopie = Deck::where('user_id', $ich->id)->sole();

    return [$geteilt, $original, $kopie, $kopie->cards()->sole()];
}

/** The owner of the original edits it a day later. */
function editOriginal(Card $original, array $attributes): void
{
    test()->travel(1)->day();
    $original->update($attributes);
}

describe('Herkunft merken', function () {
    it('merkt sich beim Kopieren und Übernehmen das Original und seinen Stand', function () {
        $ich = User::factory()->create();
        [, $original, , $karte] = copiedDeck($ich);
        $ziel = Deck::factory()->for($ich)->create(['front_language' => 'de', 'back_language' => 'en']);

        $this->withHeaders(['HX-Request' => 'true'])->post("/decks/{$ziel->id}/adopt/{$original->id}")->assertOk();

        expect($karte)->source_card_id->toBe($original->id)
            ->and($karte->source_updated_at->equalTo($original->updated_at))->toBeTrue()
            ->and($ziel->cards()->sole()->source_card_id)->toBe($original->id);
    });

    it('meldet nichts, solange das Original unverändert ist', function () {
        $ich = User::factory()->create();
        [, , $kopie] = copiedDeck($ich);

        $this->get("/decks/{$kopie->id}")->assertDontSee('im Original geändert');
        $this->get("/decks/{$kopie->id}/origin")->assertRedirect("/decks/{$kopie->id}");
    });
});

describe('Änderungen im Original', function () {
    it('meldet geänderte Originale nur der Besitzerin der Kopie', function () {
        $ich = User::factory()->create();
        [, $original, $kopie] = copiedDeck($ich);
        editOriginal($original, ['back' => ['miss', 'lose']]);

        $this->get("/decks/{$kopie->id}")->assertSee('1 Karte wurde im Original geändert.');
        $this->get("/decks/{$kopie->id}/origin")->assertOk()->assertSee('miss, lose');

        // My copy shared: a follower sees no notice and cannot open the list.
        $kopie->forceFill(['is_shared' => true])->save();
        $andere = User::factory()->create();
        $this->actingAs($andere)->get("/decks/{$kopie->id}")->assertOk()->assertDontSee('im Original geändert');
        $this->get("/decks/{$kopie->id}/origin")->assertNotFound();
    });

    it('übernimmt die Änderung und behält den Lernstand', function () {
        $ich = User::factory()->create();
        [, $original, $kopie, $karte] = copiedDeck($ich);
        $run = app(DeckRunService::class)->forStandard($ich, $kopie);
        CardProgress::create(['user_id' => $ich->id, 'deck_run_id' => $run->id, 'card_id' => $karte->id,
            'direction' => Direction::Forward, 'box' => 3, 'due_on' => '2026-10-20', 'review_count' => 3, 'correct_count' => 3]);
        editOriginal($original, ['front' => ['verpassen'], 'back' => ['miss', 'lose'], 'hint' => '(Zug)']);

        $this->post("/decks/{$kopie->id}/cards/{$karte->id}/origin/take")->assertRedirect("/decks/{$kopie->id}/origin");

        expect($karte->fresh())->back->toBe(['miss', 'lose'])->hint->toBe('(Zug)')
            ->and(CardProgress::where('card_id', $karte->id)->sole()->box)->toBe(3);
        $this->get("/decks/{$kopie->id}")->assertDontSee('im Original geändert');
    });

    it('behält die eigene Fassung und meldet erst die nächste Änderung wieder', function () {
        $ich = User::factory()->create();
        [, $original, $kopie, $karte] = copiedDeck($ich);
        editOriginal($original, ['back' => ['lose']]);

        $this->post("/decks/{$kopie->id}/cards/{$karte->id}/origin/keep");
        expect($karte->fresh()->back)->toBe(['miss']);
        $this->get("/decks/{$kopie->id}")->assertDontSee('im Original geändert');

        editOriginal($original, ['back' => ['lose', 'miss']]);
        $this->get("/decks/{$kopie->id}")->assertSee('1 Karte wurde im Original geändert.');
    });

    it('übernimmt alle Änderungen auf einmal', function () {
        $ich = User::factory()->create();
        [$geteilt, $original, $kopie] = copiedDeck($ich);
        // A second card, copied along by copying the deck again is not
        // possible — so adopt it into the copy.
        $zweite = Card::factory()->for($geteilt)->create(['front' => ['Hund'], 'back' => ['dog']]);
        $this->withHeaders(['HX-Request' => 'true'])->post("/decks/{$kopie->id}/adopt/{$zweite->id}");
        editOriginal($original, ['back' => ['lose']]);
        $zweite->update(['back' => ['hound']]);

        $this->get("/decks/{$kopie->id}/origin")->assertSee('Alle 2 übernehmen');
        $this->post("/decks/{$kopie->id}/origin/take-all")->assertRedirect("/decks/{$kopie->id}")
            ->assertSessionHas('toast.message', '2 Änderungen übernommen.');

        expect($kopie->cards()->orderBy('created_at')->pluck('back')->all())->toBe([['lose'], ['hound']]);
    });

    it('tauscht die Seiten, wenn die Karte mit umgekehrten Sprachen übernommen wurde', function () {
        $ich = actingAsUser();
        $geteilt = Deck::factory()->shared()->create(['front_language' => 'de', 'back_language' => 'en']);
        $original = Card::factory()->for($geteilt)->create(['front' => ['verpassen'], 'back' => ['miss']]);
        $ziel = Deck::factory()->for($ich)->create(['front_language' => 'en', 'back_language' => 'de']);
        $this->withHeaders(['HX-Request' => 'true'])->post("/decks/{$ziel->id}/adopt/{$original->id}");
        $karte = $ziel->cards()->sole();
        editOriginal($original, ['back' => ['miss', 'lose']]);

        $this->post("/decks/{$ziel->id}/cards/{$karte->id}/origin/take");

        expect($karte->fresh())->front->toBe(['miss', 'lose'])->back->toBe(['verpassen']);
    });

    it('meldet nichts mehr, wenn das Original privat oder gelöscht ist, und verweigert dann das Übernehmen', function () {
        $ich = User::factory()->create();
        [$geteilt, $original, $kopie, $karte] = copiedDeck($ich);
        editOriginal($original, ['back' => ['lose']]);

        $geteilt->forceFill(['is_shared' => false])->save();
        $this->get("/decks/{$kopie->id}")->assertOk()->assertDontSee('im Original geändert');
        $this->post("/decks/{$kopie->id}/cards/{$karte->id}/origin/take")->assertNotFound();

        $original->delete();
        $this->get("/decks/{$kopie->id}")->assertOk()->assertDontSee('im Original geändert');
        $this->post("/decks/{$kopie->id}/cards/{$karte->id}/origin/keep")->assertNotFound();
        expect($karte->fresh()->back)->toBe(['miss']);
    });

    it('lässt fremde Kopien und Karten ohne Original nicht anfassen', function () {
        $ich = User::factory()->create();
        [, $original, $kopie, $karte] = copiedDeck($ich);
        editOriginal($original, ['back' => ['lose']]);
        $eigene = Card::factory()->for($kopie)->create();

        $this->actingAs(User::factory()->create());
        $this->post("/decks/{$kopie->id}/cards/{$karte->id}/origin/take")->assertNotFound();
        $this->post("/decks/{$kopie->id}/origin/take-all")->assertNotFound();

        $this->actingAs($ich);
        $this->post("/decks/{$kopie->id}/cards/{$eigene->id}/origin/take")->assertNotFound();
        $this->post("/decks/{$kopie->id}/cards/abc/origin/take")->assertNotFound();
        expect($karte->fresh()->back)->toBe(['miss']);
    });
});

describe('„geändert“ in der Lernrunde', function () {
    it('zeigt „geändert“, wenn die Besitzerin die Karte nach dem letzten Lernen geändert hat', function () {
        $ich = actingAsUser();
        $geteilt = Deck::factory()->shared()->create();
        $card = Card::factory()->for($geteilt)->create(['front' => ['Hund']]);
        $run = app(DeckRunService::class)->forStandard($ich, $geteilt);
        CardProgress::create(['user_id' => $ich->id, 'deck_run_id' => $run->id, 'card_id' => $card->id,
            'direction' => Direction::Forward, 'box' => 1, 'due_on' => '2026-10-14',
            'last_reviewed_at' => now(), 'review_count' => 1, 'correct_count' => 1]);

        $this->post('/learn/round', ['deck' => $geteilt->id]);
        $this->get('/learn/round')->assertSee('Hund')->assertDontSee('>geändert<', false);

        $this->travel(1)->hour();
        $card->update(['back' => ['dog', 'hound']]);

        $this->post('/learn/round', ['deck' => $geteilt->id]);
        $this->get('/learn/round')->assertSee('>geändert<', false);
    });

    it('zeigt „geändert“ nicht bei neuen Karten und nicht beim freien Üben', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create();

        $this->post('/learn/round', ['deck' => $deck->id]);
        $this->get('/learn/round')->assertDontSee('>geändert<', false);

        $this->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => 'forward']);
        $this->get('/learn/round')->assertDontSee('>geändert<', false);
    });
});
