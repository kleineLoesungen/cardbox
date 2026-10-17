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

const WRONG_CODE = 'Dieser Code passt nicht. Bitte das Kind um einen neuen.';

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 08:00:00', 'UTC'));
});

/** The child creates a code on its account page; returns it as shown. */
function guardianCode(User $child): string
{
    test()->actingAs($child)->post('/account/guardian-code')->assertRedirect('/account#eltern');

    return (string) session('guardian_code');
}

/** Links the guardian to the child through a fresh code. */
function linkGuardian(User $guardian, User $child): void
{
    $code = guardianCode($child);
    test()->actingAs($guardian)->post('/account/children', ['code' => $code])->assertRedirect("/children/{$child->id}");
}

function redeemError(string $code): string
{
    test()->from('/account')->post('/account/children', ['code' => $code])->assertRedirect('/account#kinder');

    $errors = session('errors');

    // The session stores the bags as plain arrays (JSON serialisation).
    return is_array($errors) ? (string) ($errors['children']['messages']['code'][0] ?? '') : (string) $errors?->getBag('children')->first('code');
}

/** One card with progress of the child: box (5 = "Kann ich") and wrong answers. */
function childProgress(User $child, Card $card, int $box, int $wrong = 0, ?Carbon $reviewed = null): void
{
    $run = app(DeckRunService::class)->forStandard($child, Deck::findOrFail($card->deck_id));
    CardProgress::create(['user_id' => $child->id, 'deck_run_id' => $run->id, 'card_id' => $card->id, 'direction' => Direction::Forward,
        'box' => $box, 'due_on' => $box >= 5 ? null : app(LeitnerService::class)->today()->toDateString(),
        'review_count' => $wrong + 2, 'correct_count' => 2, 'last_reviewed_at' => $reviewed ?? now()]);
}

describe('Code vom Kind', function () {
    it('zeigt einen Code, und ein neuer ersetzt den alten', function () {
        $kind = User::factory()->create();
        $eltern = User::factory()->create();

        $this->actingAs($kind)->post('/account/guardian-code');
        $first = session('guardian_code');
        expect($first)->toMatch('/^[2-9A-HJKMNP-Z]{4}-[2-9A-HJKMNP-Z]{4}$/');
        $this->get('/account')->assertSee($first)->assertSee('Gilt 24 Stunden und nur einmal.');
        // Shown once: reloading shows only how long it is valid.
        $this->get('/account')->assertDontSee($first)->assertSee('Ein Code ist noch gültig bis');

        $second = guardianCode($kind);
        expect($second)->not->toBe($first)
            ->and(DB::table('guardian_codes')->count())->toBe(1)
            ->and(DB::table('guardian_codes')->value('code_hash'))->toBe(hash_hmac('sha256', str_replace('-', '', $second), (string) config('app.key')));

        $this->actingAs($eltern);
        expect(redeemError($first))->toBe(WRONG_CODE);
        $this->post('/account/children', ['code' => $second])->assertRedirect("/children/{$kind->id}");
    });

    it('verknüpft beim Einlösen und verbraucht den Code', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create();
        $papa = User::factory()->create();
        $code = guardianCode($kind);

        $this->actingAs($mama)->post('/account/children', ['code' => $code])
            ->assertRedirect("/children/{$kind->id}")
            ->assertSessionHas('toast.message', 'Ben ist jetzt in deiner Liste.');
        expect(DB::table('guardian_links')->where('guardian_id', $mama->id)->where('child_id', $kind->id)->exists())->toBeTrue()
            ->and(DB::table('guardian_codes')->count())->toBe(0);

        $this->actingAs($papa);
        expect(redeemError($code))->toBe(WRONG_CODE);
    });

    it('lässt den Code nach 24 Stunden nicht mehr gelten', function () {
        $kind = User::factory()->create();
        $code = guardianCode($kind);

        $this->travel(24)->hours();
        $this->actingAs(User::factory()->create());
        expect(redeemError($code))->toBe(WRONG_CODE)
            ->and(DB::table('guardian_codes')->count())->toBe(0);
    });

    it('nimmt den Code ohne Rücksicht auf Groß/Klein, Leerzeichen und Bindestrich', function () {
        $kind = User::factory()->create();
        $code = guardianCode($kind);
        $typed = ' '.strtolower(substr($code, 0, 2)).' '.strtolower(substr($code, 2, 2)).substr($code, 5).' ';

        $this->actingAs(User::factory()->create())->post('/account/children', ['code' => $typed])->assertRedirect("/children/{$kind->id}");
    });
});

describe('Fehler beim Einlösen', function () {
    it('gibt für falsche und unsinnige Codes dieselbe Meldung', function (string $code) {
        guardianCode(User::factory()->create());
        $this->actingAs(User::factory()->create());

        expect(redeemError($code))->toBe(WRONG_CODE);
    })->with(['ABCD-EFGH', 'K7P2-M9Q', '0000-0000', 'K7P2-M9QX-ZZ', '😀😀😀😀😀😀😀😀']);

    it('verlangt einen Code', function () {
        $this->actingAs(User::factory()->create());

        expect(redeemError('  '))->toBe('Bitte den Code vom Kind eingeben.');
    });

    it('erkennt den eigenen Code und eine schon bestehende Verknüpfung', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create();

        $own = guardianCode($kind);
        expect(redeemError($own))->toBe('Das ist dein eigener Code.');

        linkGuardian($mama, $kind);
        $again = guardianCode($kind);
        $this->actingAs($mama);
        expect(redeemError($again))->toBe('Ben ist schon in deiner Liste.');
    });

    it('erlaubt höchstens 4 Eltern je Kind und 10 Kinder je Elternteil', function () {
        $kind = User::factory()->create();
        foreach (User::factory()->count(4)->create() as $guardian) {
            linkGuardian($guardian, $kind);
        }
        $code = guardianCode($kind);
        $this->get('/account')->assertDontSee('Code für Eltern erzeugen');
        $this->actingAs(User::factory()->create());
        expect(redeemError($code))->toBe('Höchstens 4 Eltern je Kind.');

        $mama = User::factory()->create();
        foreach (User::factory()->count(10)->create() as $child) {
            DB::table('guardian_links')->insert(['guardian_id' => $mama->id, 'child_id' => $child->id, 'created_at' => now()]);
        }
        $code = guardianCode(User::factory()->create());
        $this->actingAs($mama);
        expect(redeemError($code))->toBe('Höchstens 10 Kinder.');
    });

    it('bremst nach 10 Versuchen in der Stunde', function () {
        $this->actingAs(User::factory()->create());

        foreach (range(1, 10) as $i) {
            $this->post('/account/children', ['code' => 'ABCD-EFGH'])->assertRedirect();
        }
        $this->post('/account/children', ['code' => 'ABCD-EFGH'])->assertStatus(429);
        // Other throttled routes keep their own count.
        $this->post('/password-suggestion')->assertOk();
    });
});

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

    it('beendet die Verknüpfung sofort, wenn das Kind „Nicht mehr zeigen“ wählt', function () {
        $kind = User::factory()->create();
        $mama = User::factory()->create(['name' => 'Anna']);
        linkGuardian($mama, $kind);

        $this->actingAs($kind)->get('/account')->assertSee('Anna')->assertSee('sieht deinen Fortschritt');
        $this->post("/account/guardians/{$mama->id}/delete")
            ->assertRedirect('/account#eltern')
            ->assertSessionHas('toast.message', 'Anna sieht deinen Fortschritt nicht mehr.');

        $this->actingAs($mama)->get("/children/{$kind->id}")->assertNotFound();
    });

    it('beendet die Verknüpfung, wenn der Elternteil das Kind entfernt', function () {
        $kind = User::factory()->create(['name' => 'Ben']);
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);

        $this->actingAs($mama)->get('/account')->assertSee('Meine Kinder')->assertSee("/children/{$kind->id}", false);
        $this->post("/account/children/{$kind->id}/delete")->assertRedirect('/account#kinder');

        $this->get("/children/{$kind->id}")->assertNotFound();
        expect(DB::table('guardian_links')->count())->toBe(0);
    });

    it('lässt fremde Verknüpfungen nicht lösen', function () {
        $kind = User::factory()->create();
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);

        $this->actingAs(User::factory()->create());
        $this->post("/account/children/{$kind->id}/delete")->assertNotFound();
        $this->post("/account/guardians/{$mama->id}/delete")->assertNotFound();
        // The guardian cannot end it from the child's side, and the other way round.
        $this->actingAs($mama)->post("/account/guardians/{$kind->id}/delete")->assertNotFound();
        $this->actingAs($kind)->post("/account/children/{$mama->id}/delete")->assertNotFound();

        expect(DB::table('guardian_links')->count())->toBe(1);
    });

    it('entfernt die Verknüpfung mit dem Kinderkonto', function () {
        $kind = User::factory()->create();
        $mama = User::factory()->create();
        linkGuardian($mama, $kind);
        guardianCode($kind);

        app(UserService::class)->delete($kind);

        expect(DB::table('guardian_links')->count())->toBe(0)
            ->and(DB::table('guardian_codes')->count())->toBe(0);
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
        childProgress($kind, $haus, 2, 4);
        childProgress($kind, $chat, 1, 3);
        DB::table('study_days')->insert(['user_id' => $kind->id, 'day' => '2026-10-14', 'reviews' => 12, 'correct' => 8, 'answers' => 8, 'goal_met' => false]);

        $html = $this->actingAs($mama)->get("/children/{$kind->id}")
            ->assertSee('Heute: <strong>8 von 20</strong> gewusst', false)
            ->assertSee('Kann schon <strong>2 Wörter</strong>', false)
            ->assertSee('+1 diese Woche')
            ->assertSeeInOrder(['Zuletzt gelernt', 'Katze – cat', 'Hund – dog'])
            ->assertSee('Englisch Unit 3')
            ->assertSee('Französisch Tiere')
            ->assertSee('1 fällig')
            ->assertSeeInOrder(['Knifflige Wörter', 'Zum Mitüben', 'Haus – house', 'Englisch Unit 3', 'Katze – le chat', 'Französisch Tiere'])
            ->assertSee('Ben hat dir das freigegeben')
            ->assertDontSee('Geheim')
            ->getContent();

        expect($html)->not->toContain("/decks/{$own->id}")
            ->and($html)->not->toContain("/decks/{$fremd->id}");
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
