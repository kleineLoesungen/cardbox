<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckPreferenceService;
use App\Services\DeckRunService;
use App\Services\DeckService;
use App\Services\GuardianService;
use App\Services\LearningOverviewService;
use App\Services\LeitnerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * "Lerne ich", "pausiert" and decks without either (issue #15), and cards
 * with "Vorderseite"/"Rückseite" (issue #14),
 * docs/specs/aktive-stapel-und-freie-karten.md.
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

/** "Lernen" (on) or "Pausieren" (off) on the deck page. */
function adLearning(Deck $deck, bool $on): void
{
    test()->post("/decks/{$deck->id}/learning", ['on' => $on ? '1' : '0'])->assertRedirect("/decks/{$deck->id}");
}

describe('Lerne ich, pausiert, ohne Kennzeichen (Issue #15)', function () {
    it('pausiert direkt auf der Stapelseite: weg von Heute und „Los geht\'s“, Üben bleibt', function () {
        $ich = actingAsUser();
        $aktiv = adDeck($ich, 'Englisch Unit 3', [['Hund', 'dog']]);
        $pause = adDeck($ich, 'Englisch Unit 1', [['Katze', 'cat'], ['Maus', 'mouse']]);
        adDue($ich, $aktiv->cards()->sole());
        adDue($ich, $pause->cards()->orderBy('created_at')->first());

        $this->get("/decks/{$pause->id}")->assertSee('>lerne ich</span>', false)->assertSee('Pausieren')->assertDontSee('Gerade nicht lernen');
        $this->post("/decks/{$pause->id}/learning", ['on' => '0'])
            ->assertSessionHas('toast.message', 'Pausiert. Der Stapel kommt nicht mehr auf „Heute“.');

        $this->get('/learn')->assertSee('Englisch Unit 3')->assertDontSee('Englisch Unit 1')->assertSee('1 Karte');
        $this->post('/learn/round');
        expect(array_column(roundQuestions(), 'card'))->toBe([$aktiv->cards()->sole()->id]);

        // Paused: badge and "Wieder lernen"; learning with the boxes is gone, practising stays.
        $this->get("/decks/{$pause->id}")
            ->assertSee('>pausiert</span>', false)->assertSee('Wieder lernen')
            ->assertDontSee('Nur diesen Stapel lernen')->assertSee('Für den Test üben');
        $this->post('/learn/round', ['mode' => 'test', 'deck' => $pause->id]);
        expect(roundQuestions())->not->toBeEmpty();
        $this->get("/decks/{$pause->id}")->assertSee('>pausiert</span>', false);

        $this->get('/decks')->assertSeeInOrder(['Lerne ich', 'Englisch Unit 3', 'Pausiert', 'Englisch Unit 1']);

        $this->post("/decks/{$pause->id}/learning", ['on' => '1'])->assertSessionHas('toast.message', 'Lerne ich: Der Stapel ist jetzt auf „Heute“ dabei.');
        $this->get('/learn')->assertSee('Englisch Unit 1');
    });

    it('zeigt eigene Stapel ohne Kennzeichen nicht auf Heute; „Lernen“ holt sie dorthin', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Für die Klasse', [['Hund', 'dog']]);
        DB::table('deck_preferences')->insert(['user_id' => $ich->id, 'deck_id' => $deck->id, 'learning' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/learn')->assertSee('Du lernst gerade keinen Stapel')->assertDontSee('Für die Klasse');
        $this->get('/decks')->assertSeeInOrder(['Weitere Stapel', 'Für die Klasse']);
        $this->get("/decks/{$deck->id}")->assertSee('Du lernst diesen Stapel gerade nicht.')->assertSee('>Lernen</button>', false)->assertDontSee('Nur diesen Stapel lernen');

        adLearning($deck, true);
        $this->get('/learn')->assertSee('Für die Klasse');
    });

    it('macht neue eigene Stapel gleich zu „Lerne ich“', function () {
        actingAsUser();
        $this->post('/decks', ['name' => 'Neu', 'front_language' => 'de', 'back_language' => 'en']);
        Card::factory()->for(Deck::sole())->create();

        $this->get('/learn')->assertSee('Neu');
        $this->get('/decks')->assertSeeInOrder(['Lerne ich', 'Neu']);
    });

    it('lernt einen geteilten Stapel mit „Lernen“, ohne getrenntes Folgen', function () {
        $deck = adDeck(User::factory()->create(), 'Geteilt', [['Hund', 'dog']], ['is_shared' => true]);
        $ich = actingAsUser();
        $this->get("/decks/{$deck->id}")->assertSee('>Lernen</button>', false);

        $this->post("/decks/{$deck->id}/follow");
        $this->get('/learn')->assertSee('Geteilt');
        $this->get('/decks?tab=discover')->assertSee('>lerne ich</span>', false);

        adLearning($deck, false);
        $this->get('/decks?tab=discover')->assertSee('>pausiert</span>', false);

        // "Nicht mehr lernen" and later "Lernen" again: back on "Heute".
        $this->post("/decks/{$deck->id}/unfollow");
        $this->post("/decks/{$deck->id}/follow");
        $this->get('/learn')->assertSee('Geteilt');
    });

    it('macht einen Stapel beim Lernen mit dem Karteikasten zu „Lerne ich“, beim Üben nicht', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Übungsstapel', [['Hund', 'dog']]);
        adLearning($deck, false);

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        $this->get('/learn')->assertDontSee('Übungsstapel');

        $this->post('/learn/round', ['deck' => $deck->id]);
        expect(app(DeckPreferenceService::class)->learningState($ich, $deck))->toBe('active');
    });

    it('wertet einen leeren aktiven Rest als geschafft, auch wenn pausierte Stapel warten', function () {
        $ich = actingAsUser();
        $aktiv = adDeck($ich, 'Aktiv', [['Hund', 'dog']]);
        $pause = adDeck($ich, 'Pause', [['Katze', 'cat']]);
        adDue($ich, $aktiv->cards()->sole());
        adDue($ich, $pause->cards()->sole());
        adLearning($pause, false);

        $this->post('/learn/round');
        $round = session('study_round');
        $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => $round['queue'][0]['card'], 'step' => $round['step'], 'known' => '1']);
        $this->flushHeaders()->get('/learn/round');

        expect((bool) DB::table('study_days')->where('user_id', $ich->id)->value('goal_met'))->toBeTrue();
    });

    it('füllt den Tag nicht, wenn kein Stapel „Lerne ich“ ist', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Pause', [['Hund', 'dog'], ['Katze', 'cat']]);
        adLearning($deck, false);

        $this->post('/learn/round', ['mode' => 'test', 'deck' => $deck->id]);
        $round = session('study_round');
        $this->withHeaders(['HX-Request' => 'true'])->post('/learn/round/answer', ['card' => $round['queue'][0]['card'], 'step' => $round['step'], 'known' => '1']);
        $this->flushHeaders()->get('/learn');

        expect((bool) DB::table('study_days')->where('user_id', $ich->id)->value('goal_met'))->toBeFalse();
    });

    it('zeigt Eltern nur „Lerne ich“-Stapel, ohne Hinweis auf die anderen', function () {
        $kind = User::factory()->create();
        $aktiv = adDeck($kind, 'Aktiv', [['Hund', 'dog']]);
        $pause = adDeck($kind, 'Pause', [['Katze', 'cat']]);
        adDue($kind, $aktiv->cards()->sole(), 1, 3);
        adDue($kind, $pause->cards()->sole(), 1, 3);
        $this->actingAs($kind);
        adLearning($pause, false);

        $overview = app(GuardianService::class)->overview($kind);
        expect($overview['decks']->pluck('name')->all())->toBe(['Aktiv'])
            ->and(array_column($overview['hard'], 'word'))->toBe(['Hund – dog']);

        $mama = User::factory()->create();
        DB::table('guardian_links')->insert(['guardian_id' => $mama->id, 'child_id' => $kind->id, 'created_at' => now()]);
        DB::table('study_days')->insert(['user_id' => $kind->id, 'day' => '2026-10-14', 'reviews' => 2, 'correct' => 1, 'answers' => 1, 'goal_met' => false]);
        $this->actingAs($mama)->get("/children/{$kind->id}")->assertDontSee('Pause')->assertDontSee('pausiert');
    });

    it('zeigt zuletzt gelernte Wörter nur aus „Lerne ich“-Stapeln, zählt den Wortschatz aber über alle', function () {
        $ich = actingAsUser();
        $aktiv = adDeck($ich, 'Aktiv', [['Hund', 'dog']]);
        $pause = adDeck($ich, 'Pause', [['Katze', 'cat']]);
        adDue($ich, $aktiv->cards()->sole(), 5);
        adDue($ich, $pause->cards()->sole(), 5);
        adLearning($pause, false);

        expect(app(LearningOverviewService::class)->recentlyMastered($ich, 10))->toBe(['Hund – dog']);
        $this->get('/learn')->assertSee('Du kannst schon <strong>2 Wörter</strong>', false);
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

    it('schaltet nur eigene und gelernte Stapel um', function () {
        actingAsUser();
        $fremd = Deck::factory()->shared()->create();
        $privat = Deck::factory()->create();

        $this->post("/decks/{$fremd->id}/learning", ['on' => '1'])->assertNotFound();
        $this->post("/decks/{$privat->id}/learning", ['on' => '0'])->assertNotFound();
        expect(DB::table('deck_preferences')->count())->toBe(0);
    });

    it('pausiert nur für die eigene Person', function () {
        $besitzerin = User::factory()->create();
        $deck = adDeck($besitzerin, 'Geteilt', [['Hund', 'dog']], ['is_shared' => true]);
        $ich = actingAsUser();
        $ich->followedDecks()->attach($deck);
        adLearning($deck, false);

        $this->get('/learn')->assertDontSee('Geteilt');
        $this->actingAs($besitzerin)->get('/learn')->assertSee('Geteilt');
    });

    it('zeigt im Wörterbuch „lerne ich“ nur für Stapel auf Heute; „Lernen“ holt pausierte und andere zurück', function () {
        $ich = actingAsUser();
        $pause = adDeck($ich, 'Pausiert', [['Apfel', 'apple']]);
        $ohne = adDeck($ich, 'Ohne', [['Birne', 'pear']]);
        $aktiv = adDeck($ich, 'Aktiv', [['Kirsche', 'cherry']]);
        adLearning($pause, false);
        DB::table('deck_preferences')->insert(['user_id' => $ich->id, 'deck_id' => $ohne->id, 'learning' => false, 'created_at' => now(), 'updated_at' => now()]);

        $htmx = ['HX-Request' => 'true'];
        $this->withHeaders($htmx)->get('/dictionary/search?q=Kirsche')->assertSee('>lerne ich</span>', false);
        foreach (['Apfel' => $pause, 'Birne' => $ohne] as $word => $deck) {
            $this->withHeaders($htmx)->get('/dictionary/search?q='.$word)->assertDontSee('>lerne ich</span>', false)->assertSee('Lernen');
            $this->withHeaders($htmx)->post('/dictionary/learn/'.$deck->cards()->sole()->id)->assertOk()->assertSee('lerne ich');
            expect(app(DeckPreferenceService::class)->learningState($ich, $deck))->toBe('active');
        }
    });

    it('macht eine Kopie eines pausierten Stapels zu „Lerne ich“', function () {
        $ich = actingAsUser();
        $deck = adDeck($ich, 'Original', [['Hund', 'dog']]);
        adLearning($deck, false);

        $this->post("/decks/{$deck->id}/copy");
        $kopie = Deck::where('user_id', $ich->id)->whereKeyNot($deck->id)->sole();

        expect(app(DeckPreferenceService::class)->learningState($ich, $kopie))->toBe('active');
    });

    it('setzt beim Umstellen eigene Stapel mit Lernstand und gelernte geteilte auf „Lerne ich“, den Rest nicht', function () {
        $ich = User::factory()->create();
        $mitLernstand = adDeck($ich, 'Mit Lernstand', [['Hund', 'dog']]);
        $ohne = adDeck($ich, 'Ohne', [['Katze', 'cat']]);
        $pausiert = adDeck($ich, 'Pausiert', [['Maus', 'mouse']]);
        $geteilt = adDeck(User::factory()->create(), 'Geteilt', [['Baum', 'tree']], ['is_shared' => true]);
        $ich->followedDecks()->attach($geteilt);
        adDue($ich, $mitLernstand->cards()->sole());
        DB::table('deck_preferences')->insert(['user_id' => $ich->id, 'deck_id' => $pausiert->id, 'paused' => true, 'with_reverse' => true, 'created_at' => now(), 'updated_at' => now()]);

        $migration = require database_path('migrations/2026_10_19_100000_add_learning_to_deck_preferences.php');
        $migration->down();
        $migration->up();

        $states = app(DeckService::class)->learningStates($ich);
        expect($states)->toHaveCount(4)
            ->and($states[$ohne->id])->toBe('idle')
            ->and($states[$mitLernstand->id])->toBe('active')
            ->and($states[$pausiert->id])->toBe('paused')
            ->and($states[$geteilt->id])->toBe('active')
            ->and((bool) DB::table('deck_preferences')->where('deck_id', $pausiert->id)->value('with_reverse'))->toBeTrue();
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
