<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use App\Services\GuardianService;
use App\Services\LearningOverviewService;
use App\Services\LeitnerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Paused decks (issue #15) and cards with "Vorderseite"/"Rückseite"
 * (issue #14), docs/specs/aktive-stapel-und-freie-karten.md.
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

function adDeck(User $owner, string $name, array $words, array $attributes = []): Deck
{
    $deck = Deck::factory()->for($owner)->create($attributes + ['name' => $name, 'front_language' => 'de', 'back_language' => 'en']);
    foreach ($words as $i => [$front, $back]) {
        Card::factory()->for($deck)->create(['front' => [$front], 'back' => [$back], 'created_at' => now()->subMinutes(100 - $i)]);
    }

    return $deck;
}

function adDue(User $user, Card $card, int $box = 1, int $wrong = 0): void
{
    $run = app(DeckRunService::class)->forStandard($user, Deck::findOrFail($card->deck_id));
    CardProgress::create(['user_id' => $user->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
        'box' => $box, 'due_on' => app(LeitnerService::class)->today()->toDateString(), 'review_count' => 1 + $wrong, 'correct_count' => 1]);
}

describe('Stapel pausieren (Issue #15)', function () {
    it('lässt einen pausierten Stapel aus Heute und „Los geht\'s“ weg, auf der Stapelseite bleibt er lernbar', function () {
        $ich = actingAsUser();
        $aktiv = adDeck($ich, 'Englisch Unit 3', [['Hund', 'dog']]);
        $pause = adDeck($ich, 'Englisch Unit 1', [['Katze', 'cat'], ['Maus', 'mouse']]);
        adDue($ich, $aktiv->cards()->sole());
        adDue($ich, $pause->cards()->orderBy('created_at')->first());

        $this->get("/decks/{$pause->id}")->assertSee('Gerade nicht lernen')->assertDontSee('Pausiert: Dieser Stapel');
        $this->post("/decks/{$pause->id}/pause", ['on' => '1'])
            ->assertRedirect("/decks/{$pause->id}")
            ->assertSessionHas('toast.message', 'Pausiert. Der Stapel kommt nicht mehr auf „Heute“.');

        $this->get('/learn')
            ->assertSee('Englisch Unit 3')
            ->assertDontSee('Englisch Unit 1')
            ->assertSee('1 Stapel ist pausiert')
            ->assertSee('1 Karte');

        $this->post('/learn/round');
        expect(array_column(roundQuestions(), 'card'))->toBe([$aktiv->cards()->sole()->id]);

        // Still learnable on its own page, and marked in the list.
        $this->get("/decks/{$pause->id}")->assertSee('Pausiert: Dieser Stapel kommt nicht auf „Heute“.')->assertSee('Nur diesen Stapel lernen');
        $this->post('/learn/round', ['deck' => $pause->id]);
        expect(roundQuestions())->not->toBeEmpty();
        $this->get('/decks')->assertSee('>pausiert</span>', false);

        $this->post("/decks/{$pause->id}/pause", ['on' => '0'])->assertSessionHas('toast.message', 'Der Stapel ist wieder auf „Heute“ dabei.');
        $this->get('/learn')->assertSee('Englisch Unit 1')->assertDontSee('pausiert');
    });

    it('sagt auf Heute, wenn alle Stapel pausiert sind', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Englisch', [['Hund', 'dog']]);
        $this->post("/decks/{$deck->id}/pause", ['on' => '1']);

        $this->get('/learn')->assertSee('Alle deine Stapel sind pausiert')->assertDontSee('Los geht\'s mit deinem ersten Stapel');
    });

    it('wertet einen leeren aktiven Rest als geschafft, auch wenn pausierte Stapel warten', function () {
        $ich = actingAsUser();
        $aktiv = adDeck($ich, 'Aktiv', [['Hund', 'dog']]);
        $pause = adDeck($ich, 'Pause', [['Katze', 'cat']]);
        adDue($ich, $aktiv->cards()->sole());
        adDue($ich, $pause->cards()->sole());
        $this->post("/decks/{$pause->id}/pause", ['on' => '1']);

        $this->post('/learn/round');
        $round = session('study_round');
        $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => $round['queue'][0]['card'], 'step' => $round['step'], 'known' => '1']);
        $this->flushHeaders()->get('/learn/round');

        expect((bool) DB::table('study_days')->where('user_id', $ich->id)->value('goal_met'))->toBeTrue();
    });

    it('zeigt Eltern pausierte Stapel und ihre Wörter nicht', function () {
        $kind = User::factory()->create();
        $aktiv = adDeck($kind, 'Aktiv', [['Hund', 'dog']]);
        $pause = adDeck($kind, 'Pause', [['Katze', 'cat']]);
        adDue($kind, $aktiv->cards()->sole(), 1, 3);
        adDue($kind, $pause->cards()->sole(), 1, 3);
        $this->actingAs($kind)->post("/decks/{$pause->id}/pause", ['on' => '1']);

        $overview = app(GuardianService::class)->overview($kind);

        expect($overview['decks']->pluck('name')->all())->toBe(['Aktiv'])
            ->and(array_column($overview['hard'], 'word'))->toBe(['Hund – dog'])
            ->and($overview['paused'])->toBe(1);

        $mama = User::factory()->create();
        DB::table('guardian_links')->insert(['guardian_id' => $mama->id, 'child_id' => $kind->id, 'created_at' => now()]);
        DB::table('study_days')->insert(['user_id' => $kind->id, 'day' => '2026-10-14', 'reviews' => 2, 'correct' => 1, 'answers' => 1, 'goal_met' => false]);
        $this->actingAs($mama)->get("/children/{$kind->id}")->assertSee('1 Stapel ist gerade pausiert und fehlt hier.')->assertDontSee('Pause');
    });

    it('zeigt zuletzt gelernte Wörter nur aus aktiven Stapeln, zählt den Wortschatz aber über alle', function () {
        $ich = actingAsUser();
        $aktiv = adDeck($ich, 'Aktiv', [['Hund', 'dog']]);
        $pause = adDeck($ich, 'Pause', [['Katze', 'cat']]);
        adDue($ich, $aktiv->cards()->sole(), 5);
        adDue($ich, $pause->cards()->sole(), 5);
        $this->post("/decks/{$pause->id}/pause", ['on' => '1']);

        expect(app(LearningOverviewService::class)->recentlyMastered($ich, 10))->toBe(['Hund – dog']);
        $this->get('/learn')->assertSee('Du kannst schon <strong>2 Wörter</strong>', false);
    });

    it('füllt den Tag nicht, wenn alle Stapel pausiert sind', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Pause', [['Hund', 'dog'], ['Katze', 'cat']]);
        $this->post("/decks/{$deck->id}/pause", ['on' => '1']);

        $this->post('/learn/round', ['deck' => $deck->id]);
        skipIntros();
        $round = session('study_round');
        $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => $round['queue'][0]['card'], 'step' => $round['step'], 'known' => '1']);
        $this->flushHeaders()->get('/learn');

        expect((bool) DB::table('study_days')->where('user_id', $ich->id)->value('goal_met'))->toBeFalse();
    });

    it('macht einen Stapel beim erneuten Folgen wieder aktiv', function () {
        $deck = adDeck(User::factory()->create(), 'Geteilt', [['Hund', 'dog']], ['is_shared' => true]);
        $ich = actingAsUser();
        $this->post("/decks/{$deck->id}/follow");
        $this->post("/decks/{$deck->id}/pause", ['on' => '1']);
        $this->get('/learn')->assertDontSee('Geteilt');

        $this->post("/decks/{$deck->id}/unfollow");
        $this->post("/decks/{$deck->id}/follow");

        $this->get('/learn')->assertSee('Geteilt');
    });

    it('zählt im Eltern-Balken nur ausgewählte Karten', function () {
        $kind = User::factory()->create();
        $deck = adDeck($kind, 'Buch', [['eins', 'one'], ['zwei', 'two'], ['drei', 'three'], ['vier', 'four']]);
        [$a, $b, $c] = $deck->cards()->orderBy('created_at')->get()->all();
        adDue($kind, $a, 5);
        adDue($kind, $b, 2);
        DB::table('deck_selections')->insert(['user_id' => $kind->id, 'deck_id' => $deck->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$a, $b, $c] as $card) {
            DB::table('deck_selection_cards')->insert(['user_id' => $kind->id, 'deck_id' => $deck->id, 'card_id' => $card->id]);
        }

        $progress = app(GuardianService::class)->overview($kind)['progress'][$deck->id];

        expect($progress)->toMatchArray(['mastered' => 1, 'learning' => 1, 'new' => 1, 'total' => 3]);
    });

    it('lässt nur eigene und gelernte Stapel pausieren', function () {
        actingAsUser();
        $fremd = Deck::factory()->shared()->create();
        $privat = Deck::factory()->create();

        $this->post("/decks/{$fremd->id}/pause", ['on' => '1'])->assertNotFound();
        $this->post("/decks/{$privat->id}/pause", ['on' => '1'])->assertNotFound();
        expect(DB::table('deck_preferences')->count())->toBe(0);
    });

    it('pausiert nur für die eigene Person', function () {
        $besitzerin = User::factory()->create();
        $deck = adDeck($besitzerin, 'Geteilt', [['Hund', 'dog']], ['is_shared' => true]);
        $ich = actingAsUser();
        $ich->followedDecks()->attach($deck);

        $this->post("/decks/{$deck->id}/pause", ['on' => '1']);

        $this->get('/learn')->assertDontSee('Geteilt');
        $this->actingAs($besitzerin)->get('/learn')->assertSee('Geteilt');
    });
});

describe('Vorderseite und Rückseite (Issue #14)', function () {
    it('legt einen Stapel mit Vorderseite und Rückseite an, ohne Vorlesen', function () {
        $ich = actingAsUser();

        $this->get('/decks?new=1')->assertSee('<option value="front"', false)->assertSee('Vorderseite');
        $this->post('/decks', ['name' => 'Hauptstädte', 'front_language' => 'front', 'back_language' => 'back']);
        $deck = Deck::sole();
        Card::factory()->for($deck)->create(['front' => ['Frankreich'], 'back' => ['Paris']]);

        $this->get("/decks/{$deck->id}")->assertSee('Vorderseite → Rückseite');
        $this->post('/learn/round', ['deck' => $deck->id]);
        skipIntros();
        $this->get('/learn/round')
            ->assertSee('Vorderseite')
            ->assertSee('Frankreich')
            ->assertSee('data-question-lang=""', false);
    });

    it('nimmt Vorderseite und Rückseite auch aus einer Tabelle (CSV) an', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Begriffe', [], ['front_language' => 'front', 'back_language' => 'back']);

        $this->post("/decks/{$deck->id}/import", ['text' => "ID;front;back\n;Photosynthese;Pflanzen machen Zucker aus Licht\n"])->assertRedirect();

        expect(Card::where('deck_id', $deck->id)->sole()->back)->toBe(['Pflanzen machen Zucker aus Licht']);
    });

    it('prüft eingetippte Antworten tolerant wie bei Sprachen', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Hauptstädte', [['Frankreich', 'Paris']], ['front_language' => 'front', 'back_language' => 'back']);
        adDue($ich, $deck->cards()->sole());
        $this->post("/decks/{$deck->id}/typing", ['on' => '1']);

        $this->post('/learn/round', ['deck' => $deck->id]);
        $round = session('study_round');
        $this->withHeaders(['HX-Request' => 'true'])
            ->post('/learn/round/answer', ['card' => $round['queue'][0]['card'], 'step' => $round['step'], 'typed' => 'paris'])
            ->assertSee('Richtig!');
    });
});
