<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
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

        return view('admin.users', [
            'users' => $this->users->list($search),
            'total' => $this->users->count(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $role = $this->roleFrom($request);
        $created = $this->users->create($request->only('name', 'email', 'password'), $role);

        Log::info('Benutzer angelegt', ['by' => $request->user()->id, 'user_id' => $created->id, 'role' => $role->value]);

        return redirect("/admin/users/{$created->id}")->with('saved', true);
    }

    public function show(User $user): View
    {
        return view('admin.user', [
            'edited' => $user,
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
            $this->users->updateProfile($user, $request->only('name', 'email'));

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

    /** @throws ValidationException */
    private function roleFrom(Request $request): Role
    {
        $request->validate(['role' => ['required', Rule::enum(Role::class)]]);

        return Role::from((string) $request->input('role'));
    }
}
