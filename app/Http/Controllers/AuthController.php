<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\LoginThrottle;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Anmelden, Ersteinrichtung, Abmelden.
 *
 * Bewusst normale HTML-Formulare ohne htmx: Sie enden in einer Weiterleitung,
 * und dabei soll der Browser die Adresszeile aendern.
 */
class AuthController extends Controller
{
    public function __construct(private UserService $users) {}

    public function showLogin(): View
    {
        return view('pages.login', ['firstSetup' => $this->isFirstSetup()]);
    }

    public function login(Request $request, LoginThrottle $throttle): RedirectResponse
    {
        $email = (string) $request->input('email');
        $keys = LoginThrottle::keys($email, $request->ip());

        // Erst die Bremse, dann das Passwort: Ein gesperrter Versuch soll den
        // Server keine Argon2-Rechenzeit kosten. Und er zaehlt NICHT als
        // Fehlversuch — sonst verlaengert stures Weiterklicken die eigene Sperre.
        if (($seconds = $throttle->lockedFor($keys)) !== null) {
            Log::warning('Anmeldung gesperrt', ['email' => $email, 'ip' => $request->ip()]);

            throw ValidationException::withMessages([
                'email' => 'Zu viele Fehlversuche. Bitte '.self::waitText($seconds).' erneut versuchen.',
            ])->status(429);
        }

        $user = $this->users->findByCredentials($email, (string) $request->input('password'));

        if ($user === null || ! $user->is_active) {
            $throttle->recordFailure($keys);

            throw ValidationException::withMessages([
                'email' => $user === null ? 'E-Mail oder Passwort stimmt nicht.' : 'Dieses Konto ist gesperrt.',
            ]);
        }

        $throttle->recordSuccess($keys);

        Auth::login($user);
        // Neue Session-ID: verhindert Session Fixation.
        $request->session()->regenerate();

        Log::info('Anmeldung erfolgreich', ['user_id' => $user->id]);

        // Zurueck dorthin, wo der Benutzer hinwollte. Laravel speichert das Ziel
        // in der Session, nicht in der URL — eine offene Weiterleitung ist so
        // nicht moeglich.
        return redirect()->intended('/dashboard');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (! $this->isFirstSetup()) {
            return redirect('/login');
        }

        return view('pages.register');
    }

    public function register(Request $request): RedirectResponse
    {
        // Die Selbstregistrierung existiert nur fuer die Ersteinrichtung.
        //
        // Wichtig: Diese Pruefung steht HIER und nicht nur im Template. Sonst
        // liesse sich das Formular einfach von Hand abschicken.
        if (! $this->isFirstSetup()) {
            return redirect('/login');
        }

        // Das erste Konto wird Administrator — sonst kaeme niemand an die
        // Benutzerverwaltung heran.
        $user = $this->users->create($request->only('name', 'email', 'password'), Role::Admin);

        Auth::login($user);
        $request->session()->regenerate();

        Log::info('Ersteinrichtung abgeschlossen', ['user_id' => $user->id]);

        return redirect('/dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /** `true`, solange die Installation noch kein einziges Konto hat. */
    private function isFirstSetup(): bool
    {
        return $this->users->count() === 0;
    }

    /** Wartezeit so ausdruecken, wie ein Mensch sie liest. */
    public static function waitText(int $seconds): string
    {
        return $seconds <= 90
            ? "in {$seconds} Sekunden"
            : 'in '.(int) ceil($seconds / 60).' Minuten';
    }
}
