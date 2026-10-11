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

it('legt einen sofort freigegebenen Benutzer an', function () {
    $admin = actingAsAdmin();

    $response = $this->post('/admin/users', [
        'name' => 'Bernd Neu',
        'username' => 'Bernd.Neu',
        'password' => 'startpasswort-123',
        'role' => 'moderator',
    ]);

    $created = User::where('username', 'bernd.neu')->sole();
    $response->assertRedirect("/admin/users/{$created->id}");
    expect($created->role)->toBe(Role::Moderator)
        ->and($created->isApproved())->toBeTrue()
        ->and($created->approved_by)->toBe($admin->id);
});

it('behält beim Fehler im Anlegen die Eingaben', function () {
    actingAsAdmin();
    User::factory()->create(['username' => 'belegt']);

    $this->from('/admin/users')->post('/admin/users', [
        'name' => 'Doppelt',
        'username' => 'belegt',
        'password' => 'startpasswort-123',
        'role' => 'user',
    ])->assertRedirect('/admin/users')->assertSessionHasErrors('username')->assertSessionHasInput('name', 'Doppelt');
});

it('lehnt eine unbekannte Rolle ab', function () {
    actingAsAdmin();

    $this->post('/admin/users', [
        'name' => 'X', 'username' => 'xaver', 'password' => 'startpasswort-123', 'role' => 'superadmin',
    ])->assertSessionHasErrors('role');
});

it('sucht in Name und Benutzername und nimmt Platzhalter wörtlich', function () {
    actingAsAdmin(['name' => 'Anna Admin', 'username' => 'anna.admin']);
    User::factory()->create(['name' => 'Bernd Beispiel', 'username' => 'bernd.b']);
    User::factory()->create(['name' => '100% Clara', 'username' => 'clara.c']);
    User::factory()->create(['name' => 'Dora', 'username' => 'xy.berndfan']);

    // Checked via the username: the own name shows in the header anyway.
    $this->get('/admin/users?q=BERND')->assertSee('bernd.b')->assertSee('xy.berndfan')->assertDontSee('anna.admin');
    $this->get('/admin/users?q=%25')->assertSee('clara.c')->assertDontSee('bernd.b');
});

it('lässt Administratoren sich nicht selbst degradieren oder sperren', function () {
    $admin = actingAsAdmin();
    User::factory()->admin()->create();

    $this->post("/admin/users/{$admin->id}", [
        'name' => $admin->name, 'username' => $admin->username, 'role' => 'user', 'is_active' => '1',
    ])->assertSessionHasErrors('role');

    $this->post("/admin/users/{$admin->id}", [
        'name' => $admin->name, 'username' => $admin->username, 'role' => 'admin',
    ])->assertSessionHasErrors('role');

    expect($admin->refresh())->role->toBe(Role::Admin)->is_active->toBeTrue();
});

it('ändert Rolle und Status eines anderen Kontos', function () {
    actingAsAdmin();
    $anderer = User::factory()->admin()->create();

    $this->post("/admin/users/{$anderer->id}", [
        'name' => 'Umbenannt', 'username' => 'Neu.Name', 'role' => 'moderator',
    ])->assertRedirect("/admin/users/{$anderer->id}");

    expect($anderer->refresh())
        ->name->toBe('Umbenannt')
        ->username->toBe('neu.name')
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

describe('Freigabe', function () {
    it('zeigt wartende Konten getrennt von der Liste', function () {
        actingAsAdmin();
        User::factory()->pending()->create(['name' => 'Wartende Wilma', 'username' => 'wilma']);

        $this->get('/admin/users')
            ->assertSee('Warten auf Freigabe (1)')
            ->assertSee('wilma');

        expect(app(UserService::class)->list()->pluck('username'))->not->toContain('wilma');
    });

    it('gibt ein wartendes Konto frei', function () {
        $admin = actingAsAdmin();
        $wartend = User::factory()->pending()->create();

        $this->post("/admin/users/{$wartend->id}/approve")->assertRedirect('/admin/users');

        expect($wartend->refresh()->isApproved())->toBeTrue()
            ->and($wartend->approved_by)->toBe($admin->id);
    });

    it('lehnt ein wartendes Konto ab, indem es gelöscht wird', function () {
        actingAsAdmin();
        $wartend = User::factory()->pending()->create();

        $this->post("/admin/users/{$wartend->id}/reject")->assertRedirect('/admin/users');

        expect(User::find($wartend->id))->toBeNull();
    });

    it('lehnt freigegebene Konten nicht ab', function () {
        actingAsAdmin();
        $aktiv = User::factory()->create();

        $this->from('/admin/users')->post("/admin/users/{$aktiv->id}/reject")->assertSessionHasErrors('user');

        expect(User::find($aktiv->id))->not->toBeNull();
    });

    it('lässt nur Administratoren freigeben und ablehnen', function (Role $role) {
        actingAsUser(['role' => $role]);
        $wartend = User::factory()->pending()->create();

        $this->post("/admin/users/{$wartend->id}/approve")->assertForbidden();
        $this->post("/admin/users/{$wartend->id}/reject")->assertForbidden();

        expect($wartend->refresh()->isApproved())->toBeFalse();
    })->with([Role::User, Role::Moderator]);

    it('löscht Registrierungen, die 30 Tage lang nicht freigegeben wurden', function () {
        actingAsAdmin();
        $alt = User::factory()->pending()->create(['created_at' => now()->subDays(31)]);
        $jung = User::factory()->pending()->create(['created_at' => now()->subDays(29)]);
        $altAberFrei = User::factory()->create(['created_at' => now()->subDays(100)]);

        $this->get('/admin/users')->assertOk();

        expect(User::find($alt->id))->toBeNull()
            ->and(User::find($jung->id))->not->toBeNull()
            ->and(User::find($altAberFrei->id))->not->toBeNull();
    });

    it('zählt wartende Konten nicht als aktive Administratoren', function () {
        User::factory()->admin()->pending()->create();
        $einziger = User::factory()->admin()->create();

        expect(app(UserService::class)->isLastActiveAdmin($einziger))->toBeTrue();
    });

    it('zeigt die Zahl wartender Konten nur Administratoren', function () {
        User::factory()->pending()->count(2)->create();

        actingAsAdmin();
        $this->get('/dashboard')->assertSee('2 warten auf Freigabe');

        actingAsUser(['role' => Role::Moderator]);
        $this->get('/dashboard')->assertDontSee('warten auf Freigabe');
    });
});

describe('Vorschläge', function () {
    it('schlägt aus dem Namen einen freien Benutzernamen vor', function () {
        actingAsAdmin();
        User::factory()->create(['username' => 'lena.mueller']);

        $this->get('/admin/users/suggest-username?name='.urlencode('Lena Müller'))
            ->assertOk()->assertSee('value="lena.mueller2"', false);
    });

    it('überschreibt einen schon eingetragenen Benutzernamen nicht', function () {
        actingAsAdmin();

        $this->get('/admin/users/suggest-username?name=Lena&username=eigen')
            ->assertSee('value="eigen"', false);
    });

    it('bildet Vorschläge nach den Regeln für Benutzernamen', function (string $name, string $expected) {
        expect(app(UserService::class)->suggestUsername($name))->toBe($expected);
    })->with([
        ['Lena Müller', 'lena.mueller'],
        ['  Jörg  Groß ', 'joerg.gross'],
        ['Ö', 'user'],
        ['42 Zahlen', 'zahlen'],
        ['Anne-Marie O\'Neill', 'anne-marie.oneill'],
    ]);

    it('lässt Nicht-Administratoren keine Benutzernamen erraten', function () {
        actingAsUser();

        $this->get('/admin/users/suggest-username?name=Lena')->assertForbidden();
    });
});
