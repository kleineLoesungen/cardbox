<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeckService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Benutzerverwaltung. Nur fuer Administratoren — erzwungen durch die
 * Routengruppe `role:admin` in routes/web.php.
 *
 * Alle Aenderungen laufen als normales Formular mit anschliessender
 * Weiterleitung (Post/Redirect/Get). Ein Neuladen loest dadurch keine zweite
 * Aenderung aus, und Fehler kommen mit erhaltenen Eingaben zurueck.
 */
class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        // No scheduler in the container: stale registrations are cleaned up
        // whenever an admin looks at the list.
        $this->users->pruneUnapproved();
        $this->users->pruneGuestSessions();

        return view('admin.users', [
            'users' => $this->users->list($search),
            'pending' => $this->users->pending(),
            'total' => $this->users->countApproved(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = $this->roleFrom($request);
        $created = $this->users->create($request->only('name', 'username', 'password'), $role, $request->user());

        Log::info('Benutzer angelegt', ['by' => $request->user()->id, 'user_id' => $created->id, 'role' => $role->value]);

        return redirect("/admin/users/{$created->id}")->with('saved', true);
    }

    public function show(User $user, DeckService $decks): View
    {
        return view('admin.user', [
            'edited' => $user,
            'deckSummary' => $decks->ownerSummary($user),
            'decks' => $decks->listForAdmin($user),
            // Steuert nur, welche Bedienelemente die Seite sperrt. Die eigentliche
            // Absicherung liegt im UserService, nicht in dieser Anzeige.
            'isSelf' => $user->is(request()->user()),
            'isLastAdmin' => $this->users->isLastActiveAdmin($user),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $role = $this->roleFrom($request);
        $active = $request->boolean('is_active');

        // Sonst koennte sich ein Administrator selbst degradieren oder aussperren.
        if ($user->is($request->user()) && ($role !== Role::Admin || ! $active)) {
            throw ValidationException::withMessages([
                'role' => 'Die eigene Rolle und der eigene Kontostatus lassen sich hier nicht ändern.',
            ]);
        }

        // Eine Transaktion: Scheitert die Rollenaenderung, bleibt auch der
        // geaenderte Name ungespeichert — sonst waere halb gespeichert.
        DB::transaction(function () use ($request, $user, $role, $active) {
            $this->users->updateProfile($user, $request->only('name', 'username'));

            // Beide Aufrufe pruefen selbst, ob dadurch der letzte Administrator verschwaende.
            if ($user->role !== $role) {
                $this->users->setRole($user, $role);
            }
            if ($user->is_active !== $active) {
                $this->users->setActive($user, $active);
            }
        });

        Log::info('Benutzer geändert', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return redirect("/admin/users/{$user->id}")->with('saved', true);
    }

    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $this->users->changePassword($user, (string) $request->input('password'));

        Log::info('Passwort zurückgesetzt', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return redirect("/admin/users/{$user->id}")->with('saved', true);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['role' => 'Das eigene Konto lässt sich nicht löschen.']);
        }

        $this->users->delete($user);

        Log::info('Benutzer gelöscht', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return redirect('/admin/users')->with('toast', ['kind' => 'success', 'message' => 'Konto gelöscht.']);
    }

    /** Hands decks of this account to another one (issue #12). */
    public function transferDecks(Request $request, User $user, DeckService $decks): RedirectResponse
    {
        ['to' => $to, 'decks' => $moved] = $decks->transfer($user, $request->input('decks'), $request->input('to_username'));

        foreach ($moved as $deck) {
            Log::info('Stapel übertragen', ['by' => $request->user()->id, 'deck_id' => $deck->id, 'from' => $user->id, 'to' => $to->id]);
        }

        $count = $moved->count();

        return redirect("/admin/users/{$user->id}")->with('toast', [
            'kind' => 'success',
            'message' => ($count === 1 ? '1 Stapel' : "{$count} Stapel")." an {$to->username} übertragen.",
        ]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        $this->users->approve($user, $request->user());

        Log::info('Konto freigegeben', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return redirect('/admin/users')->with('toast', ['kind' => 'success', 'message' => "{$user->username} ist freigegeben."]);
    }

    public function reject(Request $request, User $user): RedirectResponse
    {
        $this->users->reject($user);

        Log::info('Registrierung abgelehnt', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return redirect('/admin/users')->with('toast', ['kind' => 'success', 'message' => 'Registrierung abgelehnt.']);
    }

    /**
     * htmx: proposes a username for the typed name. Leaves a username the
     * admin already entered untouched.
     */
    public function suggestUsername(Request $request): View
    {
        $current = trim((string) $request->query('username', ''));
        $name = trim((string) $request->query('name', ''));

        return view('partials.username-field', [
            'value' => $current !== '' || $name === '' ? $current : $this->users->suggestUsername($name),
        ]);
    }

    /** @throws ValidationException */
    private function roleFrom(Request $request): Role
    {
        $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        return Role::from((string) $request->input('role'));
    }
}
