<?php

use App\Enums\SuggestionStatus;
use App\Models\Card;
use App\Models\CardSuggestion;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

/*
 * "Fehler melden": suggestions to the owner of a shared deck
 * (docs/specs/lernplaene-und-karten.md, B5).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** A shared deck of Anna with one card "verpassen – miss". */
function sharedCard(): array
{
    $anna = User::factory()->create(['name' => 'Anna Lehrerin']);
    $deck = Deck::factory()->for($anna)->shared()->create(['front_language' => 'de', 'back_language' => 'en']);
    $card = Card::factory()->for($deck)->create(['front' => ['verpassen'], 'back' => ['miss']]);

    return [$anna, $deck, $card];
}

function suggest(Deck $deck, Card $card, array $input): TestResponse
{
    return test()->withHeaders(['HX-Request' => 'true'])
        ->post("/decks/{$deck->id}/cards/{$card->id}/suggest", $input + ['type' => 'wrong']);
}

describe('Vorschlag machen', function () {
    it('schickt neuen Text und Kommentar und zeigt danach „Vorschlag offen“', function () {
        [, $deck, $card] = sharedCard();
        $ich = actingAsUser(['name' => 'Ben']);

        $this->get("/decks/{$deck->id}")->assertSee('Fehler melden');
        $this->get("/decks/{$deck->id}/cards/{$card->id}/suggest")->assertOk()
            ->assertSee('Anna Lehrerin')->assertSee('value="miss"', false);

        suggest($deck, $card, ['front' => ['verpassen'], 'back' => ['miss', 'lose'], 'comment' => ' auch „lose“ '])
            ->assertOk()
            ->assertHeader('HX-Trigger', 'sheet-close')
            ->assertSee('Vorschlag offen')
            ->assertSee('Dein Vorschlag ist bei Anna Lehrerin.');

        expect(CardSuggestion::sole())
            ->user_id->toBe($ich->id)
            ->status->toBe(SuggestionStatus::Open)
            // The front was not changed, so it proposes nothing.
            ->proposed_front->toBeNull()
            ->proposed_back->toBe(['miss', 'lose'])
            ->comment->toBe('auch „lose“');
        expect($card->fresh()->back)->toBe(['miss']);
    });

    it('nimmt einen reinen Kommentar an, verlangt aber Text oder Kommentar', function () {
        [, $deck, $card] = sharedCard();
        actingAsUser();

        suggest($deck, $card, ['front' => ['verpassen'], 'back' => ['miss'], 'comment' => '   '])
            ->assertStatus(422)->assertSee('Bitte einen neuen Text oder einen Kommentar angeben.');
        suggest($deck, $card, ['comment' => 'Das ist britisch.'])->assertOk();

        expect(CardSuggestion::sole())->proposesText()->toBeFalse();
    });

    it('prüft Art, Länge und Form der Eingaben', function (array $input) {
        [, $deck, $card] = sharedCard();
        actingAsUser();

        suggest($deck, $card, $input)->assertStatus(422);
        expect(CardSuggestion::count())->toBe(0);
    })->with([
        'unbekannte Art' => [['type' => 'spam', 'comment' => 'x']],
        'Kommentar zu lang' => [['comment' => str_repeat('x', 301)]],
        'Kommentar als Liste' => [['comment' => ['x']]],
        'Bedeutung zu lang' => [['back' => [str_repeat('x', 501)]]],
    ]);

    it('lässt höchstens einen offenen Vorschlag je Person und Karte zu', function () {
        [, $deck, $card] = sharedCard();
        actingAsUser();

        suggest($deck, $card, ['comment' => 'eins'])->assertOk();
        suggest($deck, $card, ['comment' => 'zwei'])->assertStatus(422)->assertSee('Du hast schon einen Vorschlag zu dieser Karte.');
        $this->get("/decks/{$deck->id}/cards/{$card->id}/suggest")->assertSee('Du hast schon einen Vorschlag');

        // Another person may still suggest.
        actingAsUser();
        suggest($deck, $card, ['comment' => 'drei'])->assertOk();
        expect(CardSuggestion::count())->toBe(2);
    });

    it('verweigert Vorschläge zu eigenen Karten und zu fremden privaten Stapeln', function () {
        [$anna, $deck, $card] = sharedCard();
        $privat = Deck::factory()->create();
        $privateKarte = Card::factory()->for($privat)->create();

        $this->actingAs($anna);
        $this->get("/decks/{$deck->id}")->assertDontSee('Fehler melden');
        $this->get("/decks/{$deck->id}/cards/{$card->id}/suggest")->assertNotFound();
        suggest($deck, $card, ['comment' => 'x'])->assertNotFound();

        actingAsUser();
        suggest($privat, $privateKarte, ['comment' => 'x'])->assertNotFound();
        // A card addressed through a deck it does not belong to.
        suggest($deck, $privateKarte, ['comment' => 'x'])->assertNotFound();
        $this->get("/decks/{$deck->id}/cards/abc/suggest")->assertNotFound();

        expect(CardSuggestion::count())->toBe(0);
    });

    it('drosselt Vorschläge auf 20 pro Stunde', function () {
        [$anna, $deck] = sharedCard();
        $cards = Card::factory()->for($deck)->count(21)->create();
        actingAsUser();

        foreach ($cards->take(20) as $card) {
            suggest($deck, $card, ['comment' => 'x'])->assertOk();
        }
        suggest($deck, $cards->last(), ['comment' => 'x'])->assertStatus(429);
    });
});

describe('Entscheiden', function () {
    it('zeigt der Besitzerin die Vorschläge mit Anzeigename, nie mit Benutzername', function () {
        [$anna, $deck, $card] = sharedCard();
        $ben = actingAsUser(['name' => 'Ben Schüler', 'username' => 'ben.geheim']);
        suggest($deck, $card, ['back' => ['lose'], 'comment' => 'falsch']);

        $this->actingAs($anna);
        $this->get('/decks')->assertSee('1 Vorschlag');
        $this->get("/decks/{$deck->id}")->assertSee('1 Vorschlag zu deinen Karten.');
        $this->get("/decks/{$deck->id}/suggestions")->assertOk()
            ->assertSee('Ben Schüler')
            ->assertDontSee('ben.geheim')
            ->assertSee('Falsche Übersetzung')
            ->assertSee('„falsch“', false);
    });

    it('übernimmt einen Vorschlag in die Karte und zeigt dem Absender den Status', function () {
        [$anna, $deck, $card] = sharedCard();
        $ben = actingAsUser();
        suggest($deck, $card, ['back' => ['lose', 'miss']]);
        $suggestion = CardSuggestion::sole();

        $this->actingAs($anna)->post("/decks/{$deck->id}/suggestions/{$suggestion->id}/accept")
            ->assertRedirect("/decks/{$deck->id}/suggestions")
            ->assertSessionHas('toast.message', 'Vorschlag übernommen, Karte geändert.');

        expect($card->fresh())->front->toBe(['verpassen'])->back->toBe(['lose', 'miss'])
            ->and($suggestion->fresh())->status->toBe(SuggestionStatus::Accepted)->decided_at->not->toBeNull();
        $this->get("/decks/{$deck->id}/suggestions")->assertRedirect("/decks/{$deck->id}");

        $this->actingAs($ben)->get("/decks/{$deck->id}")->assertSee('Vorschlag übernommen')->assertSee('Fehler melden');
    });

    it('lehnt ab, ohne die Karte zu ändern; ein Kommentar ohne Text wird nur erledigt', function () {
        [$anna, $deck, $card] = sharedCard();
        actingAsUser();
        suggest($deck, $card, ['back' => ['lose']]);
        $abgelehnt = CardSuggestion::sole();
        actingAsUser();
        suggest($deck, $card, ['comment' => 'Danke!']);
        $kommentar = CardSuggestion::where('comment', 'Danke!')->sole();

        $this->actingAs($anna);
        $this->get("/decks/{$deck->id}/suggestions")->assertSee('Erledigt');
        $this->post("/decks/{$deck->id}/suggestions/{$abgelehnt->id}/decline");
        $this->post("/decks/{$deck->id}/suggestions/{$kommentar->id}/accept")
            ->assertSessionHas('toast.message', 'Als erledigt markiert.');

        expect($card->fresh()->back)->toBe(['miss'])
            ->and($abgelehnt->fresh()->status)->toBe(SuggestionStatus::Declined)
            ->and($kommentar->fresh()->status)->toBe(SuggestionStatus::Accepted);
    });

    it('lässt nur die Besitzerin entscheiden und nur offene Vorschläge', function () {
        [$anna, $deck, $card] = sharedCard();
        $ben = actingAsUser();
        suggest($deck, $card, ['back' => ['lose']]);
        $suggestion = CardSuggestion::sole();

        $this->post("/decks/{$deck->id}/suggestions/{$suggestion->id}/accept")->assertNotFound();
        $this->get("/decks/{$deck->id}/suggestions")->assertNotFound();
        $fremd = Deck::factory()->for($ben)->create();
        $this->post("/decks/{$fremd->id}/suggestions/{$suggestion->id}/accept")->assertNotFound();

        $this->actingAs($anna)->post("/decks/{$deck->id}/suggestions/{$suggestion->id}/decline");
        $this->post("/decks/{$deck->id}/suggestions/{$suggestion->id}/accept")->assertNotFound();

        expect($card->fresh()->back)->toBe(['miss']);
    });

    it('weist darauf hin, wenn die Karte seit dem Vorschlag geändert wurde', function () {
        [$anna, $deck, $card] = sharedCard();
        actingAsUser();
        suggest($deck, $card, ['comment' => 'x']);

        $this->travel(1)->hour();
        $card->update(['back' => ['miss', 'lose']]);

        $this->actingAs($anna)->get("/decks/{$deck->id}/suggestions")->assertSee('Die Karte wurde seit dem Vorschlag geändert.');
    });

    it('löscht Vorschläge mit ihrer Karte und mit dem Konto des Absenders', function () {
        [, $deck, $card] = sharedCard();
        $ben = actingAsUser();
        suggest($deck, $card, ['comment' => 'x']);
        $zweite = Card::factory()->for($deck)->create();
        suggest($deck, $zweite, ['comment' => 'y']);

        $card->delete();
        expect(CardSuggestion::count())->toBe(1);

        $ben->delete();
        expect(CardSuggestion::count())->toBe(0);
    });
});
