<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use App\Services\DeckRunService;
use App\Services\LeitnerService;
use App\Services\StudyRoundService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/*
 * Simpler learning (docs/specs/einfacher-lernen.md): "Heute" brings due and
 * new cards, one word "Lernen", the back → front switch, "Für den Test üben".
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** A deck with `$count` cards in a fixed order. */
function elDeck(User $owner, int $count, array $attributes = []): Deck
{
    $deck = Deck::factory()->for($owner)->create(['front_language' => 'de', 'back_language' => 'en'] + $attributes);
    foreach (range(1, $count) as $i) {
        Card::factory()->for($deck)->create(['front' => ["{$deck->name} {$i}"], 'back' => ["x {$i}"], 'created_at' => now()->subMinutes(1000 - $i)]);
    }

    return $deck;
}

/** Starts a round and returns its queue as "card|direction". */
function elRound(array $input = []): array
{
    test()->post('/learn/round', $input)->assertRedirect('/learn/round');

    return array_map(fn (array $i) => $i['card'].'|'.$i['direction'], roundQuestions());
}

function elAnswer(bool $known): TestResponse
{
    skipIntros();
    $round = session('study_round');

    return test()->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', [
        'card' => $round['queue'][0]['card'],
        'step' => $round['step'],
        'known' => $known ? '1' : '0',
    ])->assertOk();
}

function elDue(User $user, Card $card, Direction $direction = Direction::Forward): void
{
    $run = app(DeckRunService::class)->forStandard($user, Deck::findOrFail($card->deck_id));
    CardProgress::create(['user_id' => $user->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => $direction,
        'box' => 1, 'due_on' => app(LeitnerService::class)->today()->toDateString(), 'review_count' => 1, 'correct_count' => 1]);
}

describe('Heute', function () {
    it('bringt neue Karten eines frisch gelernten Stapels und zählt sie', function () {
        $ich = actingAsUser();
        $deck = elDeck(User::factory()->create(), 3, ['name' => 'Tiere', 'is_shared' => true]);

        $this->get('/learn')->assertSee('Stapel finden');
        $this->post("/decks/{$deck->id}/follow")->assertSessionHas('toast.message', 'Du lernst diesen Stapel jetzt. Die Karten kommen unter Heute dran.');

        $this->get('/learn')->assertSee('3 Karten')->assertSee('davon 3 neu')->assertSee('Los geht');
        expect(elRound())->toHaveCount(3);

        elAnswer(true);
        expect(DeckRun::where('user_id', $ich->id)->sole()->plan)->toBe('normal')
            ->and(CardProgress::where('user_id', $ich->id)->count())->toBe(1);
    });

    it('nimmt höchstens 20 neue am Tag, mehr auf Wunsch, am nächsten Tag wieder', function () {
        $ich = actingAsUser();
        elDeck($ich, 45, ['name' => 'Viel']);

        expect(elRound())->toHaveCount(20);
        foreach (range(1, 20) as $i) {
            elAnswer(true);
        }

        $this->get('/learn')->assertSee('Tagesziel geschafft!')->assertSee('Noch 20 neue Karten');
        expect(elRound())->toBe([]);
        expect(elRound(['more' => '1']))->toHaveCount(20);
        foreach (range(1, 20) as $i) {
            elAnswer(true);
        }
        $this->get('/learn')->assertSee('Noch 5 neue Karten');

        // Next day: 20 due (box 1, one day) fill the round; new ones are counted again.
        $this->travelTo(Carbon::parse('2026-10-15 08:00:00', 'UTC'));
        $this->get('/learn')->assertSee('45 Karten')->assertSee('davon 5 neu');
    });

    it('nimmt erst fällige, dann neue Karten, höchstens 20 in einer Runde', function () {
        $ich = actingAsUser();
        $deck = elDeck($ich, 25, ['name' => 'A']);
        $cards = $deck->cards()->orderBy('created_at')->get();
        elDue($ich, $cards[24]);

        $queue = elRound();
        expect($queue)->toHaveCount(20)
            ->and($queue[0])->toBe($cards[24]->id.'|forward')
            ->and($queue[1])->toBe($cards[0]->id.'|forward');
    });

    it('wechselt bei neuen Karten zwischen den Stapeln', function () {
        $ich = actingAsUser();
        $a = elDeck($ich, 15, ['name' => 'A']);
        $b = elDeck($ich, 15, ['name' => 'B']);

        $decks = collect(elRound())->map(fn (string $item) => Card::find(explode('|', $item)[0])->deck_id)->countBy();

        expect($decks->get($a->id))->toBe(10)->and($decks->get($b->id))->toBe(10);
    });

    it('nimmt pausierte Karten nie als neu', function () {
        $ich = actingAsUser();
        $deck = elDeck($ich, 3, ['name' => 'A']);
        $cards = $deck->cards()->orderBy('created_at')->get();
        $this->post("/decks/{$deck->id}/selection", ['cards' => [$cards[1]->id]]);

        expect(elRound())->toBe([$cards[1]->id.'|forward']);
    });

    it('fragt rückwärts erst nach dem Einschalten, fällige Rückwärts-Karten bleiben solange liegen', function () {
        $ich = actingAsUser();
        $deck = elDeck($ich, 1, ['name' => 'A']);
        $card = $deck->cards()->sole();
        elDue($ich, $card, Direction::Backward);

        $this->get("/decks/{$deck->id}")->assertDontSee('aria-label="Richtung der Fächer"', false)->assertSee('Auch rückwärts lernen');
        expect(elRound())->toBe([$card->id.'|forward']);

        $this->post("/decks/{$deck->id}/reverse", ['on' => '1'])->assertSessionHas('toast.message', 'Du lernst diesen Stapel jetzt auch rückwärts.');
        expect(elRound())->toBe([$card->id.'|backward', $card->id.'|forward']);
        $this->get("/decks/{$deck->id}")->assertSee('aria-label="Richtung der Fächer"', false);

        $this->post("/decks/{$deck->id}/reverse", ['on' => '0']);
        expect(elRound())->toBe([$card->id.'|forward'])
            ->and(CardProgress::where('direction', 'backward')->count())->toBe(1);
    });
});

describe('Stapelseite', function () {
    it('bietet bei fremden geteilten Stapeln „Lernen“, Kopieren nur im Menü', function () {
        $deck = elDeck(User::factory()->create(['name' => 'Frau Weber']), 2, ['name' => 'Unit 3', 'is_shared' => true]);
        actingAsUser();

        $this->get("/decks/{$deck->id}")->assertOk()
            ->assertSee('>Lernen</button>', false)
            ->assertSee('Die Karten kommen ab jetzt unter Heute dran.')
            ->assertSee('Eigene Fassung anlegen')
            ->assertDontSee('Folgen')
            ->assertDontSee('Nur diesen Stapel lernen')
            ->assertSee('Für den Test üben');

        $this->post("/decks/{$deck->id}/follow");
        $this->get("/decks/{$deck->id}")
            ->assertSee('lernst du')
            ->assertSee('Kommt jeden Tag unter')
            ->assertSee('Nur diesen Stapel lernen')
            ->assertSee('Nicht mehr lernen')
            ->assertDontSee('Mit anderem Lernplan starten');
        $this->get('/decks?tab=discover')->assertSee('lernst du');
    });

    it('lernt mit „Nur diesen Stapel lernen“ auch über das Tageskontingent hinaus', function () {
        $ich = actingAsUser();
        $deck = elDeck($ich, 25, ['name' => 'A']);
        elRound();
        foreach (range(1, 20) as $i) {
            elAnswer(true);
        }

        expect(elRound())->toBe([])
            ->and(elRound(['deck' => $deck->id]))->toHaveCount(5);
    });

    it('schaltet rückwärts nur für eigene und gelernte Stapel', function () {
        $ich = actingAsUser();
        $privat = elDeck(User::factory()->create(), 1, ['name' => 'P']);
        $geteilt = elDeck(User::factory()->create(), 1, ['name' => 'G', 'is_shared' => true]);

        $this->post("/decks/{$privat->id}/reverse", ['on' => '1'])->assertNotFound();
        $this->post("/decks/{$geteilt->id}/reverse", ['on' => '1'])->assertNotFound();
        $this->post('/decks/abc/reverse', ['on' => '1'])->assertNotFound();
        expect(DB::table('deck_preferences')->count())->toBe(0);

        $ich->followedDecks()->attach($geteilt);
        $this->post("/decks/{$geteilt->id}/reverse", ['on' => '1'])->assertRedirect("/decks/{$geteilt->id}");
        expect(DB::table('deck_preferences')->where('user_id', $ich->id)->value('with_reverse'))->toBeTrue();
    });
});

describe('Für den Test üben', function () {
    it('fragt alle aktiven Karten, bis jede gewusst ist, und speichert nichts', function () {
        $ich = actingAsUser();
        $deck = elDeck($ich, 3, ['name' => 'A']);
        $cards = $deck->cards()->orderBy('created_at')->get();
        $this->post("/decks/{$deck->id}/selection", ['cards' => [$cards[0]->id, $cards[1]->id]]);

        expect(elRound(['mode' => 'test', 'deck' => $deck->id]))->toEqualCanonicalizing([$cards[0]->id.'|forward', $cards[1]->id.'|forward']);
        $this->get('/learn/round')->assertSee('Test üben – nur Übung')->assertSee('0 / 2');

        // Not known more often than the three repeats of a normal round.
        foreach (range(1, 5) as $i) {
            elAnswer(false);
        }
        expect(session('study_round')['queue'])->toHaveCount(2);
        elAnswer(true);
        elAnswer(true)->assertSee('Alles gewusst!')->assertSee('<strong>0 von 2</strong> gleich beim ersten Mal.', false)->assertSee('Nochmal üben');

        expect(CardProgress::count())->toBe(0)
            ->and(DB::table('study_days')->value('reviews'))->toBe(0)
            ->and(DeckRun::count())->toBe(0);
    });

    it('fragt mit Rückwärts beide Richtungen und lässt fremde private Stapel nicht zu', function () {
        $ich = actingAsUser();
        $deck = elDeck($ich, 2, ['name' => 'A']);
        $this->post("/decks/{$deck->id}/reverse", ['on' => '1']);

        expect(elRound(['mode' => 'test', 'deck' => $deck->id]))->toHaveCount(4);

        $privat = elDeck(User::factory()->create(), 1, ['name' => 'P']);
        $this->post('/learn/round', ['mode' => 'test', 'deck' => $privat->id])->assertNotFound();
    });
});

describe('Grenzfälle', function () {
    it('beginnt das Tageskontingent um Mitternacht in Berlin, nicht in UTC', function () {
        $ich = actingAsUser();
        elDeck($ich, 45, ['name' => 'Viel']);

        // 21:30 UTC = 23:30 in Berlin, still the 14th.
        $this->travelTo(Carbon::parse('2026-10-14 21:30:00', 'UTC'));
        elRound();
        foreach (range(1, 20) as $i) {
            elAnswer(true);
        }
        $this->get('/learn')->assertSee('Noch 20 neue Karten');

        // 22:30 UTC = 00:30 in Berlin: a new day, new cards again (due ones only tomorrow evening).
        $this->travelTo(Carbon::parse('2026-10-14 22:30:00', 'UTC'));
        $this->get('/learn')->assertSee('davon 20 neu');
    });

    it('lässt einen geteilten Stapel für den Test üben, ohne ihn zu lernen', function () {
        $ich = actingAsUser();
        $deck = elDeck(User::factory()->create(), 3, ['name' => 'G', 'is_shared' => true]);

        expect(elRound(['mode' => 'test', 'deck' => $deck->id]))->toHaveCount(3)
            ->and($ich->followedDecks()->count())->toBe(0)
            ->and(DeckRun::count())->toBe(0);
    });

    it('nimmt beim Kopieren „Auch rückwärts“ mit und verbraucht keine neuen Karten von heute', function () {
        $ich = actingAsUser();
        $original = elDeck(User::factory()->create(), 25, ['name' => 'G', 'is_shared' => true]);
        $ich->followedDecks()->attach($original);
        $this->post("/decks/{$original->id}/reverse", ['on' => '1']);
        $this->travelTo(Carbon::parse('2026-10-13 08:00:00', 'UTC'));
        elRound(['deck' => $original->id]);
        foreach (range(1, 20) as $i) {
            elAnswer(false);
        }
        $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));

        $this->post("/decks/{$original->id}/copy")->assertRedirect();
        $copy = Deck::where('user_id', $ich->id)->sole();

        expect(DB::table('deck_preferences')->where('deck_id', $copy->id)->value('with_reverse'))->toBeTrue()
            ->and(app(StudyRoundService::class)->newAllowedToday($ich))->toBe(20);
    });

    it('zählt bestehende zusätzliche Durchgänge auf „Heute“ weiter mit', function () {
        $ich = actingAsUser();
        $deck = elDeck($ich, 1, ['name' => 'A']);
        $card = $deck->cards()->sole();
        $run = DeckRun::create(['user_id' => $ich->id, 'deck_id' => $deck->id, 'plan' => 'quick', 'box_count' => 4, 'day_gaps' => [1, 1, 2]]);
        CardProgress::create(['user_id' => $ich->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
            'box' => 2, 'due_on' => '2026-10-14', 'review_count' => 1, 'correct_count' => 1]);

        expect(elRound())->toBe([$card->id.'|forward']);
    });
});

describe('Konto und Menü', function () {
    it('zeigt den Lernplan im Konto statt im Menü', function () {
        actingAsUser();

        $this->get('/account')->assertSee('Lernplan')->assertSee('Ändern');
        $this->get('/learn/settings')->assertSee('← Mein Konto');
        $this->get('/learn')->assertDontSee('>Lernplan</a>', false);
    });
});
