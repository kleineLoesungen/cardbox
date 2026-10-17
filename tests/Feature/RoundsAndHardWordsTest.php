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

/*
 * Cards per round and "knifflige Wörter" (docs/specs/runden-und-knifflige-woerter.md).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

function rhDeck(User $owner, int $count): Deck
{
    $deck = Deck::factory()->for($owner)->create(['front_language' => 'de', 'back_language' => 'en']);
    foreach (range(1, $count) as $i) {
        Card::factory()->for($deck)->create(['front' => ["Wort {$i}"], 'back' => ["word {$i}"], 'created_at' => now()->subMinutes(1000 - $i)]);
    }

    return $deck;
}

/** Progress with a number of wrong answers. */
function rhProgress(User $user, Card $card, int $box, int $wrong, int $dueInDays = 0): void
{
    $run = app(DeckRunService::class)->forStandard($user, Deck::findOrFail($card->deck_id));
    CardProgress::create(['user_id' => $user->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
        'box' => $box, 'due_on' => $box >= 5 ? null : app(LeitnerService::class)->today()->addDays($dueInDays)->toDateString(),
        'review_count' => $wrong + 2, 'correct_count' => 2]);
}

describe('Karten je Runde', function () {
    it('nimmt so viele Karten je Runde, wie unter Mein Konto gewählt', function () {
        $ich = actingAsUser();
        rhDeck($ich, 25);

        $this->get('/account')->assertSee('Karten je Runde')->assertSee('value="20" checked', false);
        $this->post('/account/round-size', ['round_size' => '10'])->assertSessionHas('toast.message', 'Eine Runde hat jetzt 10 Karten.');

        $this->post('/learn/round');
        expect(roundQuestions())->toHaveCount(10);
        $this->get('/account')->assertSee('value="10" checked', false);
    });

    it('lehnt andere Rundengrößen ab', function (mixed $size) {
        $ich = actingAsUser();

        $this->from('/account')->post('/account/round-size', ['round_size' => $size])->assertSessionHasErrors('round_size', null, 'round');
        $this->get('/account')->assertOk();

        expect(app(LeitnerService::class)->roundSize($ich))->toBe(20);
    })->with(['7', 'viele', [['10']]]);

    it('bietet nach dem Tagesziel so viele neue Karten an, wie eine Runde fasst', function () {
        $ich = actingAsUser();
        rhDeck($ich, 40);
        $this->post('/account/round-size', ['round_size' => '5']);
        DB::table('learning_settings')->where('user_id', $ich->id)->update(['daily_goal' => 10]);

        foreach (range(1, 4) as $round) {
            $this->post('/learn/round');
            foreach (roundQuestions() as $i) {
                skipIntros();
                $r = session('study_round');
                $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => $r['queue'][0]['card'], 'step' => $r['step'], 'known' => '1']);
            }
        }

        $this->flushHeaders()->get('/learn')->assertSee('Noch 5 neue Karten');
    });
});

describe('Knifflige Wörter', function () {
    it('markiert oft falsch beantwortete Karten und lässt sie üben', function () {
        $ich = actingAsUser();
        $deck = rhDeck($ich, 4);
        [$a, $b, $c, $d] = $deck->cards()->orderBy('created_at')->get()->all();
        rhProgress($ich, $a, 1, 3);     // knifflig
        rhProgress($ich, $b, 2, 2);     // two wrong: not yet
        rhProgress($ich, $c, 5, 4);     // "Kann ich": no longer
        rhProgress($ich, $d, 3, 5);     // knifflig

        $html = $this->get("/decks/{$deck->id}")->assertSee('Knifflige Wörter üben (2)')->getContent();
        expect(substr_count($html, '>knifflig</span>'))->toBe(2);

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id, 'only' => 'hard']);
        expect(array_column(session('study_round')['queue'], 'card'))->toEqualCanonicalizing([$a->id, $d->id]);
    });

    it('zeigt keinen Knopf ohne knifflige Wörter und nicht bei fremden Stapeln', function () {
        $ich = actingAsUser();
        $deck = rhDeck($ich, 2);
        $this->get("/decks/{$deck->id}")->assertDontSee('Knifflige Wörter üben');

        $fremd = Deck::factory()->shared()->create();
        Card::factory()->for($fremd)->create();
        $this->get("/decks/{$fremd->id}")->assertDontSee('Knifflige Wörter üben');
    });

    it('nennt am Rundenende knifflige Wörter dieser Runde mit Eselsbrücken-Hinweis', function () {
        $ich = actingAsUser();
        $deck = rhDeck($ich, 2);
        [$a, $b] = $deck->cards()->orderBy('created_at')->get()->all();
        rhProgress($ich, $a, 1, 2);
        rhProgress($ich, $b, 1, 0);

        $this->post('/learn/round', ['deck' => $deck->id]);
        $answer = fn (bool $known) => $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', [
            'card' => session('study_round')['queue'][0]['card'], 'step' => session('study_round')['step'], 'known' => $known ? '1' : '0',
        ]);
        $answer(false);   // Wort 1: third wrong answer → knifflig
        $answer(true);
        $answer(true);    // repeat of Wort 1

        $this->flushHeaders()->get('/learn/round')
            ->assertSee('Knifflig:')->assertSee('Wort 1 – word 1')->assertDontSee('Wort 2 – word 2')
            ->assertSee('Eselsbrücke');
    });

    it('übt knifflige Wörter nochmal mit derselben Auswahl', function () {
        $ich = actingAsUser();
        $deck = rhDeck($ich, 3);
        $card = $deck->cards()->orderBy('created_at')->first();
        rhProgress($ich, $card, 1, 3);

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id, 'only' => 'hard']);
        $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => $card->id, 'step' => 0, 'known' => '1'])
            ->assertSee('Nochmal üben')->assertSee('name="only" value="hard"', false);
    });
});
