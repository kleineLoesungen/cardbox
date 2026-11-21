<?php

use App\Enums\Direction;
use App\Models\Card;
use App\Models\CardProgress;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckRunService;
use App\Services\GuardianService;
use App\Services\LeitnerService;
use App\Services\UserService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Parents see the progress (docs/specs/eltern-ansicht.md).
 */

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** Links the guardian to the child, as an admin does (eltern-konten.md); the child is under 18. */
function linkGuardian(User $guardian, User $child): void
{
    DB::table('users')->where('id', $child->id)->update(['is_adult' => false]);
    DB::table('guardian_links')->insert(['guardian_id' => $guardian->id, 'child_id' => $child->id, 'created_at' => now(), 'confirmed_at' => now()]);
    test()->actingAs($guardian);
}

/** One card with progress of the child: box (5 = "Kann ich") and wrong answers. */
function childProgress(User $child, Card $card, int $box, int $wrong = 0, ?Carbon $reviewed = null): void
{
    $run = app(DeckRunService::class)->forStandard($child, Deck::findOrFail($card->deck_id));
    CardProgress::create(['user_id' => $child->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
        'box' => $box, 'due_on' => $box >= 5 ? null : app(LeitnerService::class)->today()->toDateString(),
        'review_count' => $wrong + 2, 'correct_count' => 2, 'last_reviewed_at' => $reviewed ?? now()]);
}

describe('Trennung', function () {
    it('zeigt nicht verknüpfte Kinder niemandem, auch Admins nicht', function () {
        $kind = User::factory()->create();
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);

        $this->actingAs(User::factory()->create())->get("/children/{$kind->id}")->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get("/children/{$kind->id}")->assertNotFound();
        $this->actingAs($kind)->get("/children/{$mama->id}")->assertNotFound();
        $this->actingAs($mama)->get('/children/abc')->assertNotFound();
        $this->get("/children/{$kind->id}")->assertOk();
    });

    it('lässt die Zuordnung weder vom Kind noch vom Elternteil lösen (das macht die Verwaltung)', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create(['name' => 'Anna']);
        linkGuardian($mama, $kind);

        $this->actingAs($kind)->get('/account')->assertSee('Deine Eltern:')->assertSee('Anna')->assertDontSee('Nicht mehr zeigen');
        $this->post("/account/guardians/{$mama->id}/delete")->assertNotFound();
        $this->post('/account/guardian-code')->assertNotFound();
        $this->actingAs($mama)->get('/account')->assertSee('Meine Kinder')->assertSee('Kinder ordnet die Verwaltung zu.');
        $this->post("/account/children/{$kind->id}/delete")->assertNotFound();
        $this->post('/account/children', ['code' => 'ABCD-EFGH'])->assertNotFound();

        expect(DB::table('guardian_links')->count())->toBe(1);
    });

    it('entfernt die Verknüpfung mit dem Kinderkonto', function () {
        $kind = User::factory()->create();
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);

        app(UserService::class)->delete($kind);

        expect(DB::table('guardian_links')->count())->toBe(0);
        $this->actingAs($mama)->get('/children')->assertSee('Noch keine Kinder');
    });

    it('zeigt bei gesperrtem Kinderkonto nur einen Hinweis', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);
        $deck = Deck::factory()->for($kind)->create(['name' => 'Englisch Unit 3']);
        DB::table('users')->where('id', $kind->id)->update(['is_active' => false]);

        $this->actingAs($mama)->get("/children/{$kind->id}")
            ->assertOk()
            ->assertSee('Dieses Konto ist gerade gesperrt.')
            ->assertDontSee($deck->name);
    });
});

describe('Ansicht für Eltern', function () {
    it('zeigt Lerntage der letzten vier Wochen in Berliner Zeit', function () {
        // 14.10. 00:30 in Berlin, still the 13th in UTC.
        $this->travelTo(Carbon::parse('2026-10-13 22:30:00', 'UTC'));
        $kind = User::factory()->create();
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);
        DB::table('study_days')->insert([
            ['user_id' => $kind->id, 'day' => '2026-10-14', 'reviews' => 25, 'correct' => 20, 'answers' => 20, 'goal_met' => true],
            ['user_id' => $kind->id, 'day' => '2026-10-12', 'reviews' => 4, 'correct' => 2, 'answers' => 2, 'goal_met' => false],
            ['user_id' => $kind->id, 'day' => '2026-09-21', 'reviews' => 9, 'correct' => 9, 'answers' => 9, 'goal_met' => true],
            ['user_id' => $kind->id, 'day' => '2026-09-20', 'reviews' => 9, 'correct' => 9, 'answers' => 9, 'goal_met' => true],
        ]);

        $html = $this->actingAs($mama)->get("/children/{$kind->id}")
            ->assertSee('Mi 14.10.: Ziel geschafft')
            ->assertSee('Mo 12.10.: gelernt')
            ->assertSee('Di 13.10.: nicht gelernt')
            ->assertSee('So 18.10.: noch nicht')
            ->assertSee('Mo 21.9.: Ziel geschafft')
            ->assertSee('Di 22.9.: nicht gelernt')
            ->assertDontSee('20.9.')
            ->assertSee('Heute: Tagesziel geschafft')
            ->assertSee('Jede Kachel ist ein Tag, jede Zeile eine Woche')
            ->assertSee('1 Tag in Folge')
            ->getContent();

        expect(substr_count($html, 'role="listitem"'))->toBe(28)
            ->and(substr_count($html, 'day-today'))->toBe(1);
    });

    it('zeigt Wortschatz, Stapel und knifflige Wörter, aber keine Kartenlisten', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);

        $own = Deck::factory()->for($kind)->create(['name' => 'Englisch Unit 3', 'front_language' => 'de', 'back_language' => 'en']);
        $hund = Card::factory()->for($own)->create(['front' => ['Hund'], 'back' => ['dog']]);
        $katze = Card::factory()->for($own)->create(['front' => ['Katze'], 'back' => ['cat']]);
        $haus = Card::factory()->for($own)->create(['front' => ['Haus'], 'back' => ['house']]);
        $fremd = Deck::factory()->shared()->create(['name' => 'Französisch Tiere', 'front_language' => 'de', 'back_language' => 'fr']);
        $kind->followedDecks()->attach($fremd);
        $chat = Card::factory()->for($fremd)->create(['front' => ['Katze'], 'back' => ['le chat']]);
        Card::factory()->for($fremd)->create(['front' => ['Geheim'], 'back' => ['secret']]);

        childProgress($kind, $hund, 5, 0, now()->subDays(10));
        childProgress($kind, $katze, 5, 0, now()->subDay());
        // The box right before "Kann ich" (5 boxes): "fast geschafft" (issue #26).
        childProgress($kind, $haus, 4, 4);
        childProgress($kind, $chat, 1, 3);
        DB::table('study_days')->insert(['user_id' => $kind->id, 'day' => '2026-10-14', 'reviews' => 12, 'correct' => 8, 'answers' => 8, 'goal_met' => false]);

        $html = $this->actingAs($mama)->get("/children/{$kind->id}")
            ->assertSee('Heute: <strong>8 von 20</strong> gewusst', false)
            ->assertSee('Kann schon <strong>2 Wörter</strong>', false)
            ->assertSee('+1 diese Woche')
            ->assertSeeInOrder(['Zuletzt gelernt', 'Katze – cat', 'Hund – dog'])
            ->assertSee('Englisch Unit 3')
            ->assertSee('Französisch Tiere')
            // Split bar per deck (issues #18, #26).
            ->assertSee('2 kann Ben · 1 fast geschafft · 0 im Karteikasten · 0 noch nicht gelernt')
            ->assertSee('0 kann Ben · 0 fast geschafft · 1 im Karteikasten · 1 noch nicht gelernt')
            ->assertSee('1 fällig')
            ->assertSeeInOrder(['Knifflige Wörter', 'Zum Mitüben', 'Haus – house', 'Englisch Unit 3', 'Katze – le chat', 'Französisch Tiere'])
            ->assertSee('Die Zuordnung als Elternteil hat die Verwaltung eingerichtet.')
            ->assertDontSee('Geheim')
            ->getContent();

        // The child's own deck to edit (eltern-konten.md); others' decks only by name.
        expect($html)->toContain("/decks/{$own->id}")
            ->and($html)->not->toContain("/decks/{$fremd->id}\"");
    });

    it('sagt, wenn das Kind noch nicht gelernt hat', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);

        $this->actingAs($mama)->get("/children/{$kind->id}")
            ->assertSee('Ben hat noch nicht gelernt.')
            ->assertDontSee('Lerntage')
            ->assertDontSee('Knifflige Wörter');
    });
});

describe('Menü', function () {
    it('zeigt „Meine Kinder“ nur mit Kindern und führt bei einem Kind direkt hin', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create();

        $this->actingAs($mama)->get('/learn')->assertDontSee('Meine Kinder');

        linkGuardian($mama, $kind);
        $this->get('/learn')->assertSee('href="/children"', false)->assertSee('Meine Kinder');
        $this->get('/children')->assertRedirect("/children/{$kind->id}");
        $this->actingAs($kind)->get('/learn')->assertDontSee('Meine Kinder');

        $schwester = User::factory()->create(['name' => 'Clara']);
        linkGuardian($mama, $schwester);
        $this->actingAs($mama)->get('/children')->assertOk()->assertSeeInOrder(['Ben', 'Clara']);
    });

    it('rechnet Kinder auch für Admins nur über die Verknüpfung', function () {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();

        expect(app(GuardianService::class)->childrenOf($admin))->toHaveCount(0);
        $this->actingAs($admin)->get('/children')->assertOk()->assertSee('Noch keine Kinder');
    });
});

describe('Was Eltern nicht sehen', function () {
    it('lässt Wörter aus nicht mehr gelernten, nicht mehr geteilten und aussortierten Karten weg', function () {
        $kind = User::factory()->create();
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);

        $entfolgt = Deck::factory()->shared()->create(['name' => 'Entfolgter Stapel']);
        $privat = Deck::factory()->shared()->create(['name' => 'Jetzt privat']);
        $eigen = Deck::factory()->for($kind)->create(['name' => 'Eigener Stapel']);
        $kind->followedDecks()->attach([$entfolgt->id, $privat->id]);
        foreach ([[$entfolgt, 'Apfel', 'apple'], [$privat, 'Birne', 'pear'], [$eigen, 'Kirsche', 'cherry'], [$eigen, 'Pflaume', 'plum']] as [$deck, $front, $back]) {
            $card = Card::factory()->for($deck)->create(['front' => [$front], 'back' => [$back]]);
            childProgress($kind, $card, 5);
            childProgress($kind, Card::factory()->for($deck)->create(['front' => ["{$front}baum"], 'back' => ["{$back} tree"]]), 1, 3);
        }

        DB::table('study_days')->insert(['user_id' => $kind->id, 'day' => '2026-10-14', 'reviews' => 8, 'correct' => 4, 'answers' => 4, 'goal_met' => false]);
        $kind->followedDecks()->detach($entfolgt->id);
        DB::table('decks')->where('id', $privat->id)->update(['is_shared' => false]);
        // Only "Kirsche" and its tree stay active in the own deck.
        $active = Card::where('deck_id', $eigen->id)->get()->filter(fn (Card $c) => str_starts_with($c->frontText(), 'Kirsche'))->modelKeys();
        DB::table('deck_selections')->insert(['user_id' => $kind->id, 'deck_id' => $eigen->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($active as $id) {
            DB::table('deck_selection_cards')->insert(['user_id' => $kind->id, 'deck_id' => $eigen->id, 'card_id' => $id]);
        }

        $this->actingAs($mama)->get("/children/{$kind->id}")
            ->assertSee('Kirsche – cherry')
            ->assertSee('Kirschebaum – cherry tree')
            ->assertDontSee('Pflaume')
            ->assertDontSee('Apfel')
            ->assertDontSee('Birne')
            ->assertDontSee('Entfolgter Stapel')
            ->assertDontSee('Jetzt privat');
    });

    it('nennt höchstens 10 zuletzt gelernte und 20 knifflige Wörter', function () {
        $kind = User::factory()->create();
        $deck = Deck::factory()->for($kind)->create();
        foreach (range(1, 12) as $i) {
            childProgress($kind, Card::factory()->for($deck)->create(['front' => ["gut{$i}"], 'back' => ["good{$i}"]]), 5, 0, now()->subMinutes($i));
        }
        foreach (range(1, 22) as $i) {
            childProgress($kind, Card::factory()->for($deck)->create(['front' => ["schwer{$i}"], 'back' => ["hard{$i}"]]), 1, 3 + $i);
        }

        $overview = app(GuardianService::class)->overview($kind);

        expect($overview['recent'])->toHaveCount(10)
            ->and($overview['recent'][0])->toBe('gut1 – good1')
            ->and($overview['hard'])->toHaveCount(20)
            ->and($overview['hard'][0]['word'])->toBe('schwer22 – hard22');
    });
});
