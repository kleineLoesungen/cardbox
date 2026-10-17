<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use App\Services\LeitnerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * "Antworten eintippen" in rounds (docs/specs/schreiben-und-erinnern.md, A).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

function taDeck(User $owner, array $cards, bool $typing = true, array $attributes = []): Deck
{
    $deck = Deck::factory()->for($owner)->create($attributes + ['front_language' => 'de', 'back_language' => 'en']);
    foreach ($cards as $i => [$front, $back]) {
        Card::factory()->for($deck)->create(['front' => (array) $front, 'back' => (array) $back, 'created_at' => now()->subMinutes(100 - $i)]);
    }
    if ($typing) {
        test()->post("/decks/{$deck->id}/typing", ['on' => '1'])->assertRedirect();
    }

    return $deck;
}

function taDue(User $user, Card $card, int $box = 1): void
{
    $run = app(DeckRunService::class)->forStandard($user, Deck::findOrFail($card->deck_id));
    CardProgress::create(['user_id' => $user->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
        'box' => $box, 'due_on' => app(LeitnerService::class)->today()->toDateString(), 'review_count' => 1, 'correct_count' => 1]);
}

/** Sends a typed answer (or "Weiß ich nicht") for the card that is up. */
function taType(?string $typed): TestResponse
{
    $round = session('study_round');
    $input = ['card' => $round['queue'][0]['card'], 'step' => $round['step']];

    return test()->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', $input + ($typed === null ? ['skip' => '1'] : ['typed' => $typed]))->assertOk();
}

function taBox(User $user, Card $card): int
{
    return CardProgress::where('user_id', $user->id)->where('card_id', $card->id)->sole()->box;
}

it('fragt mit Eingabefeld statt Umdrehen, nur bei eingeschaltetem Stapel', function () {
    $ich = actingAsUser();
    $tippen = taDeck($ich, [['Hund', 'dog']]);
    $umdrehen = taDeck($ich, [['Katze', 'cat']], false);
    taDue($ich, $tippen->cards()->sole());
    taDue($ich, $umdrehen->cards()->sole());

    $this->get("/decks/{$tippen->id}")->assertSee('Antworten eintippen')->assertSee('badge-success shrink-0">an', false);

    $this->post('/learn/round', ['deck' => $tippen->id]);
    $this->get('/learn/round')->assertSee('name="typed"', false)->assertSee('Weiß ich nicht')
        ->assertDontSee('data-flip role="button"', false)
        // The answer is not in the page before checking.
        ->assertDontSee('dog');

    $this->post('/learn/round', ['deck' => $umdrehen->id]);
    $this->get('/learn/round')->assertSee('data-flip role="button"', false)->assertDontSee('name="typed"', false);
});

it('wertet beim Lernen richtig und fast richtig als gewusst, falsch als nicht gewusst', function () {
    $ich = actingAsUser();
    $deck = taDeck($ich, [['empfangen', 'receive'], ['Hund', 'dog'], ['Katze', 'cat']]);
    [$a, $b, $c] = $deck->cards()->orderBy('created_at')->get()->all();
    foreach ([$a, $b, $c] as $card) {
        taDue($ich, $card, 2);
    }

    $this->post('/learn/round', ['deck' => $deck->id]);
    taType('recieve')->assertSee('Fast! So schreibt man es:')->assertSee('<span class="">rec</span><span class="answer-diff">ei</span><span class="">ve</span>', false)
        ->assertSee('Anders als bei dir: ei')
        ->assertSee('Du hast geschrieben:')->assertSee('recieve')->assertSee('data-result-next', false);
    taType('Dog')->assertSee('Richtig!')->assertDontSee('Du hast geschrieben');
    taType('horse')->assertSee('Richtig ist:')->assertSee('cat')->assertSee('horse');

    expect(taBox($ich, $a))->toBe(3)
        ->and(taBox($ich, $b))->toBe(3)
        ->and(taBox($ich, $c))->toBe(1);
});

it('lässt im Test üben fast richtig wiederkommen', function () {
    $ich = actingAsUser();
    $deck = taDeck($ich, [['empfangen', 'receive']]);

    $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
    taType('recieve')->assertSee('Im Test zählt die Rechtschreibung');
    expect(session('study_round')['queue'])->toHaveCount(1);

    taType('receive');
    $this->get('/learn/round')->assertSee('Alles gewusst!');
    expect(CardProgress::count())->toBe(0);
});

it('zählt „Weiß ich nicht“ und leere Eingaben als nicht gewusst', function (?string $typed) {
    $ich = actingAsUser();
    $deck = taDeck($ich, [['Hund', 'dog']]);
    $card = $deck->cards()->sole();
    taDue($ich, $card, 3);

    $this->post('/learn/round', ['deck' => $deck->id]);
    taType($typed)->assertSee('Richtig ist:')->assertDontSee('Du hast geschrieben');

    expect(taBox($ich, $card))->toBe(1)
        ->and(DB::table('study_days')->value('answers'))->toBe(0);
})->with([null, '', '   ']);

it('wertet doppeltes Prüfen einmal und führt nach dem Ergebnis weiter', function () {
    $ich = actingAsUser();
    $deck = taDeck($ich, [['Hund', 'dog'], ['Katze', 'cat']]);
    foreach ($deck->cards()->get() as $card) {
        taDue($ich, $card, 2);
    }

    $this->post('/learn/round', ['deck' => $deck->id]);
    $round = session('study_round');
    $first = Card::find($round['queue'][0]['card']);
    $input = ['card' => $first->id, 'step' => $round['step'], 'typed' => 'wrong'];
    $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', $input)->assertSee('Richtig ist:');
    // Late double tap from the same step: no result, no second rating.
    $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', $input)->assertDontSee('Richtig ist:');

    expect(CardProgress::where('card_id', $first->id)->sole())->box->toBe(1)->review_count->toBe(2);
    // "Weiter" (htmx) brings only the next card, reloading the whole page as well.
    $this->get('/learn/round')->assertSee('name="typed"', false)->assertDontSee('Richtig ist:')->assertDontSee('<html', false);
    $this->flushHeaders()->get('/learn/round')->assertSee('name="typed"', false)->assertSee('keyboard-keeper', false);
});

it('führt neue Wörter offen ein und tippt erst bei der Abfrage', function () {
    $ich = actingAsUser();
    taDeck($ich, [['Hund', 'dog']]);

    $this->post('/learn/round');
    $this->get('/learn/round')->assertSee('Neues Wort')->assertDontSee('name="typed"', false);
    skipIntros();
    $this->get('/learn/round')->assertSee('name="typed"', false);
    taType('dog')->assertSee('Richtig!');
});

it('übt ein Fach weiter mit Umdrehen', function () {
    $ich = actingAsUser();
    $deck = taDeck($ich, [['Hund', 'dog']]);
    taDue($ich, $deck->cards()->sole(), 2);

    $this->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => 'forward', 'box' => '2']);
    $this->get('/learn/round')->assertSee('data-flip', false)->assertDontSee('name="typed"', false);
});

it('prüft Rechenkarten genau', function () {
    $ich = actingAsUser();
    $deck = taDeck($ich, [['34 · 37', '1258']], true, ['front_language' => 'formula', 'back_language' => 'result']);
    taDue($ich, $deck->cards()->sole(), 2);

    $this->post('/learn/round', ['deck' => $deck->id]);
    taType('1285')->assertSee('Richtig ist:');
});

it('tippt je Stapel in einer gemischten Runde und rückwärts in der anderen Sprache', function () {
    $ich = actingAsUser();
    $tippen = taDeck($ich, [['Hund', 'dog']]);
    $umdrehen = taDeck($ich, [['Katze', 'cat']], false);
    taDue($ich, $tippen->cards()->sole());
    taDue($ich, $umdrehen->cards()->sole());

    $this->post('/learn/round');
    foreach (session('study_round')['queue'] as $item) {
        $html = $this->get('/learn/round')->getContent();
        $typing = $item['card'] === $tippen->cards()->sole()->id;
        expect(str_contains($html, 'name="typed"'))->toBe($typing);
        $typing ? taType('dog') : $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', [
            'card' => $item['card'], 'step' => session('study_round')['step'], 'known' => '1',
        ]);
    }

    // Back → front: the answer is German.
    $this->post("/decks/{$tippen->id}/reverse", ['on' => '1']);
    $card = $tippen->cards()->sole();
    $run = DB::table('deck_runs')->where('deck_id', $tippen->id)->value('id');
    CardProgress::create(['user_id' => $ich->id, 'deck_run_id' => $run, 'card_id' => $card->id, 'direction' => Direction::Backward,
        'box' => 1, 'due_on' => '2026-10-14', 'review_count' => 1, 'correct_count' => 1]);
    $this->post('/learn/round', ['deck' => $tippen->id]);
    $this->get('/learn/round')->assertSee('lang="de"', false)->assertSee('name="typed"', false);
    taType('hund')->assertSee('Richtig!');
});

it('zählt fast richtig im Test nicht fürs Tagesziel und wertet getippte Antworten ohne Schalter nicht', function () {
    $ich = actingAsUser();
    $deck = taDeck($ich, [['empfangen', 'receive']]);
    $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
    taType('recieve');
    expect((int) DB::table('study_days')->value('answers'))->toBe(0);

    $ohne = taDeck($ich, [['Katze', 'cat']], false);
    taDue($ich, $ohne->cards()->sole());
    $this->post('/learn/round', ['deck' => $ohne->id]);
    taType('cat')->assertDontSee('Richtig!');
    expect(CardProgress::where('card_id', $ohne->cards()->sole()->id)->sole()->review_count)->toBe(1);
});

it('schaltet das Eintippen für gefolgte Stapel ein und aus, ohne rückwärts anzufassen', function () {
    $ich = actingAsUser();
    $geteilt = Deck::factory()->shared()->create();
    $ich->followedDecks()->attach($geteilt);
    $this->post("/decks/{$geteilt->id}/reverse", ['on' => '1']);

    $this->post("/decks/{$geteilt->id}/typing", ['on' => '1'])->assertSessionHas('toast.message', 'Bei diesem Stapel tippst du die Antworten jetzt ein.');
    expect(DB::table('deck_preferences')->first(['with_reverse', 'type_answers']))->toEqual((object) ['with_reverse' => true, 'type_answers' => true]);

    $this->post("/decks/{$geteilt->id}/typing", ['on' => '0']);
    expect(DB::table('deck_preferences')->first(['with_reverse', 'type_answers']))->toEqual((object) ['with_reverse' => true, 'type_answers' => false]);
});

it('nimmt den Schalter beim Kopieren mit', function () {
    $ich = actingAsUser();
    $geteilt = Deck::factory()->shared()->create();
    $ich->followedDecks()->attach($geteilt);
    $this->post("/decks/{$geteilt->id}/typing", ['on' => '1']);

    $this->post("/decks/{$geteilt->id}/copy");

    $copy = Deck::where('user_id', $ich->id)->sole();
    expect(DB::table('deck_preferences')->where('deck_id', $copy->id)->value('type_answers'))->toBeTrue();
});

it('schaltet das Eintippen nur für eigene und gelernte Stapel', function () {
    actingAsUser();
    $privat = Deck::factory()->create();
    $geteilt = Deck::factory()->shared()->create();

    $this->post("/decks/{$privat->id}/typing", ['on' => '1'])->assertNotFound();
    $this->post("/decks/{$geteilt->id}/typing", ['on' => '1'])->assertNotFound();
    $this->post('/decks/abc/typing', ['on' => '1'])->assertNotFound();

    expect(DB::table('deck_preferences')->count())->toBe(0);
});
