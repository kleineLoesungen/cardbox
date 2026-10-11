<?php

namespace App\Http\Controllers;

use App\Services\LoginThrottle;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sign in, registration (incl. first setup), sign out.
 *
 * Bewusst normale HTML-Formulare ohne htmx: Sie enden in einer Weiterleitung,
 * und dabei soll der Browser die Adresszeile aendern.
 */
class AuthController extends Controller
{
    public function __construct(private UserService $users) {}

    public function showLogin(): View
    {
        return view('pages.login', ['firstSetup' => $this->users->isEmpty()]);
    }

    public function login(Request $request, LoginThrottle $throttle): RedirectResponse
    {
        $username = (string) $request->input('username');
        $keys = LoginThrottle::keys($username, $request->ip());

        // Erst die Bremse, dann das Passwort: Ein gesperrter Versuch soll den
        // Server keine Argon2-Rechenzeit kosten. Und er zaehlt NICHT als
        // Fehlversuch — sonst verlaengert stures Weiterklicken die eigene Sperre.
        if (($seconds = $throttle->lockedFor($keys)) !== null) {
            Log::warning('Anmeldung gesperrt', ['username' => $username, 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'username' => 'Zu viele Fehlversuche. Bitte '.self::waitText($seconds).' erneut versuchen.',
            ])->status(429);
        }

        $user = $this->users->findByCredentials($username, (string) $request->input('password'));

        if ($user === null) {
            $throttle->recordFailure($keys);

            throw ValidationException::withMessages([
                'username' => 'Benutzername oder Passwort stimmt nicht.',
            ]);
        }

        // The password was right, so these are not guessing attempts and do
        // not count against the throttle.
        if (! $user->isApproved()) {
            throw ValidationException::withMessages([
                'username' => 'Dein Konto ist noch nicht freigegeben. Frag eine Administratorin oder einen Administrator.',
            ]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['username' => 'Dieses Konto ist gesperrt.']);
        }

        $throttle->recordSuccess($keys);

        Auth::login($user);
        // Neue Session-ID: verhindert Session Fixation.
        $request->session()->regenerate();

        Log::info('Anmeldung erfolgreich', ['user_id' => $user->id]);

        // Zurueck dorthin, wo der Benutzer hinwollte. Laravel speichert das Ziel
        // in der Session, nicht in der URL — eine offene Weiterleitung ist so
        // nicht moeglich.
        return redirect()->intended('/learn');
    }

    /** Registration form, or the confirmation after a successful registration. */
    public function showRegister(Request $request): View
    {
        if ($request->session()->has('registered')) {
            return view('pages.registered', ['username' => (string) $request->session()->get('registered')]);
        }

        return view('pages.register', ['firstSetup' => $this->users->isEmpty()]);
    }

    public function register(Request $request): RedirectResponse
    {
        // Whether the account becomes the first admin or has to wait for
        // approval is decided in UserService::register — not here and not in
        // the template, so a hand-crafted form changes nothing.
        $user = $this->users->register($request->only('name', 'username', 'password', 'password_confirmation'));

        if (! $user->isApproved()) {
            Log::info('Registrierung wartet auf Freigabe', ['user_id' => $user->id]);

            return redirect('/register')->with('registered', $user->username);
        }

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('Ersteinrichtung abgeschlossen', ['user_id' => $user->id]);

        return redirect('/learn');
    }

    /**
     * htmx: fills the password field(s) with a memorable suggestion. Public
     * on purpose (registration needs it); it returns random words, no data.
     */
    public function suggestPassword(Request $request): View
    {
        return view('partials.password-fields', [
            'value' => $this->users->suggestPassword(),
            'confirm' => $request->boolean('confirm'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /** Wartezeit so ausdruecken, wie ein Mensch sie liest. */
    public static function waitText(int $seconds): string
    {
        return $seconds <= 90
            ? "in {$seconds} Sekunden"
            : 'in '.(int) ceil($seconds / 60).' Minuten';
    }
}
