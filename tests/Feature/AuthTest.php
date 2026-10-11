<?php

use App\Enums\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;

describe('Ersteinrichtung', function () {
    it('bietet auf der Startseite die Einrichtung an, solange es kein Konto gibt', function () {
        $this->get('/')->assertOk()->assertSee('Ersten Administrator anlegen');
    });

    it('macht das erste Konto zum Administrator und meldet es an', function () {
        $this->post('/register', [
            'name' => 'Erste Person',
            'email' => 'Erste@Example.com',
            'password' => 'ein-langes-passwort',
        ])->assertRedirect('/dashboard');

        $user = User::sole();
        expect($user->role)->toBe(Role::Admin)
            ->and($user->email)->toBe('erste@example.com');
        $this->assertAuthenticatedAs($user);
    });

    it('schließt die Registrierung, sobald ein Konto existiert — auch per Hand abgeschickt', function () {
        User::factory()->create();

        $this->get('/register')->assertRedirect('/login');
        $this->post('/register', [
            'name' => 'Eindringling',
            'email' => 'boese@example.com',
            'password' => 'ein-langes-passwort',
        ])->assertRedirect('/login');

        expect(User::count())->toBe(1);
        $this->assertGuest();
    });

    it('verlangt ein Passwort mit mindestens zwölf Zeichen', function () {
        $this->from('/register')->post('/register', [
            'name' => 'Kurz',
            'email' => 'kurz@example.com',
            'password' => 'zu-kurz',
        ])->assertRedirect('/register')->assertSessionHasErrors('password');

        expect(User::count())->toBe(0);
    });
});

describe('Anmelden', function () {
    it('meldet mit richtigem Passwort an, unabhängig von Großschreibung der E-Mail', function () {
        $user = User::factory()->create(['email' => 'anna@example.com']);

        $this->post('/login', ['email' => ' ANNA@example.com ', 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    });

    it('schickt nach dem Anmelden dorthin zurück, wo man hinwollte', function () {
        $user = User::factory()->create();

        $this->get('/notes')->assertRedirect('/login');
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/notes');
    });

    it('weist ein falsches Passwort ab, ohne zu verraten, ob es das Konto gibt', function () {
        User::factory()->create(['email' => 'anna@example.com']);

        $falsch = $this->from('/login')->post('/login', ['email' => 'anna@example.com', 'password' => 'falsches-passwort']);
        $unbekannt = $this->from('/login')->post('/login', ['email' => 'niemand@example.com', 'password' => 'falsches-passwort']);

        $falsch->assertSessionHasErrors(['email' => 'E-Mail oder Passwort stimmt nicht.']);
        $unbekannt->assertSessionHasErrors(['email' => 'E-Mail oder Passwort stimmt nicht.']);
        $this->assertGuest();
    });

    it('lässt gesperrte Konten nicht herein', function () {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors(['email' => 'Dieses Konto ist gesperrt.']);

        $this->assertGuest();
    });

    it('meldet ein Konto bei der nächsten Anfrage ab, sobald es gesperrt wird', function () {
        $user = actingAsUser();
        $this->get('/dashboard')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    });

    it('meldet ab', function () {
        actingAsUser();

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });

    it('leitet Gäste von geschützten Seiten zum Login', function (string $path) {
        $this->get($path)->assertRedirect('/login');
    })->with(['/dashboard', '/notes', '/account', '/moderation', '/admin/users']);
});

describe('Anmeldebremse', function () {
    it('gibt drei Versuche frei und sperrt den vierten', function () {
        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'falsches-passwort'])
                ->assertSessionHasErrors(['email' => 'E-Mail oder Passwort stimmt nicht.']);
        }

        // Selbst mit dem RICHTIGEN Passwort ist jetzt erst einmal Pause.
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD])
            ->assertStatus(302)
            ->assertSessionHasErrors('email');

        expect(session('errors')->first('email'))->toContain('Zu viele Fehlversuche');
        $this->assertGuest();
    });

    it('zählt gesperrte Versuche nicht mit', function () {
        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'falsch-falsch-falsch']);
        }
        foreach (range(1, 5) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'falsch-falsch-falsch']);
        }

        expect(DB::table('login_attempts')->where('attempt_key', 'email:'.$user->email)->value('failures'))->toBe(3);
    });

    it('vergisst die Fehlversuche nach erfolgreicher Anmeldung', function () {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'falsch-falsch-falsch']);
        $this->post('/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD]);

        expect(DB::table('login_attempts')->count())->toBe(0);
    });
});
