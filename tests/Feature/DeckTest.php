<?php

use App\Enums\Role;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;

describe('Sichtbarkeit', function () {
    it('versteckt fremde private Stapel vor allen, auch vor Moderation und Verwaltung', function (Role $role) {
        $fremd = Deck::factory()->create();
        actingAsUser(['role' => $role]);

        $this->get("/decks/{$fremd->id}")->assertNotFound();
        $this->post("/decks/{$fremd->id}/follow")->assertNotFound();
        $this->post("/decks/{$fremd->id}/copy")->assertNotFound();
        $this->get("/decks/{$fremd->id}/import")->assertNotFound();
        $this->post("/moderation/decks/{$fremd->id}/delete")->assertStatus($role === Role::User ? 403 : 404);
        $this->post("/moderation/decks/{$fremd->id}/unshare")->assertStatus($role === Role::User ? 403 : 404);

        expect(Deck::find($fremd->id))->not->toBeNull();
    })->with([Role::User, Role::Moderator, Role::Admin]);

    it('zeigt fremde geteilte Stapel, lässt sie aber nicht ändern', function () {
        $fremd = Deck::factory()->shared()->create(['name' => 'Tiere']);
        Card::factory()->for($fremd)->create(['front' => ['Hund'], 'back' => ['dog']]);
        actingAsUser();

        $this->get("/decks/{$fremd->id}")->assertOk()->assertSee('Tiere')->assertSee('Hund')->assertSee('Lernen')->assertDontSee('Folgen');
        $this->post("/decks/{$fremd->id}", ['name' => 'Gekapert', 'front_language' => 'de', 'back_language' => 'en'])->assertNotFound();
        $this->post("/decks/{$fremd->id}/share", ['shared' => '0'])->assertNotFound();
        $this->post("/decks/{$fremd->id}/delete")->assertNotFound();
        $this->post("/decks/{$fremd->id}/cards", ['front' => ['x'], 'back' => ['y']])->assertNotFound();
        $this->get("/decks/{$fremd->id}/import")->assertNotFound();

        expect($fremd->refresh()->name)->toBe('Tiere')->and($fremd->cards()->count())->toBe(1);
    });

    it('listet unter „Entdecken“ nur geteilte Stapel anderer', function () {
        $ich = actingAsUser();
        Deck::factory()->for($ich)->shared()->create(['name' => 'Mein Geteilter']);
        Deck::factory()->shared()->create(['name' => 'Fremd Geteilt']);
        Deck::factory()->create(['name' => 'Fremd Privat']);

        $this->get('/decks?tab=discover')
            ->assertSee('Fremd Geteilt')
            ->assertDontSee('Fremd Privat')
            ->assertDontSee('Mein Geteilter');
    });

    it('sucht in „Entdecken“ nach Name und Sprache, Platzhalter wörtlich', function () {
        actingAsUser();
        Deck::factory()->shared()->create(['name' => 'Latein 100%', 'front_language' => 'la', 'back_language' => 'de']);
        Deck::factory()->shared()->create(['name' => 'Englisch Basis']);

        $this->get('/decks?tab=discover&q=%25')->assertSee('Latein 100%')->assertDontSee('Englisch Basis');
        $this->get('/decks?tab=discover&lang=la')->assertSee('Latein 100%')->assertDontSee('Englisch Basis');
    });

    it('antwortet auf ungültige IDs mit 404 statt 500', function () {
        actingAsUser();

        $this->get('/decks/keine-uuid')->assertNotFound();
        $this->post('/decks/123/follow')->assertNotFound();
    });
});

describe('Eigene Stapel', function () {
    it('legt einen privaten Stapel an und öffnet ihn zum Befüllen', function () {
        $ich = actingAsUser();

        $response = $this->post('/decks', ['name' => '  Unit 3 ', 'front_language' => 'de', 'back_language' => 'en']);

        $deck = Deck::sole();
        $response->assertRedirect("/decks/{$deck->id}?new=1");
        expect($deck->user_id)->toBe($ich->id)
            ->and($deck->name)->toBe('Unit 3')
            ->and($deck->is_shared)->toBeFalse();
    });

    it('lässt das Formular für neue Stapel leer, auch wenn schon Stapel gelistet sind', function () {
        $ich = actingAsUser();
        Deck::factory()->for($ich)->create(['name' => 'Schon da']);

        $this->get('/decks')->assertOk()
            ->assertSee('id="new-name"', false)
            ->assertDontSee('value="Schon da"', false);
    });

    it('verlangt verschiedene Sprachen vorne und hinten', function () {
        actingAsUser();

        $this->from('/decks')->post('/decks', ['name' => 'X', 'front_language' => 'de', 'back_language' => 'de'])
            ->assertSessionHasErrors(['back_language' => 'Vorne und hinten brauchen verschiedene Sprachen.']);
        $this->from('/decks')->post('/decks', ['name' => 'X', 'front_language' => 'de', 'back_language' => 'klingonisch'])
            ->assertSessionHasErrors('back_language');

        expect(Deck::count())->toBe(0);
    });

    it('ändert Sprachen, ohne die Karten anzufassen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        $card = Card::factory()->for($deck)->create(['front' => ['Haus'], 'back' => ['house']]);

        $this->post("/decks/{$deck->id}", ['name' => 'Neu', 'front_language' => 'de', 'back_language' => 'fr'])
            ->assertRedirect("/decks/{$deck->id}");

        expect($deck->refresh()->back_language)->toBe('fr')
            ->and($card->refresh()->back)->toBe(['house']);
    });

    it('teilt einen Stapel und nimmt das Teilen zurück', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();

        $this->post("/decks/{$deck->id}/share", ['shared' => '1']);
        expect($deck->refresh()->is_shared)->toBeTrue();

        $this->post("/decks/{$deck->id}/share", ['shared' => '0']);
        expect($deck->refresh()->is_shared)->toBeFalse();
    });

    it('löscht einen Stapel samt Karten und Folgen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->shared()->create();
        Card::factory()->for($deck)->count(3)->create();
        User::factory()->create()->followedDecks()->attach($deck);

        $this->post("/decks/{$deck->id}/delete")->assertRedirect('/decks');

        expect(Deck::count())->toBe(0)->and(Card::count())->toBe(0)
            ->and(DB::table('deck_follows')->count())->toBe(0);
    });

    it('zeigt unter „Meine“ eigene und gefolgte, aber keine fremden Stapel', function () {
        $ich = actingAsUser();
        Deck::factory()->for($ich)->create(['name' => 'Eigener']);
        $gefolgt = Deck::factory()->shared()->create(['name' => 'Gefolgter']);
        $ich->followedDecks()->attach($gefolgt);
        Deck::factory()->shared()->create(['name' => 'Fremder']);

        $this->get('/decks')->assertSee('Eigener')->assertSee('Gefolgter')->assertDontSee('Fremder');
        $this->get('/learn')->assertSee('Eigener')->assertSee('Gefolgter');
    });
});

describe('Folgen und Kopieren', function () {
    it('folgt einem geteilten Stapel und sieht Änderungen der Besitzerin sofort', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->shared()->create();
        $card = Card::factory()->for($deck)->create(['front' => ['Haus'], 'back' => ['house']]);

        $this->post("/decks/{$deck->id}/follow")->assertRedirect("/decks/{$deck->id}");
        $this->post("/decks/{$deck->id}/follow");
        expect($ich->followedDecks()->count())->toBe(1);

        $card->update(['back' => ['home']]);
        $this->get("/decks/{$deck->id}")->assertSee('home')->assertSee('lerne ich');
    });

    it('lässt dem eigenen Stapel nicht folgen', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->shared()->create();

        $this->from("/decks/{$deck->id}")->post("/decks/{$deck->id}/follow")->assertSessionHasErrors('deck');
        expect($ich->followedDecks()->count())->toBe(0);
    });

    it('blendet einen gefolgten Stapel aus, sobald er nicht mehr geteilt ist, und zeigt ihn beim erneuten Teilen wieder', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->shared()->create(['name' => 'Wechselhaft']);
        $ich->followedDecks()->attach($deck);

        $deck->forceFill(['is_shared' => false])->save();
        $this->get('/decks')->assertDontSee('Wechselhaft');
        $this->get("/decks/{$deck->id}")->assertNotFound();

        $deck->forceFill(['is_shared' => true])->save();
        $this->get('/decks')->assertSee('Wechselhaft');
    });

    it('entfolgt', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->shared()->create();
        $ich->followedDecks()->attach($deck);

        $this->post("/decks/{$deck->id}/unfollow")->assertRedirect('/decks');

        expect($ich->followedDecks()->count())->toBe(0);
    });

    it('kopiert einen geteilten Stapel als unabhängigen privaten Stapel', function () {
        $ich = actingAsUser();
        $original = Deck::factory()->shared()->create(['name' => 'Tiere']);
        $card = Card::factory()->for($original)->create(['front' => ['Hund'], 'back' => ['dog', 'hound']]);

        $this->post("/decks/{$original->id}/copy");

        $copy = Deck::where('user_id', $ich->id)->sole();
        $copied = $copy->cards()->sole();
        expect($copy->is_shared)->toBeFalse()
            ->and($copy->name)->toBe('Tiere')
            ->and($copy->copied_from_name)->toBe('Tiere')
            ->and($copied->id)->not->toBe($card->id)
            ->and($copied->back)->toBe(['dog', 'hound']);

        $card->update(['back' => ['geändert']]);
        expect($copied->refresh()->back)->toBe(['dog', 'hound']);
    });

    it('kopiert auch den eigenen Stapel', function () {
        $ich = actingAsUser();
        $deck = Deck::factory()->for($ich)->create();
        Card::factory()->for($deck)->count(2)->create();

        $this->post("/decks/{$deck->id}/copy");

        expect($ich->decks()->count())->toBe(2)->and(Card::count())->toBe(4);
    });
});

describe('Moderation', function () {
    it('beendet als Moderatorin das Teilen eines fremden Stapels', function () {
        actingAsUser(['role' => Role::Moderator]);
        $deck = Deck::factory()->shared()->create();

        $this->post("/moderation/decks/{$deck->id}/unshare")->assertRedirect('/decks');

        expect($deck->refresh()->is_shared)->toBeFalse();
    });

    it('löscht als Moderatorin einen fremden geteilten Stapel', function () {
        actingAsUser(['role' => Role::Moderator]);
        $deck = Deck::factory()->shared()->create();

        $this->post("/moderation/decks/{$deck->id}/delete")->assertRedirect('/decks');

        expect(Deck::find($deck->id))->toBeNull();
    });

    it('verbietet einfachen Benutzern die Moderation', function () {
        actingAsUser();
        $deck = Deck::factory()->shared()->create();

        $this->post("/moderation/decks/{$deck->id}/unshare")->assertForbidden();
        $this->post("/moderation/decks/{$deck->id}/delete")->assertForbidden();

        expect($deck->refresh()->is_shared)->toBeTrue();
    });

    it('listet geteilte Stapel auf der Moderationsseite', function () {
        actingAsUser(['role' => Role::Moderator]);
        Deck::factory()->shared()->create(['name' => 'Sichtbar']);
        Deck::factory()->create(['name' => 'Versteckt']);

        $this->get('/moderation')->assertOk()->assertSee('Sichtbar')->assertDontSee('Versteckt');
    });
});

describe('Konto löschen', function () {
    it('löscht mit dem Konto dessen Stapel, Karten und Folgen', function () {
        actingAsAdmin();
        $weg = User::factory()->create();
        $deck = Deck::factory()->for($weg)->shared()->create();
        Card::factory()->for($deck)->create();
        User::factory()->create()->followedDecks()->attach($deck);

        $this->post("/admin/users/{$weg->id}/delete")->assertRedirect('/admin/users');

        expect(Deck::count())->toBe(0)->and(Card::count())->toBe(0)
            ->and(DB::table('deck_follows')->count())->toBe(0);
    });
});

it('nennt beim Löschen eines Kontos dessen Stapel und Folgende', function () {
    actingAsAdmin();
    $weg = User::factory()->create();
    $deck = Deck::factory()->for($weg)->shared()->create();
    User::factory()->count(2)->create()->each(fn (User $u) => $u->followedDecks()->attach($deck));

    $this->get("/admin/users/{$weg->id}")
        ->assertSee('Dabei werden 1 Stapel gelöscht, mit denen insgesamt 2 Personen lernen.', false);
});

it('zeigt einen leeren Stapel ohne leeren Rahmen und bringt den Leerzustand nach dem Löschen der letzten Karte zurück', function () {
    $ich = actingAsUser();
    $deck = Deck::factory()->for($ich)->create();
    $card = Card::factory()->for($deck)->create();

    $this->withHeaders(['HX-Request' => 'true'])
        ->post("/decks/{$deck->id}/cards/{$card->id}/delete")
        ->assertSee('hx-swap-oob="afterend:#card-list"', false)
        ->assertSee('Noch keine Karten');
});
