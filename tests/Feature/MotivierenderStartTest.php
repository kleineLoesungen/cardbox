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
 * New words shown first, the daily goal, the growing vocabulary
 * (docs/specs/motivierender-start.md).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

function msDeck(User $owner, int $count, array $attributes = []): Deck
{
    $deck = Deck::factory()->for($owner)->create(['front_language' => 'de', 'back_language' => 'en'] + $attributes);
    foreach (range(1, $count) as $i) {
        Card::factory()->for($deck)->create(['front' => ["Wort {$i}"], 'back' => ["word {$i}"], 'created_at' => now()->subMinutes(1000 - $i)]);
    }

    return $deck;
}

/** The queue as "intro:card" / "card", in order. */
function msQueue(): array
{
    return array_map(fn (array $i) => (($i['intro'] ?? false) ? 'intro:' : '').$i['card'], session('study_round')['queue']);
}

/** Answers whatever is up (intros with "Weiter"). */
function msNext(bool $known = true): TestResponse
{
    $round = session('study_round');

    return test()->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', [
        'card' => $round['queue'][0]['card'],
        'step' => $round['step'],
        'known' => $known ? '1' : '0',
    ])->assertOk();
}

/** A progress row: box and days until due (null = mastered, no due date). */
function msProgress(User $user, Card $card, int $box, ?int $dueInDays = 0, ?Carbon $reviewed = null, Direction $direction = Direction::Forward): void
{
    $run = app(DeckRunService::class)->forStandard($user, Deck::findOrFail($card->deck_id));
    CardProgress::create(['user_id' => $user->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => $direction, 'box' => $box,
        'due_on' => $dueInDays === null ? null : app(LeitnerService::class)->today()->addDays($dueInDays)->toDateString(),
        'last_reviewed_at' => $reviewed ?? now()->subDay(), 'review_count' => 1, 'correct_count' => 1]);
}

describe('Neue Wörter zuerst zeigen', function () {
    it('zeigt ein neues Wort offen und fragt es drei Karten später', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 3);
        [$a, $b, $c] = $deck->cards()->orderBy('created_at')->get()->all();

        $this->post('/learn/round', ['deck' => $deck->id]);
        expect(msQueue())->toBe(["intro:{$a->id}", "intro:{$b->id}", "intro:{$c->id}", $a->id, $b->id, $c->id]);

        $this->get('/learn/round')->assertSee('Neues Wort')->assertSee('Wort 1')->assertSee('word 1')
            ->assertSee('Merk es dir')->assertSee('>Weiter</button>', false)->assertSee('1 / 3')
            ->assertSee('data-intro-screen', false)->assertDontSee('data-card-screen>', false);

        msNext();
        expect(CardProgress::count())->toBe(0)
            ->and(DB::table('study_days')->count())->toBe(0);
        msNext();
        msNext();
        msNext(true);
        expect(CardProgress::where('card_id', $a->id)->sole()->box)->toBe(1);
    });

    it('lässt zwischen Einführung und Abfrage drei Karten, auch zwischen fälligen', function () {
        $ich = actingAsUser();
        $ids = msDeck($ich, 6)->cards()->orderBy('created_at')->pluck('id')->all();
        $due = msDeck($ich, 2)->cards()->orderBy('created_at')->get();
        foreach ($due as $card) {
            msProgress($ich, $card, 1, 0);
        }
        [$x, $y] = $due->modelKeys();

        $this->post('/learn/round');

        // Due ones first; then new ones in blocks: each question three places after its intro.
        expect(msQueue())->toBe([
            $x, $y,
            "intro:{$ids[0]}", "intro:{$ids[1]}", "intro:{$ids[2]}", "intro:{$ids[3]}", $ids[0], $ids[1], $ids[2], $ids[3],
            "intro:{$ids[4]}", "intro:{$ids[5]}", $ids[4], $ids[5],
        ]);
    });

    it('stellt fällige Karten, Test üben und Fach üben ohne Einführung', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 2);
        [$a, $b] = $deck->cards()->orderBy('created_at')->get()->all();
        msProgress($ich, $a, 1, 0);
        msProgress($ich, $b, 2, 3);

        $this->post('/learn/round');
        expect(msQueue())->toBe([$a->id]);
        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        expect(collect(msQueue())->filter(fn ($i) => str_starts_with($i, 'intro:')))->toBeEmpty();
        $this->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => 'forward', 'box' => '2']);
        expect(msQueue())->toBe([$b->id]);
    });

    it('führt rückwärts nicht noch einmal ein und fragt es nach vorwärts', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 1);
        $card = $deck->cards()->sole();
        $this->post("/decks/{$deck->id}/reverse", ['on' => '1']);

        $this->post('/learn/round');

        expect(array_map(fn ($i) => (($i['intro'] ?? false) ? 'intro:' : '').$i['direction'], session('study_round')['queue']))
            ->toBe(['intro:forward', 'forward', 'backward']);
    });

    it('überspringt eine gelöschte Karte samt Abfrage und zählt sie einmal ab', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 3);
        [$a] = $deck->cards()->orderBy('created_at')->get()->all();
        $this->post('/learn/round');
        expect(session('study_round')['total'])->toBe(3);

        $a->delete();
        $this->get('/learn/round')->assertSee('Wort 2');
        skipIntros();
        expect(session('study_round')['total'])->toBe(2);
        $this->get('/learn/round')->assertSee('1 / 2');
    });

    it('führt eine alte Runde ohne neue Felder weiter', function () {
        $ich = actingAsUser();
        $card = msDeck($ich, 1)->cards()->sole();
        msProgress($ich, $card, 4, 0);
        session()->put('study_round', ['mode' => 'learn', 'deck' => null, 'step' => 0, 'total' => 1, 'answered' => 0, 'known' => 0,
            'first' => 0, 'promoted' => 0, 'repeats' => [],
            'queue' => [['card' => $card->id, 'direction' => 'forward', 'repeat' => false, 'runs' => [DB::table('deck_runs')->value('id')]]]]);

        msNext()->assertSee('Geschafft!')->assertSee('Neu in „Kann ich“:', false);
    });

    it('ignoriert „Weiter“ mit veraltetem Schritt', function () {
        $ich = actingAsUser();
        msDeck($ich, 2);
        $this->post('/learn/round');
        $first = session('study_round')['queue'][0]['card'];

        msNext();
        $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => $first, 'step' => 0])->assertOk();

        expect(session('study_round')['step'])->toBe(1);
    });
});

describe('Tagesziel und Lernserie', function () {
    it('füllt den Ring mit jeder Antwort, auch Wiederholungen und Test üben', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 12);

        $this->get('/learn')->assertSee('aria-label="Tagesziel: 0 von 20 Karten"', false)->assertSee("Los geht's");

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 15) as $i) {
            msNext($i > 3);     // three times "Nochmal", then known
        }
        $this->get('/learn')->assertSee('aria-label="Tagesziel: 15 von 20 Karten"', false)->assertDontSee('Tagesziel geschafft!');

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 5) as $i) {
            msNext();
        }
        $this->get('/learn')->assertSee('Tagesziel geschafft!')->assertSee('goal-ring-done')->assertSee('Weiterlernen');

        expect(DB::table('study_days')->where('user_id', $ich->id)->first(['answers', 'goal_met', 'reviews']))
            ->toEqual((object) ['answers' => 20, 'goal_met' => true, 'reviews' => 0])
            ->and(app(LeitnerService::class)->streak($ich))->toBe(1);
    });

    it('nimmt ein kleineres Tagesziel aus dem Lernplan', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 12);

        $this->post('/learn/settings', ['plan' => 'normal', 'daily_goal' => '10'])->assertRedirect('/learn/settings');
        $this->get('/account')->assertSee('Tagesziel 10 Karten');
        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 10) as $i) {
            msNext();
        }

        $this->get('/learn')->assertSee('Tagesziel geschafft!');
    });

    it('lehnt andere Tagesziele ab und behält das bisherige ohne Angabe', function (mixed $goal) {
        $ich = actingAsUser();
        $this->post('/learn/settings', ['plan' => 'normal', 'daily_goal' => '30']);

        $this->from('/learn/settings')->post('/learn/settings', ['plan' => 'normal', 'daily_goal' => $goal])
            ->assertSessionHasErrors(['daily_goal' => 'Bitte 10, 20 oder 30 wählen.']);
        $this->get('/learn/settings')->assertOk();
        $this->post('/learn/settings', ['plan' => 'quick']);

        expect(app(LeitnerService::class)->dailyGoal($ich))->toBe(30);
    })->with(['15', 'viel', [['20']]]);

    it('zählt den Tag sofort, wenn ein gesenktes Ziel schon erreicht ist', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 20);
        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 12) as $i) {
            msNext();
        }
        expect(app(LeitnerService::class)->streak($ich))->toBe(0);

        $this->post('/learn/settings', ['plan' => 'normal', 'daily_goal' => '10']);

        expect(app(LeitnerService::class)->streak($ich))->toBe(1);
    });

    it('zeigt an einem Tag ohne fällige Karten keinen vollen Ring, bis geübt wurde', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 2);
        foreach ($deck->cards()->get() as $card) {
            msProgress($ich, $card, 2, 3);
        }

        $this->get('/learn')->assertSee('Heute ist nichts dran.')->assertDontSee('goal-ring-done');

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        msNext();
        msNext();

        expect(app(LeitnerService::class)->streak($ich))->toBe(1);
        $this->get('/learn')->assertSee('Für heute alles geschafft.')->assertSee('goal-ring-done');
    });

    it('zählt eine beendete Lernrunde nicht, solange noch Karten warten', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 25);
        foreach ($deck->cards()->get() as $card) {
            msProgress($ich, $card, 1, 0);
        }
        $this->post('/learn/settings', ['plan' => 'normal', 'daily_goal' => '30']);

        $this->post('/learn/round');
        foreach (range(1, 20) as $i) {
            msNext();
        }

        expect(session('study_round')['queue'])->toBe([])
            ->and(DB::table('study_days')->value('goal_met'))->toBeFalse();
    });

    it('meldet „Tagesziel geschafft!“ nur am Ende der Runde, die es erreicht', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 12);
        $this->post('/learn/settings', ['plan' => 'normal', 'daily_goal' => '10']);

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 11) as $i) {
            $last = msNext();
        }
        $last->assertDontSee('Tagesziel geschafft!');
        msNext()->assertSee('Alles gewusst!')->assertSee('Tagesziel geschafft!');

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 11) as $i) {
            msNext();
        }
        msNext()->assertSee('Alles gewusst!')->assertDontSee('Tagesziel geschafft!');
    });

    it('zählt einen Tag mit wenig zu lernen, wenn danach nichts mehr wartet', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 4);
        foreach ($deck->cards()->get() as $card) {
            msProgress($ich, $card, 1, 0);
        }

        $this->post('/learn/round');
        foreach (range(1, 4) as $i) {
            msNext();
        }

        expect(DB::table('study_days')->value('goal_met'))->toBeTrue()
            ->and(app(LeitnerService::class)->streak($ich))->toBe(1);
        $this->get('/learn')->assertSee('Für heute alles geschafft.')->assertSee('goal-ring-done');
    });

    it('zählt einen Tag mit wenigen Antworten nicht, solange noch Karten warten', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 30);

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 5) as $i) {
            msNext();
        }

        expect(DB::table('study_days')->value('goal_met'))->toBeFalse()
            ->and(app(LeitnerService::class)->streak($ich))->toBe(0);
    });

    it('beginnt den Tag in Berliner Zeit', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 30);
        $this->travelTo(Carbon::parse('2026-10-14 21:30:00', 'UTC'));   // 23:30 in Berlin
        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        foreach (range(1, 5) as $i) {
            msNext();
        }

        $this->travelTo(Carbon::parse('2026-10-14 22:30:00', 'UTC'));   // 00:30 in Berlin
        $this->get('/learn')->assertSee('aria-label="Tagesziel: 0 von 20 Karten"', false);
    });
});

describe('Wortschatz', function () {
    it('zählt Wörter in „Kann ich“ einmal, nur aktive und gelernte', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 4);
        [$a, $b, $c, $d] = $deck->cards()->orderBy('created_at')->get()->all();
        msProgress($ich, $a, 5, null, now()->subDays(20));
        msProgress($ich, $a, 5, null, now()->subDays(20), Direction::Backward);   // same word, other direction
        msProgress($ich, $b, 5, null, now()->subDays(2));
        msProgress($ich, $c, 2, 1);
        // Backward counts once the direction is learned; decks not learned never count.
        $this->post("/decks/{$deck->id}/reverse", ['on' => '1']);
        $fremd = msDeck(User::factory()->create(), 1, ['is_shared' => true]);
        msProgress($ich, $fremd->cards()->sole(), 5, null);

        $this->get('/learn')->assertSee('Du kannst schon <strong>2 Wörter</strong>', false)->assertSee('+1 diese Woche');

        $this->post("/decks/{$deck->id}/selection", ['cards' => [$a->id, $c->id, $d->id]]);
        $this->get('/learn')->assertSee('Du kannst schon <strong>1 Wort</strong>', false)->assertDontSee('diese Woche');
    });

    it('zeigt vor dem ersten „Kann ich“ die Wörter im Karteikasten', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 3);
        msProgress($ich, $deck->cards()->first(), 2, 1);

        $this->get('/learn')->assertSee('<strong class="text-content">1 Wort</strong> im Karteikasten', false)->assertDontSee('Du kannst schon');
    });

    it('nennt am Rundenende die Wörter, die neu in „Kann ich“ sind', function () {
        $ich = actingAsUser();
        $deck = msDeck($ich, 10);
        foreach ($deck->cards()->orderBy('created_at')->get() as $card) {
            msProgress($ich, $card, 4, 0);   // Normal: box 4 of 5, one step to "Kann ich"
        }

        $this->post('/learn/round');
        foreach (range(1, 9) as $i) {
            msNext();
        }
        msNext()->assertSee('Neu in „Kann ich“:', false)
            ->assertSee('word 1, word 2, word 3, word 4, word 5, word 6, word 7, word 8 und 2 weitere');
    });
});

it('lässt bestehende Lernserien durch die Migration bestehen', function () {
    $migration = require database_path('migrations/2026_10_16_000000_add_daily_goal_and_goal_met.php');
    $migration->down();

    $ich = User::factory()->create();
    foreach (['2026-10-13', '2026-10-12'] as $day) {
        DB::table('study_days')->insert(['user_id' => $ich->id, 'day' => $day, 'reviews' => 3, 'correct' => 2]);
    }

    $migration->up();

    expect(app(LeitnerService::class)->streak($ich))->toBe(2)
        ->and(DB::table('study_days')->pluck('answers')->all())->toBe([3, 3]);
});
