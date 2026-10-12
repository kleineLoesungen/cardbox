<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use App\Services\LeitnerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
 * Deck runs ("Durchgänge"): a deck learned with one plan, several at once.
 * docs/specs/lernplaene-und-karten.md, A1–A7.
 */

/** A run of the person on the deck, as the start sheet would create it. */
function run(User $user, Deck $deck, string $plan): DeckRun
{
    test()->actingAs($user)->post("/decks/{$deck->id}/runs", ['plan' => $plan])->assertRedirect();

    return DeckRun::where('user_id', $user->id)->where('deck_id', $deck->id)->latest('created_at')->latest('id')->firstOrFail();
}

/** A progress row in a given run: box and days until due. */
function progressIn(DeckRun $run, Card $card, int $box, ?int $dueInDays = 0, Direction $direction = Direction::Forward): CardProgress
{
    return CardProgress::create([
        'user_id' => $run->user_id,
        'deck_run_id' => $run->id,
        'card_id' => $card->id,
        'direction' => $direction,
        'box' => $box,
        'due_on' => $dueInDays === null ? null : app(LeitnerService::class)->today()->addDays($dueInDays)->toDateString(),
        'review_count' => 1,
        'correct_count' => 0,
    ]);
}

function answerCard(Card $card, bool $known): void
{
    test()->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', [
        'card' => $card->id,
        'step' => session('study_round')['step'],
        'known' => $known ? '1' : '0',
    ])->assertOk();
}

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

describe('Starten', function () {
    it('startet beim ersten Lernen ohne Auswahl genau einen Durchgang mit dem Standard', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create();

        $this->post('/learn/round', ['deck' => $deck->id, 'direction' => 'forward']);
        $this->post('/learn/round', ['deck' => $deck->id, 'direction' => 'backward']);

        expect(DeckRun::where('user_id', $ich->id)->sole())
            ->plan->toBe('normal')->box_count->toBe(5)->day_gaps->toBe([1, 2, 4, 7]);
    });

    it('nimmt einen eigenen Standard als „Mein Plan“', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create();
        DB::table('learning_settings')->insert(['user_id' => $ich->id, 'box_count' => 3, 'day_gaps' => json_encode([2, 9])]);

        $this->post('/learn/round', ['deck' => $deck->id]);

        expect(DeckRun::where('user_id', $ich->id)->sole())
            ->plan->toBe('custom')->box_count->toBe(3)->day_gaps->toBe([2, 9])
            ->label()->toBe('Mein Plan');
    });

    it('startet mit einer Vorgabe und zeigt den neuen Durchgang', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create();

        $response = $this->post("/decks/{$deck->id}/runs", ['plan' => 'quick']);

        $run = DeckRun::where('user_id', $ich->id)->sole();
        $response->assertRedirect("/decks/{$deck->id}?run={$run->id}")
            ->assertSessionHas('toast.message', 'Gestartet mit Lernplan Schnell.');
        expect($run)->plan->toBe('quick')->box_count->toBe(4)->day_gaps->toBe([1, 1, 2]);
    });

    it('startet einen Stapel mit jedem Plan nur einmal, mit verschiedenen Plänen mehrmals', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        run($ich, $deck, 'quick');
        $this->from("/decks/{$deck->id}")->post("/decks/{$deck->id}/runs", ['plan' => 'quick'])
            ->assertSessionHasErrors(['plan' => 'Dieser Stapel läuft schon mit diesem Plan.']);
        run($ich, $deck, 'thorough');
        // "standard" is Normal here: a third, different run.
        run($ich, $deck, 'standard');

        expect(DeckRun::where('user_id', $ich->id)->orderBy('created_at')->orderBy('id')->pluck('plan')->sort()->values()->all())
            ->toBe(['normal', 'quick', 'thorough']);
    });

    it('lehnt unbekannte Pläne ab', function (mixed $plan) {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->from("/decks/{$deck->id}")->post("/decks/{$deck->id}/runs", ['plan' => $plan])->assertSessionHasErrors('plan');
        expect(DeckRun::count())->toBe(0);
    })->with(['custom', 'schnell', '', null, [['quick']]]);

    it('folgt einem fremden geteilten Stapel beim Starten und verweigert private', function () {
        $ich = actingAsUser();
        $geteilt = Deck::factory()->shared()->create();
        $privat = Deck::factory()->create();

        run($ich, $geteilt, 'quick');
        $this->post("/decks/{$privat->id}/runs", ['plan' => 'quick'])->assertNotFound();

        expect($ich->followedDecks()->pluck('decks.id')->all())->toBe([$geteilt->id])
            ->and(DeckRun::where('deck_id', $privat->id)->exists())->toBeFalse();
    });

    it('bietet keine Route an, die den Plan eines Durchgangs ändert', function () {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_contains($route->uri(), 'runs'))
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->sort()->values()->all();

        expect($routes)->toBe([
            'POST decks/{deck}/runs',
            'POST decks/{deck}/runs/{run}/reset',
        ]);
    });
});

describe('Lernen mit mehreren Durchgängen', function () {
    it('rückt nur im gewählten Durchgang weiter, nach dessen Plan', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();
        $normal = run($ich, $deck, 'normal');
        $schnell = run($ich, $deck, 'quick');
        progressIn($normal, $card, 2, 0);
        progressIn($schnell, $card, 2, 0);

        $this->post('/learn/round', ['deck' => $deck->id, 'direction' => 'forward', 'run' => $schnell->id]);
        answerCard($card, true);

        // Quick: box 3 → 2 days. Normal untouched: box 2, due today.
        expect(CardProgress::where('deck_run_id', $schnell->id)->sole())
            ->box->toBe(3)->due_on->toDateString()->toBe('2026-10-16')
            ->and(CardProgress::where('deck_run_id', $normal->id)->sole())
            ->box->toBe(2)->due_on->toDateString()->toBe('2026-10-14');
    });

    it('nimmt neue Karten je Durchgang', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();
        $normal = run($ich, $deck, 'normal');
        $schnell = run($ich, $deck, 'quick');
        progressIn($normal, $card, 2, 3);

        $this->post('/learn/round', ['deck' => $deck->id, 'run' => $schnell->id]);

        expect(array_column(session('study_round')['queue'], 'card'))->toBe([$card->id]);
    });

    it('fragt bei „Los geht\'s“ eine in zwei Durchgängen fällige Karte nur einmal und bewegt beide', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create();
        $normal = run($ich, $deck, 'normal');
        $schnell = run($ich, $deck, 'quick');
        progressIn($normal, $card, 1, -2);
        progressIn($schnell, $card, 1, 0);

        $this->get('/learn')->assertSee('1 Karte');
        $this->post('/learn/round');
        expect(session('study_round')['queue'])->toHaveCount(1);

        answerCard($card, true);

        // Normal: box 2 → 2 days. Quick: box 2 → 1 day. One study day, one review.
        expect(CardProgress::where('deck_run_id', $normal->id)->sole())->box->toBe(2)->due_on->toDateString()->toBe('2026-10-16')
            ->and(CardProgress::where('deck_run_id', $schnell->id)->sole())->box->toBe(2)->due_on->toDateString()->toBe('2026-10-15')
            ->and(DB::table('study_days')->where('user_id', $ich->id)->value('reviews'))->toBe(1);
    });

    it('zählt fällige Karten auf „Heute“ und am Stapel einmal, „Kann ich“ nach dem besten Durchgang', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create(['name' => 'Tiere']);
        [$a, $b] = Card::factory()->for($deck)->count(2)->create()->all();
        $normal = run($ich, $deck, 'normal');
        $schnell = run($ich, $deck, 'quick');
        progressIn($normal, $a, 1, 0);
        progressIn($schnell, $a, 1, 0);
        progressIn($schnell, $b, 4, null);   // mastered in Quick (4 boxes)

        $this->get('/learn')
            ->assertSee('1 fällig')
            ->assertSee('50 %', false);
    });

    it('überspringt Karten eines Durchgangs, der während der Runde zurückgesetzt wurde', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['Apfel']]);
        $run = run($ich, $deck, 'quick');

        $this->post('/learn/round', ['deck' => $deck->id, 'run' => $run->id]);
        $this->post("/decks/{$deck->id}/runs/{$run->id}/reset");

        $this->get('/learn/round')->assertDontSee('Apfel');
        expect(CardProgress::count())->toBe(0);
    });
});

describe('Stapelansicht', function () {
    it('zeigt bei einem Durchgang den Plan, bei mehreren die Umschaltleiste', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create();

        $this->get("/decks/{$deck->id}")->assertSee('Lernplan:')->assertSee('Normal')
            ->assertDontSee('aria-label="Lernplan"', false);

        run($ich, $deck, 'normal');
        $schnell = run($ich, $deck, 'quick');

        $this->get("/decks/{$deck->id}?run={$schnell->id}")
            ->assertSee('aria-label="Lernplan"', false)
            ->assertSee('name="run" value="'.$schnell->id.'"', false)
            ->assertSee('/runs/'.$schnell->id.'/reset', false)
            ->assertSee('läuft schon');
    });

    it('nimmt bei unbekanntem ?run den ältesten Durchgang statt eines Fehlers', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->create();
        $normal = run($ich, $deck, 'normal');
        $fremd = run(User::factory()->create(), Deck::factory()->shared()->create(), 'quick');

        $this->actingAs($ich)->get("/decks/{$deck->id}?run={$fremd->id}")->assertOk()
            ->assertSee('name="run" value="'.$normal->id.'"', false);
        $this->get("/decks/{$deck->id}?run=abc")->assertOk();
    });

    it('zeigt die Karten im Fach des gewählten Durchgangs', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['Hund']]);
        $normal = run($ich, $deck, 'normal');
        $schnell = run($ich, $deck, 'quick');
        progressIn($schnell, $card, 2, 1);

        $this->get("/decks/{$deck->id}/box/2?direction=forward&run={$schnell->id}")->assertSee('Hund')
            ->assertSee('name="run" value="'.$schnell->id.'"', false);
        $this->get("/decks/{$deck->id}/box/2?direction=forward&run={$normal->id}")->assertDontSee('Hund');
        // Quick has 4 boxes: box 5 does not exist there.
        $this->get("/decks/{$deck->id}/box/5?run={$schnell->id}")->assertNotFound();
    });
});

describe('Zurücksetzen und Zugriff', function () {
    it('löscht nur den gewählten Durchgang mit seinem Lernstand', function () {
        $ich = actingAsUser();
        $andere = User::factory()->create();
        $deck = Deck::factory()->shared()->create();
        $card = Card::factory()->for($deck)->create();
        $normal = run($ich, $deck, 'normal');
        $schnell = run($ich, $deck, 'quick');
        $fremd = run($andere, $deck, 'quick');
        progressIn($normal, $card, 2);
        progressIn($schnell, $card, 2);
        progressIn($fremd, $card, 2);

        $this->actingAs($ich)->post("/decks/{$deck->id}/runs/{$schnell->id}/reset")
            ->assertRedirect("/decks/{$deck->id}")
            ->assertSessionHas('toast.message', 'Fortschritt mit Lernplan Schnell gelöscht.');

        expect(DeckRun::whereKey([$normal->id, $fremd->id])->count())->toBe(2)
            ->and(DeckRun::find($schnell->id))->toBeNull()
            ->and(CardProgress::count())->toBe(2);
    });

    it('behandelt fremde Durchgänge wie nicht vorhandene', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->shared()->create();
        $card = Card::factory()->for($deck)->create();
        $fremd = run(User::factory()->create(), $deck, 'quick');
        $andererStapel = run($ich, Deck::factory()->for($ich)->create(), 'quick');
        $this->actingAs($ich);

        $this->post("/decks/{$deck->id}/runs/{$fremd->id}/reset")->assertNotFound();
        $this->post("/decks/{$deck->id}/runs/{$andererStapel->id}/reset")->assertNotFound();
        $this->post("/decks/{$deck->id}/runs/abc/reset")->assertNotFound();
        $this->get("/decks/{$deck->id}/box/1?run={$fremd->id}")->assertNotFound();
        $this->get("/decks/{$deck->id}/box/1?run=abc")->assertNotFound();
        $this->post('/learn/round', ['deck' => $deck->id, 'run' => $fremd->id])->assertNotFound();
        $this->post('/learn/round', ['mode' => 'practice', 'deck' => $deck->id, 'direction' => 'forward', 'box' => '1', 'run' => $fremd->id])->assertNotFound();

        expect(DeckRun::find($fremd->id))->not->toBeNull()
            ->and(CardProgress::where('card_id', $card->id)->count())->toBe(0);
    });

    it('nimmt beim Kopieren alle eigenen Durchgänge mit, keine fremden', function () {
        $ich = actingAsUser();
        $andere = User::factory()->create();
        $deck = Deck::factory()->shared()->create();
        $card = Card::factory()->for($deck)->create();
        progressIn(run($ich, $deck, 'normal'), $card, 2, 1);
        progressIn(run($ich, $deck, 'quick'), $card, 3, 1);
        progressIn(run($andere, $deck, 'thorough'), $card, 4, 1);

        $this->actingAs($ich)->post("/decks/{$deck->id}/copy");

        $kopie = Deck::where('user_id', $ich->id)->sole();
        $runs = DeckRun::where('deck_id', $kopie->id)->get()->keyBy('plan');
        expect($runs->keys()->sort()->values()->all())->toBe(['normal', 'quick'])
            ->and($runs->every(fn (DeckRun $r) => $r->user_id === $ich->id))->toBeTrue()
            ->and(CardProgress::where('deck_run_id', $runs['quick']->id)->sole())
            ->box->toBe(3)->card_id->toBe($kopie->cards()->sole()->id);
    });
});

/*
 * The migration moves existing progress into one run per (person, deck):
 * down() restores the old shape, then up() must rebuild the runs.
 */
it('überführt vorhandenen Lernstand in je einen Durchgang mit dem damaligen Standard', function () {
    $migration = require database_path('migrations/2026_10_13_000000_create_deck_runs_table.php');
    $migration->down();

    $anna = User::factory()->create();
    $ben = User::factory()->create();
    $tiere = Deck::factory()->for($anna)->create();
    $farben = Deck::factory()->for($anna)->create();
    $hund = Card::factory()->for($tiere)->create();
    $katze = Card::factory()->for($tiere)->create();
    $rot = Card::factory()->for($farben)->create();
    DB::table('learning_settings')->insert(['user_id' => $ben->id, 'box_count' => 4, 'day_gaps' => json_encode([1, 1, 2])]);

    $row = fn (User $u, Card $c, string $direction, int $box) => DB::table('card_progress')->insert([
        'id' => (string) Str::uuid(), 'user_id' => $u->id, 'card_id' => $c->id, 'direction' => $direction,
        'box' => $box, 'due_on' => '2026-10-20', 'review_count' => 1, 'correct_count' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $row($anna, $hund, 'forward', 2);
    $row($anna, $hund, 'backward', 3);
    $row($anna, $katze, 'forward', 1);
    $row($anna, $rot, 'forward', 4);
    $row($ben, $hund, 'forward', 2);

    $migration->up();

    $runs = DeckRun::all();
    expect($runs)->toHaveCount(3)
        ->and($runs->where('user_id', $anna->id)->pluck('plan')->unique()->all())->toBe(['normal'])
        ->and($runs->firstWhere('user_id', $ben->id))->plan->toBe('quick')->box_count->toBe(4)
        ->and(DB::table('card_progress')->whereNull('deck_run_id')->count())->toBe(0)
        ->and(DB::table('card_progress as p')->join('cards as c', 'c.id', '=', 'p.card_id')
            ->join('deck_runs as r', 'r.id', '=', 'p.deck_run_id')
            ->where(fn ($q) => $q->whereColumn('r.deck_id', '!=', 'c.deck_id')->orWhereColumn('r.user_id', '!=', 'p.user_id'))
            ->count())->toBe(0)
        ->and(DB::table('card_progress')->where('box', 3)->value('due_on'))->toBe('2026-10-20');
});
