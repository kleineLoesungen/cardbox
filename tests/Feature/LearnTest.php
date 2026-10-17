<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use App\Services\DeckRunService;
use App\Services\LeitnerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Progress row helper: box and days until due (relative to today in Berlin).
 * Without a run it lands in the person's standard run of the card's deck,
 * started on the spot with their current standard plan.
 */
function progress(User $user, Card $card, int $box, ?int $dueInDays = 0, Direction $direction = Direction::Forward, ?DeckRun $run = null): CardProgress
{
    $today = app(LeitnerService::class)->today();
    $run ??= app(DeckRunService::class)->forStandard($user, $card->deck);

    return CardProgress::create([
        'user_id' => $user->id,
        'deck_run_id' => $run->id,
        'card_id' => $card->id,
        'direction' => $direction,
        'box' => $box,
        'due_on' => $dueInDays === null ? null : $today->addDays($dueInDays)->toDateString(),
        'review_count' => 1,
        'correct_count' => 0,
    ]);
}

function progressOf(User $user, Card $card, Direction $direction = Direction::Forward, ?DeckRun $run = null): ?CardProgress
{
    return CardProgress::where('user_id', $user->id)->where('card_id', $card->id)
        ->where('direction', $direction->value)
        ->when($run, fn ($q) => $q->where('deck_run_id', $run->id))
        ->first();
}

/** Starts a round for a deck and returns the card ids in queue order. */
function startRound(Deck $deck): array
{
    test()->post('/learn/round', ['deck' => $deck->id])->assertRedirect('/learn/round');

    return array_column(roundQuestions(), 'card');
}

/** Answers like the form does: card, the step it was shown at, known or not. */
function answer(Card $card, bool $known, ?int $step = null): TestResponse
{
    if ($step === null) {
        skipIntros();
    }

    return test()->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', [
        'card' => $card->id,
        'step' => $step ?? session('study_round')['step'],
        'known' => $known ? '1' : '0',
    ]);
}

beforeEach(function () {
    // 10:00 in Berlin, mid-day: no date line nearby unless a test moves it.
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

describe('Karteikasten', function () {
    it('legt eine neue gewusste Karte in Fach 1, fällig morgen', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();

        startRound($card->deck);
        answer($card, true)->assertOk();

        expect(progressOf($ich, $card))->box->toBe(1)
            ->and(progressOf($ich, $card)->due_on->toDateString())->toBe('2026-10-15');
    });

    it('rückt mit dem Abstand des neuen Fachs weiter und meistert im letzten Fach', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $zwei = Card::factory()->for($deck)->create();
        $vier = Card::factory()->for($deck)->create();
        progress($ich, $zwei, 1);   // → Fach 2: 2 Tage
        progress($ich, $vier, 4);   // → Fach 5 = Kann ich

        startRound($deck);
        answer($zwei, true);
        answer($vier, true);

        expect(progressOf($ich, $zwei)->box)->toBe(2)
            ->and(progressOf($ich, $zwei)->due_on->toDateString())->toBe('2026-10-16')
            ->and(progressOf($ich, $vier)->box)->toBe(5)
            ->and(progressOf($ich, $vier)->due_on)->toBeNull();
    });

    it('legt eine nicht gewusste Karte zurück in Fach 1, fällig morgen', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();
        progress($ich, $card, 3);

        startRound($card->deck);
        answer($card, false);

        expect(progressOf($ich, $card)->box)->toBe(1)
            ->and(progressOf($ich, $card)->due_on->toDateString())->toBe('2026-10-15');
    });

    it('nutzt den Lernplan des Durchgangs, auch wenn sich der Standard danach ändert', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();
        $neu = Card::factory()->for($deck)->create();
        DB::table('learning_settings')->insert(['user_id' => $ich->id, 'box_count' => 3, 'day_gaps' => json_encode([3, 10])]);
        progress($ich, $card, 1);

        // Standard back to Normal: the running deck keeps 3 boxes, 3 · 10 days.
        DB::table('learning_settings')->where('user_id', $ich->id)->update(['box_count' => 5, 'day_gaps' => json_encode([1, 2, 4, 7])]);
        app(LeitnerService::class)->forgetPlan($ich);

        expect(startRound($deck))->toBe([$card->id, $neu->id]);
        answer($card, true);
        answer($neu, true);

        expect(progressOf($ich, $card)->box)->toBe(2)
            ->and(progressOf($ich, $card)->due_on->toDateString())->toBe('2026-10-24')
            ->and(progressOf($ich, $neu)->due_on->toDateString())->toBe('2026-10-17')
            ->and(DeckRun::where('user_id', $ich->id)->sole())->plan->toBe('custom')->box_count->toBe(3);
        $this->get("/decks/{$deck->id}")->assertSee('Kann ich: 0 Karten')->assertSee('Mein Plan');
    });

    it('führt Fortschritt je Richtung getrennt, rückwärts erst nach dem Einschalten', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();

        expect(startRound($card->deck))->toBe([$card->id]);
        $this->post("/decks/{$card->deck_id}/reverse", ['on' => '1'])->assertRedirect();
        expect(startRound($card->deck))->toBe([$card->id, $card->id])
            ->and(array_column(roundQuestions(), 'direction'))->toBe(['forward', 'backward']);

        answer($card, true);
        expect(progressOf($ich, $card, Direction::Forward)->box)->toBe(1)
            ->and(progressOf($ich, $card, Direction::Backward))->toBeNull();
        answer($card, false);
        expect(progressOf($ich, $card, Direction::Backward)->box)->toBe(1);
    });

    it('rechnet „heute“ in der Lern-Zeitzone, nicht in UTC', function () {
        // 23:30 UTC on the 14th is already 01:30 on the 15th in Berlin.
        $this->travelTo(Carbon::parse('2026-10-14 23:30:00', 'UTC'));
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();
        CardProgress::create([
            'user_id' => $ich->id, 'deck_run_id' => app(DeckRunService::class)->forStandard($ich, $card->deck)->id,
            'card_id' => $card->id, 'direction' => Direction::Forward,
            'box' => 1, 'due_on' => '2026-10-15', 'review_count' => 1, 'correct_count' => 1,
        ]);

        expect(startRound($card->deck))->toBe([$card->id]);
        answer($card, true);

        expect(progressOf($ich, $card)->due_on->toDateString())->toBe('2026-10-17');
    });
});

describe('Lernrunde', function () {
    it('nimmt fällige vor neuen Karten, höchstens 20, keine gemeisterten und keine noch nicht fälligen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $neu = Card::factory()->for($deck)->count(25)->create();
        $faellig = Card::factory()->for($deck)->create();
        $spaeter = Card::factory()->for($deck)->create();
        $gemeistert = Card::factory()->for($deck)->create();
        progress($ich, $faellig, 2, -1);
        progress($ich, $spaeter, 2, 3);
        progress($ich, $gemeistert, 5, null);

        $queue = startRound($deck);

        expect($queue)->toHaveCount(20)
            ->and($queue[0])->toBe($faellig->id)
            ->and($queue)->not->toContain($spaeter->id)
            ->and($queue)->not->toContain($gemeistert->id);
    });

    it('stellt nicht gewusste Karten ans Ende, höchstens dreimal, ohne das Fach erneut zu ändern', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();

        startRound($deck);
        answer($card, false);
        answer($card, true)->assertSee('Geschafft');   // the repeat
        expect(progressOf($ich, $card))->box->toBe(1)->review_count->toBe(1);

        startRound($deck);   // new round: card is not due (tomorrow) → empty
        expect(session('study_round')['queue'])->toBe([]);
    });

    it('wiederholt eine Karte je Runde höchstens dreimal', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();

        startRound($card->deck);
        foreach (range(1, 4) as $_) {
            answer($card, false);
        }

        expect(session('study_round')['queue'])->toBe([])
            ->and(progressOf($ich, $card)->review_count)->toBe(1);
    });

    it('setzt nach dem Neuladen an derselben Karte fort und ignoriert Antworten für andere Karten', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $a = Card::factory()->for($deck)->create(['front' => ['Apfel']]);
        $b = Card::factory()->for($deck)->create(['front' => ['Birne']]);

        startRound($deck);
        $this->get('/learn/round')->assertOk()->assertSee('Apfel');
        $this->get('/learn/round')->assertSee('Apfel');

        answer($b, true)->assertSee('Apfel');
        expect(progressOf($ich, $b))->toBeNull();
    });

    it('überspringt Karten, die während der Runde gelöscht oder unsichtbar wurden', function () {
        $ich = actingAsUser();
        $fremd = Deck::factory()->shared()->create();
        $a = Card::factory()->for($fremd)->create(['front' => ['Apfel']]);
        $b = Card::factory()->for($fremd)->create(['front' => ['Birne']]);

        startRound($fremd);
        $a->delete();
        $this->get('/learn/round')->assertSee('Birne');

        $fremd->forceFill(['is_shared' => false])->save();
        $this->get('/learn/round')->assertSee('Gerade ist nichts zu lernen');
    });

    it('folgt einem fremden geteilten Stapel beim ersten Lernen und verweigert private', function () {
        $ich = actingAsUser();
        $geteilt = Deck::factory()->shared()->create();
        Card::factory()->for($geteilt)->create();
        $privat = Deck::factory()->create();

        startRound($geteilt);
        expect($ich->followedDecks()->pluck('decks.id')->all())->toBe([$geteilt->id]);

        $this->post('/learn/round', ['deck' => $privat->id])->assertNotFound();
        $this->post('/learn/round', ['deck' => ['x']])->assertRedirect('/learn/round');
    });

    it('zeigt am Ende, wie viele gewusst und wie viele aufgestiegen sind', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        [$a, $b] = Card::factory()->for($deck)->count(2)->create()->all();

        startRound($deck);
        answer($a, true);
        answer($b, false);
        answer($b, true)
            ->assertSee('1 von 2')
            ->assertSee('1 Karte ist ein Fach weiter');
    });
});

describe('Los geht\'s', function () {
    it('lernt fällige Karten aus eigenen und gefolgten Stapeln, nicht aus fremden oder nicht mehr geteilten', function () {
        $ich = actingAsUser();
        $eigen = Card::factory()->for(Deck::factory()->for($ich))->create();
        $gefolgtDeck = Deck::factory()->shared()->create();
        $gefolgt = Card::factory()->for($gefolgtDeck)->create();
        $ich->followedDecks()->attach($gefolgtDeck);
        $entfolgt = Card::factory()->for(Deck::factory()->shared()->create())->create();
        $neu = Card::factory()->for(Deck::factory()->for($ich))->create();
        foreach ([$eigen, $gefolgt, $entfolgt] as $card) {
            progress($ich, $card, 1, -1);
        }

        $this->post('/learn/round')->assertRedirect('/learn/round');

        // Due ones first, then new ones (docs/specs/einfacher-lernen.md, A).
        $queue = array_column(roundQuestions(), 'card');
        expect(array_slice($queue, 0, 2))->toEqualCanonicalizing([$eigen->id, $gefolgt->id])
            ->and(array_slice($queue, 2))->toBe([$neu->id]);
    });

    it('zeigt auf „Heute“ fällige Karten, morgen fällige und die Lernserie', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create(['name' => 'Tiere']);
        progress($ich, Card::factory()->for($deck)->create(), 1, 0);
        progress($ich, Card::factory()->for($deck)->create(), 2, 1);
        DB::table('study_days')->insert([
            ['user_id' => $ich->id, 'day' => '2026-10-13', 'reviews' => 3, 'correct' => 2, 'goal_met' => true],
            ['user_id' => $ich->id, 'day' => '2026-10-12', 'reviews' => 1, 'correct' => 1, 'goal_met' => true],
        ]);

        $this->get('/learn')->assertOk()
            ->assertSee('1 Karte')
            ->assertSee('Los geht')
            ->assertSee('2 Tage in Folge gelernt')
            ->assertSee('1 fällig');
    });

    it('meldet „alles geschafft“ und was morgen dran ist', function () {
        $ich = actingAsUser();
        progress($ich, Card::factory()->for(Deck::factory()->for($ich))->create(), 2, 1);

        $this->get('/learn')->assertSee('Heute ist nichts dran.')->assertSee('Morgen ist 1 Karte dran.');
    });

    it('leitet von / und /dashboard nach /learn', function () {
        actingAsUser();

        $this->get('/')->assertRedirect('/learn');
        $this->get('/dashboard')->assertRedirect('/learn');
    });
});

describe('Freies Üben', function () {
    it('speichert keinen Fortschritt, zählt aber fürs Tagesziel', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();

        $this->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => 'mixed'])->assertRedirect('/learn/round');
        $this->get('/learn/round')->assertSee('Freies Üben – nur Übung');
        answer($card, true)->assertSee('1 von 1');

        expect(CardProgress::count())->toBe(0)
            ->and(DB::table('study_days')->where('user_id', $ich->id)->first(['reviews', 'answers']))
            ->toEqual((object) ['reviews' => 0, 'answers' => 1]);
    });

    it('verlangt eine gültige Richtung', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->from("/decks/{$deck->id}")->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => 'quer'])
            ->assertSessionHasErrors('direction');
    });
});

describe('Lernserie', function () {
    it('zählt aufeinanderfolgende Tage und bricht nach einem Tag Pause ab', function () {
        $ich = User::factory()->create();
        $leitner = app(LeitnerService::class);
        $tage = fn (array $days) => DB::table('study_days')->insert(array_map(
            fn ($d) => ['user_id' => $ich->id, 'day' => $d, 'reviews' => 1, 'correct' => 1, 'goal_met' => true], $days,
        ));

        expect($leitner->streak($ich))->toBe(0);

        $tage(['2026-10-13', '2026-10-12', '2026-10-10']);
        expect($leitner->streak($ich))->toBe(2);

        $tage(['2026-10-14']);
        expect($leitner->streak($ich))->toBe(3);

        DB::table('study_days')->where('user_id', $ich->id)->whereIn('day', ['2026-10-14', '2026-10-13'])->delete();
        expect($leitner->streak($ich))->toBe(0);
    });

    it('zählt einen Tag, sobald eine Karte bewertet wurde', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();

        startRound($card->deck);
        answer($card, false);

        expect(DB::table('study_days')->where('user_id', $ich->id)->value('reviews'))->toBe(1);
    });
});

describe('Lernstand verwalten', function () {
    it('setzt nur den eigenen Fortschritt dieses Stapels zurück', function () {
        $ich = actingAsUser();
        $andere = User::factory()->create();
        $deck = Deck::factory()->shared()->create();
        $card = Card::factory()->for($deck)->create();
        $sonst = Card::factory()->for(Deck::factory()->for($ich))->create();
        progress($ich, $card, 2);
        progress($ich, $card, 3, 0, Direction::Backward);
        progress($ich, $sonst, 2);
        progress($andere, $card, 2);

        $run = DeckRun::where('user_id', $ich->id)->where('deck_id', $deck->id)->sole();
        $this->post("/decks/{$deck->id}/runs/{$run->id}/reset")->assertRedirect("/decks/{$deck->id}");

        expect(CardProgress::where('user_id', $ich->id)->pluck('card_id')->all())->toBe([$sonst->id])
            ->and(progressOf($andere, $card))->not->toBeNull()
            ->and(DeckRun::find($run->id))->toBeNull();
    });

    it('behält den Lernstand beim Entfolgen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->shared()->create();
        $card = Card::factory()->for($deck)->create();
        $ich->followedDecks()->attach($deck);
        progress($ich, $card, 2);

        $this->post("/decks/{$deck->id}/unfollow");

        expect(progressOf($ich, $card))->not->toBeNull();
    });

    it('nimmt beim Kopieren den eigenen Lernstand mit, nicht den anderer', function () {
        $ich = actingAsUser();
        $andere = User::factory()->create();
        $deck = Deck::factory()->shared()->create();
        $card = Card::factory()->for($deck)->create();
        progress($ich, $card, 3, 2);
        progress($andere, $card, 4, 2);

        $this->post("/decks/{$deck->id}/copy");

        $kopie = Deck::where('user_id', $ich->id)->sole()->cards()->sole();
        expect(progressOf($ich, $kopie))->box->toBe(3)
            ->and(progressOf($andere, $kopie))->toBeNull();
    });

    it('zeigt die Fächer eines Stapels und die Karten in einem Fach', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);
        Card::factory()->for($deck)->create();
        progress($ich, $card, 2, 1);

        $this->get("/decks/{$deck->id}")
            ->assertSee('Neu: 1 Karte')
            ->assertSee('Fach 2: 1 Karte')
            ->assertSee('Lernen');
        $this->get("/decks/{$deck->id}/box/2?direction=forward")->assertSee('Hund')->assertSee('morgen');
        $this->get('/decks/'.Deck::factory()->create()->id.'/box/2')->assertNotFound();
    });
});

describe('Grenzfälle', function () {
    it('antwortet in der Runde und im Fach-Blatt mit Fragmenten, nicht mit ganzen Seiten', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();

        startRound($card->deck);
        answer($card, true)->assertDontSee('<html', false)->assertSee('id="round"', false);
        $this->get("/decks/{$card->deck->id}/box/1")->assertDontSee('<html', false);
    });

    it('verweigert Zurücksetzen und Üben bei fremden privaten Stapeln', function () {
        actingAsUser();
        $privat = Deck::factory()->create();
        Card::factory()->for($privat)->create();

        $run = DeckRun::create(['user_id' => $privat->user_id, 'deck_id' => $privat->id, 'plan' => 'normal', 'box_count' => 5, 'day_gaps' => [1, 2, 4, 7]]);
        $this->post("/decks/{$privat->id}/runs/{$run->id}/reset")->assertNotFound();
        expect(DeckRun::find($run->id))->not->toBeNull();
        $this->post('/learn/round', ['mode' => 'practice', 'deck' => $privat->id, 'direction' => 'forward'])->assertNotFound();
    });

    it('schickt ohne laufende Runde zurück zu „Heute“', function () {
        actingAsUser();

        $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => 'x', 'step' => 0])
            ->assertHeader('HX-Redirect', '/learn');
        $this->get('/learn/round')->assertRedirect('/learn');
    });

    it('nimmt bei „Los geht\'s“ höchstens 20 Karten, älteste Fälligkeit zuerst', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $alt = Card::factory()->for($deck)->create();
        progress($ich, $alt, 2, -5);
        foreach (Card::factory()->for($deck)->count(22)->create() as $card) {
            progress($ich, $card, 1, 0);
        }

        $this->post('/learn/round');
        $queue = session('study_round')['queue'];

        expect($queue)->toHaveCount(20)->and($queue[0]['card'])->toBe($alt->id);
    });

    it('wertet eine veraltete Antwort nicht, auch wenn dieselbe Karte wieder dran ist', function () {
        $ich = actingAsUser();
        $card = Card::factory()->for(Deck::factory()->for($ich))->create();

        startRound($card->deck);
        skipIntros();
        $step = session('study_round')['step'];
        answer($card, false, $step);   // shown at this step → repeat comes next
        answer($card, true, $step);    // late double tap from the same step: ignored
        expect(session('study_round')['queue'])->toHaveCount(1)
            ->and(progressOf($ich, $card)->review_count)->toBe(1);
    });

    it('antwortet auf unbekannte oder zu große Fachnummern mit 404', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->get("/decks/{$deck->id}/box/6")->assertNotFound();
        $this->get("/decks/{$deck->id}/box/99999999999999999999")->assertNotFound();
    });

    it('rechnet „morgen“ auch in Zeitzonen westlich von UTC richtig', function () {
        config(['learning.timezone' => 'America/New_York']);
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['Hund']]);
        progress($ich, $card, 1, 1);

        $this->get("/decks/{$deck->id}/box/1")->assertSee('morgen');
    });
});

describe('Freies Üben auf ein Fach', function () {
    /** Deck with one card each: new, box 2, mastered — and box 2 only backward for a fourth card. */
    function deckWithBoxes(User $ich): array
    {
        $deck = Deck::factory()->for($ich)->create();
        $neu = Card::factory()->for($deck)->create();
        $zwei = Card::factory()->for($deck)->create();
        $kann = Card::factory()->for($deck)->create();
        $rueck = Card::factory()->for($deck)->create();
        progress($ich, $zwei, 2, 3);
        progress($ich, $kann, 5, null);
        progress($ich, $rueck, 2, 3, Direction::Backward);

        return [$deck, $neu, $zwei, $kann, $rueck];
    }

    function practiseBox(Deck $deck, string $direction, string $box): array
    {
        test()->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => $direction, 'box' => $box])
            ->assertRedirect('/learn/round');

        return array_column(session('study_round')['queue'], 'card');
    }

    it('übt nur die Karten des gewählten Fachs in dieser Richtung', function () {
        $ich = actingAsUser();
        [$deck, $neu, $zwei, $kann, $rueck] = deckWithBoxes($ich);

        expect(practiseBox($deck, 'forward', '2'))->toBe([$zwei->id])
            ->and(practiseBox($deck, 'forward', '5'))->toBe([$kann->id])
            ->and(practiseBox($deck, 'forward', '0'))->toEqualCanonicalizing([$neu->id, $rueck->id])
            ->and(practiseBox($deck, 'backward', '2'))->toBe([$rueck->id]);
    });

    it('speichert beim Üben eines Fachs nichts', function () {
        $ich = actingAsUser();
        [$deck, , $zwei] = deckWithBoxes($ich);

        practiseBox($deck, 'forward', '2');
        answer($zwei, false);
        answer($zwei, true);

        expect(progressOf($ich, $zwei))->box->toBe(2)->review_count->toBe(1)
            ->and(DB::table('study_days')->value('reviews'))->toBe(0);
    });

    it('lehnt unbekannte Fächer und „gemischt“ mit Fach ab', function (string $direction, string $box) {
        $ich = actingAsUser();
        [$deck] = deckWithBoxes($ich);

        $this->from("/decks/{$deck->id}")
            ->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => $direction, 'box' => $box])
            ->assertSessionHasErrors('box');
    })->with([['forward', '6'], ['forward', 'x'], ['forward', '999'], ['mixed', '2']]);

    it('bietet im Fach-Blatt an, genau diese Karten zu üben', function () {
        $ich = actingAsUser();
        [$deck] = deckWithBoxes($ich);

        $this->get("/decks/{$deck->id}/box/2?direction=forward")
            ->assertSee('Diese Karte üben')
            ->assertSee('name="box" value="2"', false)
            ->assertSee('name="direction" value="forward"', false);
        $this->get("/decks/{$deck->id}/box/1?direction=forward")->assertDontSee('üben');
    });
});
