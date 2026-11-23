<?php

use App\Enums\Role;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckService;
use App\Services\TourService;
use App\Services\UserService;
use App\Support\Tours;
use Illuminate\Support\Facades\File;

/*
 * Guided tours (docs/specs/gefuehrte-tour.md).
 */

function tourDeck(User $owner, string $name = 'Tiere'): Deck
{
    $deck = Deck::factory()->for($owner)->create(['name' => $name, 'front_language' => 'de', 'back_language' => 'en']);
    Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);

    return $deck;
}

it('liefert die offenen Schritte einer Seite und markiert neue mit „Neu:“', function () {
    $user = User::factory()->withTours()->create();
    $tours = app(TourService::class);

    expect(array_column($tours->pending($user, ['heute']), 'id'))->toBe(['heute.serie', 'heute.los', 'heute.lerne-ich']);

    $tours->markSeen($user, ['heute.serie', 'heute.los']);
    $pending = $tours->pending($user->fresh(), ['heute', 'stapel']);
    expect(array_column($pending, 'id'))->toBe(['heute.lerne-ich', 'stapel.entdecken', 'stapel.gruppen', 'stapel.neu'])
        ->and($pending[0]['title'])->toBe('Neu: Nur diese Stapel kommen dran')
        ->and($pending[1]['title'])->toBe('Stapel von anderen');
});

it('merkt sich nur bekannte Schritte, ohne Doppelte und höchstens 20 auf einmal', function () {
    $user = User::factory()->withTours()->create();
    $tours = app(TourService::class);

    expect($tours->markSeen($user, ['heute.serie', 'heute.serie', 'gibt.es.nicht', 42, ['x']]))->toBe(1)
        ->and($tours->markSeen($user, 'heute.los'))->toBe(0)
        ->and($tours->markSeen($user, [...array_fill(0, 20, 'heute.serie'), 'heute.los']))->toBe(0)
        ->and($user->fresh()->tour_steps_seen)->toBe(['heute.serie']);
});

it('nimmt gesehene Schritte entgegen und setzt sie zurück – nur beim eigenen Konto', function () {
    $user = actingAsUser(['tour_steps_seen' => []]);
    $other = User::factory()->withTours()->create();

    $this->postJson('/tours/seen', ['steps' => ['stapel.neu', 'stapel.entdecken']])->assertNoContent();
    expect($user->fresh()->tour_steps_seen)->toBe(['stapel.neu', 'stapel.entdecken'])
        ->and($other->fresh()->tour_steps_seen)->toBe([]);

    // Not a change of the account.
    expect($user->fresh()->updated_at->equalTo($user->updated_at))->toBeTrue();

    $this->post('/tours/reset')->assertRedirect('/learn')->assertSessionHas('toast.message', 'Die Touren kommen jetzt wieder.');
    expect($user->fresh()->tour_steps_seen)->toBe([]);

    $this->get('/help')->assertSee('Touren noch einmal zeigen');
    $this->get('/account')->assertSee('Touren noch einmal zeigen');
});

it('lädt Driver.js nur, wenn auf der Seite noch etwas offen ist', function () {
    $user = actingAsUser(['tour_steps_seen' => []]);
    tourDeck($user);

    $this->get('/decks')->assertSee('/js/driver-1.10.2.js')->assertSee('data-tour-steps', false)->assertSee('stapel.neu');

    $user->forceFill(['tour_steps_seen' => Tours::stepIds()])->save();
    $this->get('/decks')->assertDontSee('/js/driver-1.10.2.js')->assertDontSee('data-tour-steps', false);
});

it('zeigt das Teilen nur, wer teilen darf, und die Verwaltung nur Admins', function () {
    $kind = actingAsUser(['is_adult' => false, 'tour_steps_seen' => []]);
    $deck = tourDeck($kind);
    $this->get("/decks/{$deck->id}")->assertSee('stapel-eigen.menue')->assertDontSee('teilen.alle')->assertDontSee('admin.');

    $erwachsen = actingAsUser(['tour_steps_seen' => []]);
    $this->get('/decks/'.tourDeck($erwachsen)->id)->assertSee('teilen.alle')->assertSee('teilen.rechte');

    // Somebody else's shared deck: the tour for learning along, not for editing.
    $fremd = tourDeck(User::factory()->create(), 'Geteilt');
    $fremd->forceFill(['is_shared' => true])->save();
    $this->get("/decks/{$fremd->id}")->assertSee('stapel-fremd.lernen')->assertDontSee('stapel-eigen.menue')->assertDontSee('teilen.alle');

    actingAsAdmin(['tour_steps_seen' => []]);
    $this->get('/admin/users')->assertSee('admin.konto');
});

it('hat für jeden Schritt ein Ziel in den Seiten', function () {
    $views = collect(File::allFiles(resource_path('views')))->map(fn ($file) => $file->getContents())->implode("\n");

    foreach (Tours::PAGES as $steps) {
        foreach ($steps as $step) {
            if ($step['target'] !== null) {
                // As an attribute value or a quoted string in a Blade expression, not just any text.
                $found = str_contains($views, "data-tour=\"{$step['target']}\"") || str_contains($views, "'{$step['target']}'");
                expect($found)->toBeTrue("Kein data-tour=\"{$step['target']}\" für {$step['id']}");
            }
        }
    }
    expect(Tours::stepIds())->toHaveCount(count(array_unique(Tours::stepIds())));
});

it('setzt die Ziele auf den wichtigsten Seiten', function () {
    $user = actingAsUser(['tour_steps_seen' => []]);
    $deck = tourDeck($user);
    // A duel needs a few cards.
    Card::factory()->for($deck)->count(4)->create();
    app(DeckService::class)->startLearning($user, $deck);

    $this->get('/learn')->assertSee('data-tour="heute-serie"', false)->assertSee('data-tour="heute-los"', false)->assertSee('data-tour="heute-lerne-ich"', false);
    $this->get("/decks/{$deck->id}")->assertSee('data-tour="stapel-eigen-menue"', false)
        ->assertSee('data-tour="stapel-eigen-test"', false)->assertSee('data-tour="stapel-eigen-duell"', false);
    $other = Deck::factory()->for(User::factory()->create())->create(['is_shared' => true, 'front_language' => 'de', 'back_language' => 'en']);
    Card::factory()->for($other)->create(['front' => ['Zebrafink'], 'back' => ['zebra finch']]);
    $this->get('/dictionary/search?q=zebrafink')->assertSee('data-tour="woerterbuch-lernen"', false)->assertSee('data-tour="woerterbuch-mehr"', false);

    // New words start with an introduction; the card screen comes after "Weiter".
    $small = tourDeck($user, 'Eine Karte');
    $this->post('/learn/round', ['deck' => $small->id]);
    $intro = $this->get('/learn/round')->assertSee('/js/driver-1.10.2.js')->getContent();
    preg_match('/name="card" value="([^"]+)"/', $intro, $card);
    preg_match('/name="step" value="([^"]+)"/', $intro, $step);
    $this->withHeader('HX-Request', 'true')->post('/learn/round/answer', ['card' => $card[1], 'step' => $step[1]])
        ->assertSee('data-tour="runde-vorlesen"', false)
        ->assertSee('data-tour="runde-ehrlich"', false)->assertSee('data-tour="runde-aufhoeren"', false);
});

it('zeigt Gästen ohne Konto keine Tour', function () {
    $this->blade('<x-tour :pages="[\'heute\', \'duelle\']" />')->assertDontSee('data-tour-steps', false)->assertDontSee('driver-1.10.2');

    $this->actingAs(User::factory()->withTours()->create());
    $this->blade('<x-tour :pages="[\'duelle\']" />')->assertSee('data-tour-steps', false)->assertSee('duelle.gaeste');
});

it('gibt neuen Konten die Touren schon in derselben Anfrage', function () {
    $user = app(UserService::class)->create(
        ['name' => 'Neu', 'username' => 'neu.konto', 'password' => 'startpasswort-123', 'age' => 'adult'],
        Role::User,
    );

    expect(app(TourService::class)->pending($user, ['heute']))->toHaveCount(3);
});
