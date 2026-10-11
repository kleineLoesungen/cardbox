<?php

use App\Enums\Role;
use App\Models\ApiToken;
use App\Models\Note;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

describe('Zugriff nach Rolle', function () {
    it('verweigert normalen Benutzern die Verwaltung', function (string $path) {
        actingAsUser();
        $this->get($path)->assertForbidden();
    })->with(['/admin/users', '/admin/tokens', '/admin/system', '/moderation']);

    it('lässt Moderatoren in die Moderation, aber nicht in die Verwaltung', function () {
        actingAsUser(['role' => Role::Moderator]);

        $this->get('/moderation')->assertOk();
        $this->get('/admin/users')->assertForbidden();
    });

    it('lässt Administratoren überall hin', function (string $path) {
        actingAsAdmin();
        $this->get($path)->assertOk();
    })->with(['/admin/users', '/admin/tokens', '/admin/system', '/moderation']);
});

it('legt einen Benutzer an', function () {
    actingAsAdmin();

    $response = $this->post('/admin/users', [
        'name' => 'Bernd Neu',
        'email' => 'bernd@example.com',
        'password' => 'startpasswort-123',
        'role' => 'moderator',
    ]);

    $created = User::where('email', 'bernd@example.com')->sole();
    $response->assertRedirect("/admin/users/{$created->id}");
    expect($created->role)->toBe(Role::Moderator);
});

it('behält beim Fehler im Anlegen die Eingaben', function () {
    actingAsAdmin();
    User::factory()->create(['email' => 'belegt@example.com']);

    $this->from('/admin/users')->post('/admin/users', [
        'name' => 'Doppelt',
        'email' => 'belegt@example.com',
        'password' => 'startpasswort-123',
        'role' => 'user',
    ])->assertRedirect('/admin/users')->assertSessionHasErrors('email')->assertSessionHasInput('name', 'Doppelt');
});

it('lehnt eine unbekannte Rolle ab', function () {
    actingAsAdmin();

    $this->post('/admin/users', [
        'name' => 'X', 'email' => 'x@example.com', 'password' => 'startpasswort-123', 'role' => 'superadmin',
    ])->assertSessionHasErrors('role');
});

it('sucht in Name und E-Mail und nimmt Platzhalter wörtlich', function () {
    actingAsAdmin(['name' => 'Anna Admin', 'email' => 'anna@example.com']);
    User::factory()->create(['name' => 'Bernd Beispiel', 'email' => 'bernd@example.com']);
    User::factory()->create(['name' => '100% Clara', 'email' => 'clara@example.com']);

    // Geprueft wird ueber die E-Mail: Der eigene Name steht ohnehin in der Kopfzeile.
    $this->get('/admin/users?q=BERND')->assertSee('bernd@example.com')->assertDontSee('anna@example.com');
    $this->get('/admin/users?q=%25')->assertSee('clara@example.com')->assertDontSee('bernd@example.com');
});

it('lässt Administratoren sich nicht selbst degradieren oder sperren', function () {
    $admin = actingAsAdmin();
    User::factory()->admin()->create();

    $this->post("/admin/users/{$admin->id}", [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'user', 'is_active' => '1',
    ])->assertSessionHasErrors('role');

    $this->post("/admin/users/{$admin->id}", [
        'name' => $admin->name, 'email' => $admin->email, 'role' => 'admin',
    ])->assertSessionHasErrors('role');

    expect($admin->refresh())->role->toBe(Role::Admin)->is_active->toBeTrue();
});

it('ändert Rolle und Status eines anderen Kontos', function () {
    actingAsAdmin();
    $anderer = User::factory()->admin()->create();

    $this->post("/admin/users/{$anderer->id}", [
        'name' => 'Umbenannt', 'email' => $anderer->email, 'role' => 'moderator',
    ])->assertRedirect("/admin/users/{$anderer->id}");

    expect($anderer->refresh())
        ->name->toBe('Umbenannt')
        ->role->toBe(Role::Moderator)
        ->is_active->toBeFalse();
});

it('verhindert, dass der letzte Administrator gesperrt oder gelöscht wird', function () {
    $service = app(UserService::class);
    $einziger = User::factory()->admin()->create();

    expect(fn () => $service->setActive($einziger, false))->toThrow(ValidationException::class)
        ->and(fn () => $service->delete($einziger))->toThrow(ValidationException::class)
        ->and($einziger->refresh()->is_active)->toBeTrue();
});

it('lässt Administratoren sich nicht selbst löschen', function () {
    $admin = actingAsAdmin();
    User::factory()->admin()->create();

    $this->from("/admin/users/{$admin->id}")->post("/admin/users/{$admin->id}/delete")
        ->assertSessionHasErrors('role');

    expect(User::find($admin->id))->not->toBeNull();
});

it('löscht ein Konto samt Notizen, lässt aber ausgestellte Tokens bestehen', function () {
    actingAsAdmin();
    $anderer = User::factory()->admin()->create();
    Note::factory()->for($anderer)->create();
    $token = ApiToken::create([
        'name' => 'Dienst', 'token_id' => 'abc', 'token_hash' => 'x', 'scopes' => ['notes:read'], 'created_by' => $anderer->id,
    ]);

    $this->post("/admin/users/{$anderer->id}/delete")->assertRedirect('/admin/users');

    expect(User::find($anderer->id))->toBeNull()
        ->and(Note::count())->toBe(0)
        ->and($token->refresh()->created_by)->toBeNull();
});

it('setzt ein Passwort zurück', function () {
    actingAsAdmin();
    $user = User::factory()->create();

    $this->post("/admin/users/{$user->id}/password", ['password' => 'neues-startpasswort'])
        ->assertRedirect("/admin/users/{$user->id}");

    expect(Hash::check('neues-startpasswort', $user->refresh()->password))->toBeTrue();
});
