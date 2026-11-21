<?php

use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use App\Services\DeckService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Accounts under 18 and their parents (docs/specs/eltern-konten.md).
 */

/** A child under 18 with a parent of 18 or older; the parent is logged in. */
function family(string $parentName = 'Bea', string $childName = 'Mia'): array
{
    $parent = User::factory()->create(['name' => $parentName, 'username' => strtolower($parentName)]);
    $child = User::factory()->minor()->create(['name' => $childName, 'username' => strtolower($childName)]);
    DB::table('guardian_links')->insert(['guardian_id' => $parent->id, 'child_id' => $child->id, 'created_at' => now(), 'confirmed_at' => now()]);
    test()->actingAs($parent);

    return [$parent, $child];
}

function childDeck(User $child, string $name = 'Tiere'): Deck
{
    $deck = Deck::factory()->for($child)->create(['name' => $name, 'front_language' => 'de', 'back_language' => 'en']);
    Card::factory()->for($deck)->create(['front' => ['Hund'], 'back' => ['dog']]);

    return $deck;
}

// -----------------------------------------------------------------------------
// Admin: age and parents
// -----------------------------------------------------------------------------

it('legt ein Konto unter 18 nur mit Elternteil ab 18 an', function () {
    actingAsAdmin();
    User::factory()->create(['username' => 'bea']);
    User::factory()->minor()->create(['username' => 'tom']);
    $base = ['name' => 'Mia', 'username' => 'mia', 'password' => 'startpasswort-123', 'role' => 'user'];

    $this->post('/admin/users', $base)->assertSessionHasErrors('age');
    $this->post('/admin/users', [...$base, 'age' => 'minor'])
        ->assertSessionHasErrors(['parents' => 'Für ein Konto unter 18 braucht es mindestens ein Elternteil.']);
    $this->post('/admin/users', [...$base, 'age' => 'minor', 'parents' => 'tom'])->assertSessionHasErrors('parents');
    $this->post('/admin/users', [...$base, 'age' => 'minor', 'parents' => 'niemand'])->assertSessionHasErrors('parents');
    expect(User::where('username', 'mia')->exists())->toBeFalse();

    $this->post('/admin/users', [...$base, 'age' => 'minor', 'parents' => ' BEA ']);
    $mia = User::where('username', 'mia')->sole();
    expect($mia->is_adult)->toBeFalse()
        ->and($mia->guardians()->pluck('username')->all())->toBe(['bea']);
});

it('nimmt höchstens vier Eltern', function () {
    actingAsAdmin();
    foreach (['e1', 'e2', 'e3', 'e4', 'e5'] as $name) {
        User::factory()->create(['username' => $name]);
    }

    $this->post('/admin/users', [
        'name' => 'Mia', 'username' => 'mia', 'password' => 'startpasswort-123', 'role' => 'user',
        'age' => 'minor', 'parents' => 'e1, e2; e3 e4 e5',
    ])->assertSessionHasErrors('parents');
});

it('fragt bei der Freigabe nach Alter und Eltern', function () {
    $admin = actingAsAdmin();
    User::factory()->create(['username' => 'bea']);
    $mia = User::factory()->pending()->create(['username' => 'mia']);

    $this->post("/admin/users/{$mia->id}/approve", [])->assertSessionHasErrors('age', null, "approve-{$mia->id}");
    $this->post("/admin/users/{$mia->id}/approve", ['age' => 'minor'])->assertSessionHasErrors('parents', null, "approve-{$mia->id}");
    expect($mia->fresh()->isApproved())->toBeFalse();

    $this->post("/admin/users/{$mia->id}/approve", ['age' => 'minor', 'parents' => 'bea'])->assertRedirect();
    $mia->refresh();
    expect($mia->isApproved())->toBeTrue()
        ->and($mia->is_adult)->toBeFalse()
        ->and(DB::table('guardian_links')->where('child_id', $mia->id)->value('linked_by'))->toBe($admin->id);
});

it('stellt das Alter um: ab 18 löst die Eltern, unter 18 braucht welche und keine eigenen Kinder', function () {
    actingAsAdmin();
    $bea = User::factory()->create(['username' => 'bea']);
    $mia = User::factory()->create(['username' => 'mia']);
    $deck = childDeck($mia);
    $deck->forceFill(['is_shared' => true])->save();

    $this->post("/admin/users/{$mia->id}/age", ['age' => 'minor'])->assertSessionHasErrors('parents', null, 'family');
    $this->post("/admin/users/{$mia->id}/age", ['age' => 'minor', 'parents' => 'bea'])
        ->assertSessionHas('toast.message', "{$mia->name} gilt jetzt als unter 18.");
    expect($mia->fresh()->is_adult)->toBeFalse()
        ->and($deck->fresh()->share_review_pending)->toBeTrue();

    // A parent cannot become a child.
    $this->post("/admin/users/{$bea->id}/age", ['age' => 'minor', 'parents' => 'mia'])->assertSessionHasErrors();
    expect($bea->fresh()->is_adult)->toBeTrue();

    $this->post("/admin/users/{$mia->id}/age", ['age' => 'adult']);
    expect($mia->fresh()->is_adult)->toBeTrue()
        ->and($mia->guardians()->count())->toBe(0)
        ->and($deck->fresh()->share_review_pending)->toBeFalse()
        ->and($deck->fresh()->is_shared)->toBeTrue();
});

it('ergänzt und entfernt Eltern; das letzte bleibt', function () {
    actingAsAdmin();
    [$bea, $mia] = family();
    $tom = User::factory()->create(['username' => 'tom']);
    actingAsAdmin();

    $this->post("/admin/users/{$mia->id}/guardians", ['username' => 'bea'])->assertSessionHasErrors('parents', null, 'family');
    $this->post("/admin/users/{$mia->id}/guardians", ['username' => 'mia'])->assertSessionHasErrors('parents', null, 'family');
    $this->post("/admin/users/{$mia->id}/guardians", ['username' => 'tom'])->assertSessionHasNoErrors();
    expect($mia->guardians()->count())->toBe(2);

    $this->post("/admin/users/{$mia->id}/guardians/{$bea->id}/delete")->assertSessionHasNoErrors();
    $this->post("/admin/users/{$mia->id}/guardians/{$tom->id}/delete")->assertSessionHasErrors('parents', null, 'family');
    $this->post("/admin/users/{$mia->id}/guardians/{$bea->id}/delete")->assertNotFound();
    expect($mia->guardians()->pluck('users.id')->all())->toBe([$tom->id]);

    // Parents exist for accounts under 18 only.
    $this->post("/admin/users/{$tom->id}/guardians", ['username' => 'bea'])->assertSessionHasErrors('parents', null, 'family');
});

it('weist in der Benutzerliste auf Kinder ohne Elternteil hin', function () {
    actingAsAdmin();
    User::factory()->minor()->create(['name' => 'Ohne Eltern']);

    $this->get('/admin/users')->assertSee('Elternteil fehlt')->assertSee('unter 18');
});

// -----------------------------------------------------------------------------
// Who may share
// -----------------------------------------------------------------------------

it('lässt Kinder nicht teilen und verweist auf die Eltern', function () {
    $mia = actingAsUser(['is_adult' => false]);
    $deck = childDeck($mia);
    User::factory()->create(['username' => 'freundin']);

    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all'])->assertSessionHasErrors('share');
    $this->post("/decks/{$deck->id}/shares", ['username' => 'freundin'])->assertSessionHasErrors('share', null, 'share');
    expect($deck->fresh()->is_shared)->toBeFalse()
        ->and(DB::table('deck_shares')->count())->toBe(0);

    $this->get("/decks/{$deck->id}")->assertSee('Teilen können deine Eltern.')->assertSee('nur für dich und deine Eltern sichtbar')
        ->assertDontSee('Urheber- und Lizenzrechte');
});

it('lässt Kinder Freigaben selbst zurücknehmen', function () {
    $mia = actingAsUser(['is_adult' => false]);
    $deck = childDeck($mia);
    $deck->forceFill(['is_shared' => true, 'share_review_pending' => true])->save();
    $freundin = User::factory()->create();
    DB::table('deck_shares')->insert(['deck_id' => $deck->id, 'user_id' => $freundin->id, 'created_at' => now()]);

    $this->get("/decks/{$deck->id}")->assertSee('Zurücknehmen kannst du es selbst.')->assertDontSee('Hinzufügen');
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all'])->assertSessionHasErrors('share');
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'people'])->assertSessionHasNoErrors();
    expect($deck->fresh()->is_shared)->toBeFalse()->and($deck->fresh()->share_review_pending)->toBeTrue();

    $this->post("/decks/{$deck->id}/shares/{$freundin->id}/delete")->assertSessionHasNoErrors();
    expect(app(DeckService::class)->isSharedWith($freundin, $deck->fresh()))->toBeFalse();

    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'private'])->assertSessionHasNoErrors();
    expect($deck->fresh()->share_review_pending)->toBeFalse();
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'people'])->assertSessionHasErrors('share');
});

it('lässt Erwachsene ihre Stapel teilen, mit Hinweis auf Urheber- und Lizenzrechte', function () {
    $bea = actingAsUser();
    $deck = childDeck($bea, 'Eigene');

    $this->get("/decks/{$deck->id}")->assertSee('Urheber- und Lizenzrechte');
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all'])->assertSessionHasNoErrors();
    expect($deck->fresh()->is_shared)->toBeTrue();
});

// -----------------------------------------------------------------------------
// Parents look after the child's decks
// -----------------------------------------------------------------------------

it('lässt Eltern die Stapel des Kindes bearbeiten wie das Kind', function () {
    [$bea, $mia] = family();
    $deck = childDeck($mia);
    $card = $deck->cards()->sole();

    $this->get("/children/{$mia->id}")->assertSee('Stapel von Mia bearbeiten')->assertSee('Tiere');
    $this->get("/decks/{$deck->id}")->assertOk()
        ->assertSee('du bearbeitest ihn als Elternteil')
        ->assertSee('← Mia')
        ->assertSee("/children/{$mia->id}")
        ->assertSee('Privat: nur Mia und die Eltern sehen den Stapel.')
        ->assertDontSee('Stapel lernen');

    $this->post("/decks/{$deck->id}", ['name' => 'Tiere 5b', 'front_language' => 'de', 'back_language' => 'en'])->assertSessionHasNoErrors();
    $this->post("/decks/{$deck->id}/cards", ['front' => 'Katze', 'back' => 'cat'])->assertSessionHasNoErrors();
    $this->post("/decks/{$deck->id}/cards/{$card->id}", ['front' => 'Hund', 'back' => 'hound'])->assertSessionHasNoErrors();
    $this->post("/decks/{$deck->id}/import", ['text' => "Maus\tmouse"])->assertSessionHasNoErrors();
    $extra = $deck->cards()->where('front', json_encode(['Katze']))->sole();
    $this->post("/decks/{$deck->id}/cards/{$extra->id}/delete")->assertSessionHasNoErrors();
    // Changes in originals stay with the child.
    $this->post("/decks/{$deck->id}/cards/{$card->id}/origin/take")->assertNotFound();
    $this->get("/decks/{$deck->id}/origin")->assertNotFound();

    expect($deck->fresh()->name)->toBe('Tiere 5b')
        ->and($deck->cards()->count())->toBe(2)
        ->and($card->fresh()->back)->toBe(['hound'])
        ->and($deck->fresh()->user_id)->toBe($mia->id);

    $this->post("/decks/{$deck->id}/delete")->assertRedirect("/children/{$mia->id}");
    expect(Deck::find($deck->id))->toBeNull();
});

it('lässt Eltern einen Stapel für das Kind anlegen', function () {
    [$bea, $mia] = family();

    $response = $this->post("/children/{$mia->id}/decks", ['name' => 'Englisch Unit 3', 'front_language' => 'de', 'back_language' => 'en']);

    $deck = Deck::where('name', 'Englisch Unit 3')->sole();
    $response->assertRedirect("/decks/{$deck->id}?new=1");
    expect($deck->user_id)->toBe($mia->id);
});

it('lässt Eltern die Stapel des Kindes teilen und merkt, wer geteilt hat', function () {
    [$bea, $mia] = family();
    $deck = childDeck($mia);
    $freundin = User::factory()->create(['username' => 'freundin']);

    $this->get("/decks/{$deck->id}")->assertSee('Urheber- und Lizenzrechte');
    $this->post("/decks/{$deck->id}/shares", ['username' => 'freundin'])->assertSessionHasNoErrors();
    $this->post("/decks/{$deck->id}/shares", ['username' => 'mia'])->assertSessionHasErrors(['username' => 'Der Stapel gehört Mia schon.'], null, 'share');
    expect(DB::table('deck_shares')->where('deck_id', $deck->id)->where('user_id', $freundin->id)->value('shared_by'))->toBe($bea->id);

    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all'])->assertSessionHasNoErrors();
    expect($deck->fresh()->is_shared)->toBeTrue();

    $this->post("/decks/{$deck->id}/shares/{$freundin->id}/delete")->assertSessionHasNoErrors();
    expect(app(DeckService::class)->isSharedWith($freundin, $deck->fresh()))->toBeFalse();
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'private'])->assertSessionHasNoErrors();
    expect($deck->fresh()->is_shared)->toBeFalse();
});

it('lässt Eltern fremder Kinder und Eltern unter 18 nichts tun', function () {
    [, $mia] = family();
    $deck = childDeck($mia);

    $this->actingAs(User::factory()->create());
    $this->get("/decks/{$deck->id}")->assertNotFound();
    $this->post("/decks/{$deck->id}", ['name' => 'X', 'front_language' => 'de', 'back_language' => 'en'])->assertNotFound();
    $this->post("/children/{$mia->id}/decks", ['name' => 'X', 'front_language' => 'de', 'back_language' => 'en'])->assertNotFound();
    $this->post("/children/{$mia->id}/shared/{$deck->id}/decline")->assertNotFound();
    $this->post("/children/{$mia->id}/review/{$deck->id}", ['keep' => '0'])->assertNotFound();

    // A link to a parent under 18 (from before) grants nothing.
    $teen = User::factory()->minor()->create();
    DB::table('guardian_links')->insert(['guardian_id' => $teen->id, 'child_id' => $mia->id, 'created_at' => now(), 'confirmed_at' => now()]);
    $this->actingAs($teen);
    $this->get("/decks/{$deck->id}")->assertNotFound();
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all'])->assertNotFound();
    expect($deck->fresh()->is_shared)->toBeFalse();
});

it('lässt Eltern nicht für das Kind lernen', function () {
    [, $mia] = family();
    $deck = childDeck($mia);

    $this->post("/decks/{$deck->id}/follow")->assertNotFound();
    $this->get("/decks/{$deck->id}/selection")->assertNotFound();
    $this->post("/decks/{$deck->id}/typing", ['typing' => '1'])->assertNotFound();
    $this->post("/decks/{$deck->id}/learning", ['learning' => '0'])->assertNotFound();
});

it('lässt Eltern geteilte Stapel des Kindes ablehnen oder entfernen', function () {
    [$bea, $mia] = family();
    $lehrer = User::factory()->create(['name' => 'Herr Lehrer']);
    $byName = childDeck($lehrer, 'Vokabeln Klasse 5');
    DB::table('deck_shares')->insert(['deck_id' => $byName->id, 'user_id' => $mia->id, 'created_at' => now()]);
    $public = childDeck($lehrer, 'Öffentlich');
    $public->forceFill(['is_shared' => true])->save();
    $mia->followedDecks()->attach($public->id);

    $this->get("/children/{$mia->id}")->assertSee('Mit Mia geteilt')->assertSee('Vokabeln Klasse 5')->assertSee('Öffentlich');

    $this->post("/children/{$mia->id}/shared/{$byName->id}/decline")->assertRedirect("/children/{$mia->id}");
    $this->post("/children/{$mia->id}/shared/{$public->id}/decline")->assertRedirect("/children/{$mia->id}");

    expect(DB::table('deck_shares')->where('deck_id', $byName->id)->value('declined_at'))->not->toBeNull()
        ->and($mia->followedDecks()->count())->toBe(0)
        ->and(app(DeckService::class)->sharedWithChild($bea, $mia->id))->toHaveCount(0);

    // Declined by name: the teacher cannot share it again.
    $this->actingAs($lehrer)->post("/decks/{$byName->id}/shares", ['username' => $mia->username])->assertSessionHasErrors('username', null, 'share');
});

// -----------------------------------------------------------------------------
// "Bitte durchsehen"
// -----------------------------------------------------------------------------

it('lässt Eltern frühere Freigaben des Kindes durchsehen', function () {
    [, $mia] = family();
    $keep = childDeck($mia, 'So lassen');
    $keep->forceFill(['is_shared' => true, 'share_review_pending' => true])->save();
    $private = childDeck($mia, 'Privat machen');
    $private->forceFill(['share_review_pending' => true])->save();
    $freundin = User::factory()->create();
    DB::table('deck_shares')->insert(['deck_id' => $private->id, 'user_id' => $freundin->id, 'created_at' => now()]);

    $this->get("/children/{$mia->id}")->assertSee('Bitte einmal durchsehen');

    $this->post("/children/{$mia->id}/review/{$keep->id}", ['keep' => '1'])->assertSessionHas('toast.message', '„So lassen“ bleibt geteilt.');
    $this->post("/children/{$mia->id}/review/{$private->id}", ['keep' => '0'])->assertSessionHas('toast.message', '„Privat machen“ ist jetzt privat.');

    expect($keep->fresh()->is_shared)->toBeTrue()
        ->and($keep->fresh()->share_review_pending)->toBeFalse()
        ->and($private->fresh()->share_review_pending)->toBeFalse()
        ->and(app(DeckService::class)->isSharedWith($freundin, $private->fresh()))->toBeFalse();
    $this->get("/children/{$mia->id}")->assertDontSee('Bitte einmal durchsehen');

    // Done is done; the child itself cannot do it; nor via another child's address.
    $this->post("/children/{$mia->id}/review/{$keep->id}", ['keep' => '0'])->assertNotFound();
    $keep->refresh()->forceFill(['share_review_pending' => true])->save();
    $this->post('/children/'.User::factory()->minor()->create()->id."/review/{$keep->id}", ['keep' => '0'])->assertNotFound();
    $this->actingAs($mia)->post("/children/{$mia->id}/review/{$keep->id}", ['keep' => '0'])->assertNotFound();
    expect($keep->fresh()->share_review_pending)->toBeTrue()->and($keep->fresh()->is_shared)->toBeTrue();
});

it('lässt Admins für Kinder ohne Eltern durchsehen', function () {
    $mia = User::factory()->minor()->create();
    $deck = childDeck($mia);
    $deck->forceFill(['is_shared' => true, 'share_review_pending' => true])->save();
    actingAsAdmin();

    $this->get("/admin/users/{$mia->id}")->assertSee('Tiere');
    $this->post("/admin/users/{$mia->id}/review/{$deck->id}", ['keep' => '0'])->assertSessionHas('toast.message', '„Tiere“ ist jetzt privat.');

    expect($deck->fresh()->is_shared)->toBeFalse()->and($deck->fresh()->share_review_pending)->toBeFalse();
    $this->post("/admin/users/{$mia->id}/review/{$deck->id}", ['keep' => '0'])->assertNotFound();

    // Only under the owner's address.
    $other = childDeck($mia, 'Anderer');
    $other->forceFill(['share_review_pending' => true])->save();
    $this->post('/admin/users/'.User::factory()->create()->id."/review/{$other->id}", ['keep' => '0'])->assertNotFound();
});

// -----------------------------------------------------------------------------
// Links from the time of codes and the migration
// -----------------------------------------------------------------------------

it('lässt alte Code-Verknüpfungen nur zuschauen, bis die Verwaltung sie bestätigt', function () {
    $bea = User::factory()->create(['name' => 'Bea', 'username' => 'bea']);
    $mia = User::factory()->minor()->create(['name' => 'Mia']);
    DB::table('guardian_links')->insert(['guardian_id' => $bea->id, 'child_id' => $mia->id, 'created_at' => now()]);
    $deck = childDeck($mia);

    $this->actingAs($bea)->get("/children/{$mia->id}")->assertOk()
        ->assertSee('sobald die Verwaltung dich als Elternteil bestätigt')
        ->assertDontSee('Stapel von Mia bearbeiten');
    $this->get("/decks/{$deck->id}")->assertNotFound();
    $this->post("/children/{$mia->id}/decks", ['name' => 'X', 'front_language' => 'de', 'back_language' => 'en'])->assertNotFound();

    actingAsAdmin();
    $this->get('/admin/users')->assertSee('Elternteil fehlt');
    $this->get("/admin/users/{$mia->id}")->assertSee('nur zuschauen')->assertSee('Bestätigen');
    $this->post("/admin/users/{$mia->id}/guardians/{$bea->id}/confirm")->assertSessionHasNoErrors();
    $this->get('/admin/users')->assertDontSee('Elternteil fehlt');

    $this->actingAs($bea)->get("/decks/{$deck->id}")->assertOk()->assertSee('du bearbeitest ihn als Elternteil');
});

it('gibt Eltern keine Rechte über Konten ab 18', function () {
    $bea = User::factory()->create();
    $grown = User::factory()->create();
    DB::table('guardian_links')->insert(['guardian_id' => $bea->id, 'child_id' => $grown->id, 'created_at' => now(), 'confirmed_at' => now()]);
    $deck = childDeck($grown);

    $this->actingAs($bea)->get("/decks/{$deck->id}")->assertNotFound();
    $this->post("/decks/{$deck->id}/visibility", ['visibility' => 'all'])->assertNotFound();
});

it('macht aus einem Elternteil kein Kind', function () {
    actingAsAdmin();
    [$bea] = family();
    User::factory()->create(['username' => 'oma']);
    actingAsAdmin();

    $this->post("/admin/users/{$bea->id}/age", ['age' => 'minor', 'parents' => 'oma'])->assertSessionHasErrors('age', null, 'family');
    $bea->forceFill(['is_adult' => false])->save();
    $this->post("/admin/users/{$bea->id}/guardians", ['username' => 'oma'])->assertSessionHasErrors('parents', null, 'family');
});

it('macht das erste Konto der Installation zum Erwachsenen', function () {
    $this->post('/register', ['name' => 'Erste', 'username' => 'erste', 'password' => 'richtig-langes-passwort', 'password_confirmation' => 'richtig-langes-passwort']);

    expect(User::where('username', 'erste')->sole()->is_adult)->toBeTrue();
});

it('stellt bei der Migration Alter, Durchsicht und alte Verknüpfungen richtig', function () {
    $migration = require database_path('migrations/2026_11_21_100000_add_adult_and_review_to_users_and_decks.php');
    $admin = User::factory()->admin()->create();
    $moderator = User::factory()->moderator()->create();
    $kind = User::factory()->create();
    $mama = User::factory()->create();
    $shared = childDeck($kind, 'Öffentlich');
    $shared->forceFill(['is_shared' => true])->save();
    $byName = childDeck($kind, 'Per Name');
    DB::table('deck_shares')->insert(['deck_id' => $byName->id, 'user_id' => $mama->id, 'created_at' => now()]);
    $declined = childDeck($kind, 'Abgelehnt');
    DB::table('deck_shares')->insert(['deck_id' => $declined->id, 'user_id' => $mama->id, 'created_at' => now(), 'declined_at' => now()]);
    $private = childDeck($kind, 'Privat');
    $adminShared = childDeck($admin, 'Admin');
    $adminShared->forceFill(['is_shared' => true])->save();

    $migration->down();
    DB::table('guardian_links')->insert([
        ['guardian_id' => $mama->id, 'child_id' => $kind->id, 'created_at' => now()],
        ['guardian_id' => $mama->id, 'child_id' => $moderator->id, 'created_at' => now()],
    ]);
    $migration->up();

    $adult = DB::table('users')->pluck('is_adult', 'id');
    $pending = DB::table('decks')->pluck('share_review_pending', 'name');
    expect($adult[$admin->id])->toBeTrue()
        ->and($adult[$moderator->id])->toBeTrue()
        ->and($adult[$kind->id])->toBeFalse()
        ->and($adult[$mama->id])->toBeFalse()
        ->and($pending->only(['Öffentlich', 'Per Name', 'Abgelehnt', 'Privat', 'Admin'])->all())
        ->toEqual(['Öffentlich' => true, 'Per Name' => true, 'Abgelehnt' => false, 'Privat' => false, 'Admin' => false])
        ->and(DB::table('guardian_links')->where('child_id', $moderator->id)->exists())->toBeFalse()
        ->and(DB::table('guardian_links')->where('child_id', $kind->id)->value('confirmed_at'))->toBeNull()
        ->and(Schema::hasTable('guardian_codes'))->toBeFalse();
});
