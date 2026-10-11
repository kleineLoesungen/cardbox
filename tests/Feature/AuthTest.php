<?php

use App\Enums\Role;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\DB;

/** Valid registration input; override single fields per test. */
function registration(array $overrides = []): array
{
    return [
        'name' => 'Lena Müller',
        'username' => 'lena.m',
        'password' => 'ein-langes-passwort',
        'password_confirmation' => 'ein-langes-passwort',
        ...$overrides,
    ];
}

describe('Ersteinrichtung', function () {
    it('bietet auf der Startseite die Einrichtung an, solange es kein Konto gibt', function () {
        $this->get('/')->assertOk()->assertSee('Ersten Administrator anlegen');
    });

    it('macht das erste Konto zum freigegebenen Administrator und meldet es an', function () {
        $this->post('/register', registration(['username' => ' Erste.Person ']))
            ->assertRedirect('/learn');

        $user = User::sole();
        expect($user->role)->toBe(Role::Admin)
            ->and($user->username)->toBe('erste.person')
            ->and($user->isApproved())->toBeTrue();
        $this->assertAuthenticatedAs($user);
    });

    it('verlangt ein Passwort mit mindestens zwölf Zeichen', function () {
        $this->from('/register')->post('/register', registration([
            'password' => 'zu-kurz', 'password_confirmation' => 'zu-kurz',
        ]))->assertRedirect('/register')->assertSessionHasErrors('password');

        expect(User::count())->toBe(0);
    });
});

describe('Registrieren', function () {
    beforeEach(fn () => User::factory()->admin()->create());

    it('legt ein wartendes Benutzerkonto an und meldet nicht an', function () {
        $this->post('/register', registration())->assertRedirect('/register');

        $user = User::where('username', 'lena.m')->sole();
        expect($user->role)->toBe(Role::User)
            ->and($user->isApproved())->toBeFalse();
        $this->assertGuest();

        $this->get('/register')->assertOk()->assertSee('Merk dir deinen Benutzernamen')->assertSee('lena.m');
    });

    it('lässt sich per Hand abgeschickt nicht zum Administrator machen', function () {
        $this->post('/register', registration(['role' => 'admin', 'approved_at' => now()->toIso8601String()]));

        $user = User::where('username', 'lena.m')->sole();
        expect($user->role)->toBe(Role::User)->and($user->isApproved())->toBeFalse();
    });

    it('weist einen vergebenen Benutzernamen ab und schlägt einen freien vor', function () {
        User::factory()->create(['username' => 'lena.m']);

        $this->from('/register')->post('/register', registration(['username' => 'Lena.M']))
            ->assertSessionHasErrors(['username' => 'Diesen Benutzernamen gibt es schon. Versuch zum Beispiel lena.m2.']);
    });

    it('weist ungültige Benutzernamen ab', function (string $username) {
        $this->from('/register')->post('/register', registration(['username' => $username]))
            ->assertSessionHasErrors('username');

        expect(User::count())->toBe(1);
    })->with(['ab', str_repeat('a', 31), 'lena@m', 'jürgen', 'lena m', '1lena', '.lena']);

    it('verlangt die gleiche Wiederholung des Passworts', function () {
        $this->from('/register')->post('/register', registration(['password_confirmation' => 'etwas-ganz-anderes']))
            ->assertSessionHasErrors('password');
    });

    it('begrenzt Registrierungen pro IP-Adresse', function () {
        foreach (range(1, 5) as $i) {
            $this->post('/register', registration(['username' => "kind{$i}"]))->assertRedirect('/register');
        }

        $this->post('/register', registration(['username' => 'kind6']))->assertStatus(429);
        expect(User::where('username', 'kind6')->exists())->toBeFalse();
    });

    it('leitet Angemeldete von der Registrierung weg', function () {
        actingAsUser();

        $this->get('/register')->assertRedirect();
    });

    it('schlägt ein merkbares Passwort vor', function () {
        $first = $this->post('/password-suggestion', ['confirm' => '1'])->assertOk();
        $second = $this->post('/password-suggestion', ['confirm' => '1']);

        preg_match('/name="password" value="([^"]+)"/', $first->getContent(), $a);
        preg_match('/name="password" value="([^"]+)"/', $second->getContent(), $b);

        expect($a[1])->toMatch('/^[A-Za-z]+-[A-Za-z]+-[A-Za-z]+-\d{2}$/')
            ->and(strlen($a[1]))->toBeGreaterThanOrEqual(12)
            ->and($a[1])->not->toBe($b[1]);
        $first->assertSee('name="password_confirmation"', false);
    });
});

describe('Anmelden', function () {
    it('meldet mit Benutzername an, unabhängig von Großschreibung und Leerraum', function () {
        $user = User::factory()->create(['username' => 'anna']);

        $this->post('/login', ['username' => ' ANNA ', 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/learn');

        $this->assertAuthenticatedAs($user);
    });

    it('schickt nach dem Anmelden dorthin zurück, wo man hinwollte', function () {
        $user = User::factory()->create();

        $this->get('/notes')->assertRedirect('/login');
        $this->post('/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/notes');
    });

    it('weist ein falsches Passwort ab, ohne zu verraten, ob es das Konto gibt', function () {
        User::factory()->create(['username' => 'anna']);

        $falsch = $this->from('/login')->post('/login', ['username' => 'anna', 'password' => 'falsches-passwort']);
        $unbekannt = $this->from('/login')->post('/login', ['username' => 'niemand', 'password' => 'falsches-passwort']);

        $falsch->assertSessionHasErrors(['username' => 'Benutzername oder Passwort stimmt nicht.']);
        $unbekannt->assertSessionHasErrors(['username' => 'Benutzername oder Passwort stimmt nicht.']);
        $this->assertGuest();
    });

    it('lässt wartende Konten nicht herein und zählt das nicht als Fehlversuch', function () {
        $user = User::factory()->pending()->create();

        $this->post('/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors('username');

        expect(session('errors')->first('username'))->toContain('noch nicht freigegeben')
            ->and(DB::table('login_attempts')->count())->toBe(0);
        $this->assertGuest();
    });

    it('verrät bei wartenden Konten mit falschem Passwort nichts', function () {
        $user = User::factory()->pending()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'falsches-passwort'])
            ->assertSessionHasErrors(['username' => 'Benutzername oder Passwort stimmt nicht.']);
    });

    it('lässt gesperrte Konten nicht herein', function () {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD])
            ->assertSessionHasErrors(['username' => 'Dieses Konto ist gesperrt.']);

        $this->assertGuest();
    });

    it('meldet ein Konto bei der nächsten Anfrage ab, sobald es gesperrt wird', function () {
        $user = actingAsUser();
        $this->get('/learn')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/learn')->assertRedirect('/login');
        $this->assertGuest();
    });

    it('meldet ab', function () {
        actingAsUser();

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });

    it('leitet Gäste von geschützten Seiten zum Login', function (string $path) {
        $this->get($path)->assertRedirect('/login');
    })->with(['/learn', '/dashboard', '/learn/round', '/notes', '/account', '/moderation', '/admin/users']);
});

describe('Anmeldebremse', function () {
    it('gibt drei Versuche frei und sperrt den vierten', function () {
        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $this->post('/login', ['username' => $user->username, 'password' => 'falsches-passwort'])
                ->assertSessionHasErrors(['username' => 'Benutzername oder Passwort stimmt nicht.']);
        }

        // Even with the CORRECT password there is a pause now.
        $this->post('/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD])
            ->assertStatus(302)
            ->assertSessionHasErrors('username');

        expect(session('errors')->first('username'))->toContain('Zu viele Fehlversuche');
        $this->assertGuest();
    });

    it('zählt gesperrte Versuche nicht mit', function () {
        $user = User::factory()->create();

        foreach (range(1, 8) as $_) {
            $this->post('/login', ['username' => $user->username, 'password' => 'falsch-falsch-falsch']);
        }

        expect(DB::table('login_attempts')->where('attempt_key', 'user:'.$user->username)->value('failures'))->toBe(3);
    });

    it('vergisst die Fehlversuche nach erfolgreicher Anmeldung', function () {
        $user = User::factory()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'falsch-falsch-falsch']);
        $this->post('/login', ['username' => $user->username, 'password' => UserFactory::PASSWORD]);

        expect(DB::table('login_attempts')->count())->toBe(0);
    });
});
