<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DeckService;
use App\Services\GuardianService;
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
            // After the switch to parent accounts: who still needs a parent (eltern-konten.md).
            'minorsWithoutGuardian' => $this->users->minorsWithoutGuardian(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = $this->roleFrom($request);
        $created = $this->users->create($request->only('name', 'username', 'password', 'age', 'parents'), $role, $request->user());

        Log::info('Benutzer angelegt', ['by' => $request->user()->id, 'user_id' => $created->id, 'role' => $role->value]);

        return redirect("/admin/users/{$created->id}")->with('saved', true);
    }

    public function show(User $user, DeckService $decks, GuardianService $guardians): View
    {
        return view('admin.user', [
            'edited' => $user,
            // Age and parents (eltern-konten.md).
            'guardians' => $guardians->guardiansOf($user),
            'children' => $guardians->childrenOf($user),
            'reviewPending' => $decks->reviewPending($user),
            'lastActivity' => $this->users->lastActivity($user),
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
        try {
            $this->users->approve($user, $request->user(), $request->only('age', 'parents'));
        } catch (ValidationException $e) {
            throw $e->errorBag('approve-'.$user->id)->redirectTo('/admin/users');
        }

        Log::info('Konto freigegeben', ['by' => $request->user()->id, 'user_id' => $user->id]);

        return redirect('/admin/users')->with('toast', ['kind' => 'success', 'message' => "{$user->username} ist freigegeben."]);
    }

    /** "unter 18" / "ab 18" (eltern-konten.md, B). */
    public function setAge(Request $request, User $user): RedirectResponse
    {
        try {
            $this->users->setAge($user, $request->only('age', 'parents'), $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('family')->redirectTo("/admin/users/{$user->id}");
        }
        Log::info('Alter geändert', ['by' => $request->user()->id, 'user_id' => $user->id, 'adult' => $user->is_adult]);

        return redirect("/admin/users/{$user->id}")->with('toast', ['kind' => 'success', 'message' => $user->is_adult ? "{$user->name} gilt jetzt als ab 18." : "{$user->name} gilt jetzt als unter 18."]);
    }

    public function addGuardian(Request $request, User $user): RedirectResponse
    {
        try {
            $guardian = $this->users->addGuardian($user, $request->input('username'), $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('family')->redirectTo("/admin/users/{$user->id}");
        }

        return redirect("/admin/users/{$user->id}")->with('toast', ['kind' => 'success', 'message' => "{$guardian->name} ist jetzt Elternteil von {$user->name}."]);
    }

    /** "Bestätigen" for a link from the time of codes (eltern-konten.md). */
    public function confirmGuardian(Request $request, User $user, string $guardian): RedirectResponse
    {
        try {
            $confirmed = $this->users->confirmGuardian($user, $guardian, $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('family')->redirectTo("/admin/users/{$user->id}");
        }

        return redirect("/admin/users/{$user->id}")->with('toast', ['kind' => 'success', 'message' => "{$confirmed->name} ist jetzt Elternteil von {$user->name}."]);
    }

    public function removeGuardian(Request $request, User $user, string $guardian): RedirectResponse
    {
        try {
            $removed = $this->users->removeGuardian($user, $guardian);
        } catch (ValidationException $e) {
            throw $e->errorBag('family')->redirectTo("/admin/users/{$user->id}");
        }

        return redirect("/admin/users/{$user->id}")->with('toast', ['kind' => 'success', 'message' => "{$removed->name} ist nicht mehr Elternteil von {$user->name}."]);
    }

    /** "So lassen" / "Privat machen" for a child without parents (eltern-konten.md, F). */
    public function reviewDeck(Request $request, User $user, string $deck, DeckService $decks): RedirectResponse
    {
        $model = $decks->reviewAsAdmin($user, $deck, $request->boolean('keep'));

        return redirect("/admin/users/{$user->id}")->with('toast', ['kind' => 'success', 'message' => $request->boolean('keep') ? "„{$model->name}“ bleibt geteilt." : "„{$model->name}“ ist jetzt privat."]);
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
