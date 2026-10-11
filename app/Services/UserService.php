<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Benutzerverwaltung.
 *
 * Alles, was eine Regel ist — E-Mail eindeutig, Passwort lang genug, der
 * letzte Administrator bleibt —, steht hier und nirgends sonst.
 */
class UserService
{
    /**
     * Mindestlaenge fuer Passwoerter. Bewusst laengenbasiert statt
     * Zeichenklassen-Regeln — das entspricht der aktuellen NIST-Empfehlung.
     * Gesetzt in App\Providers\AppServiceProvider ueber Password::defaults().
     */
    public const MIN_PASSWORD_LENGTH = 12;

    /**
     * Legt einen Benutzer an. E-Mail wird normalisiert, Passwort mit Argon2id gehasht.
     *
     * @param  array<string, mixed>  $input  name, email, password
     *
     * @throws ValidationException
     */
    public function create(array $input, Role $role): User
    {
        $input['email'] = $this->normalizeEmail($input['email'] ?? null);

        /** @var array{name: string, email: string, password: string} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::defaults()],
        ])->validate();

        return User::create([...$data, 'role' => $role, 'is_active' => true]);
    }

    /**
     * Prueft E-Mail und Passwort. `null` bei jedem Fehlschlag — bewusst ohne
     * Unterscheidung, damit nicht erkennbar wird, ob es das Konto gibt.
     *
     * Gesperrte Konten kommen hier zurueck; ob `is_active` stimmt, prueft der
     * Aufrufer. Erst nach dem richtigen Passwort, damit die Meldung „gesperrt"
     * nichts ueber fremde Konten verraet.
     */
    public function findByCredentials(string $email, string $password): ?User
    {
        $user = User::where('email', $this->normalizeEmail($email))->first();

        if ($user === null) {
            // Trotzdem hashen, damit die Antwortzeit nicht verraet, dass das Konto fehlt.
            Hash::make($password);

            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }

    /**
     * Alle Benutzer, wahlweise auf einen Suchbegriff eingeschraenkt.
     *
     * Gesucht wird in Name und E-Mail, Gross-/Kleinschreibung egal. Bewusst
     * ohne Blaettern: Bei der Groessenordnung, fuer die dieses Kit gedacht ist,
     * kostet ein vollstaendiger Durchlauf nichts. Wird die Liste lang, hier
     * ->paginate() nehmen und in der Oberflaeche {{ $users->links() }}.
     *
     * @return Collection<int, User>
     */
    public function list(?string $search = null): Collection
    {
        $search = trim((string) $search);

        return User::query()
            ->when($search !== '', function ($query) use ($search) {
                // Platzhalter von LIKE entschaerfen, damit ein eingegebenes `%`
                // auch wirklich nach einem Prozentzeichen sucht.
                $pattern = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($q) => $q
                    ->where('name', 'ilike', $pattern)
                    ->orWhere('email', 'ilike', $pattern));
            })
            ->latest()
            ->get();
    }

    public function count(): int
    {
        return User::count();
    }

    /** Anzahl der Konten, die sich anmelden UND verwalten koennen. */
    public function countActiveAdmins(): int
    {
        return User::where('role', Role::Admin)->where('is_active', true)->count();
    }

    public function isLastActiveAdmin(User $user): bool
    {
        return $user->role === Role::Admin && $user->is_active && $this->countActiveAdmins() <= 1;
    }

    /**
     * Aendert Name und E-Mail.
     *
     * @param  array<string, mixed>  $input  name, email
     *
     * @throws ValidationException
     */
    public function updateProfile(User $user, array $input): User
    {
        $input['email'] = $this->normalizeEmail($input['email'] ?? null);

        /** @var array{name: string, email: string} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ])->validate();

        $user->update($data);

        return $user;
    }

    /** @throws ValidationException */
    public function setRole(User $user, Role $role): User
    {
        // Sperre, damit zwei gleichzeitige Degradierungen nicht beide den
        // „noch ein anderer Admin da"-Check bestehen.
        return DB::transaction(function () use ($user, $role) {
            $this->lockAdmins();
            if ($role !== Role::Admin) {
                $this->ensureNotLastAdmin($user->refresh());
            }
            $user->update(['role' => $role]);

            return $user;
        });
    }

    /** @throws ValidationException */
    public function setActive(User $user, bool $active): User
    {
        return DB::transaction(function () use ($user, $active) {
            $this->lockAdmins();
            if (! $active) {
                $this->ensureNotLastAdmin($user->refresh());
            }
            $user->update(['is_active' => $active]);

            return $user;
        });
    }

    /**
     * Loescht ein Konto samt aller zugehoerigen Daten (ON DELETE CASCADE).
     *
     * Ausgestellte App-Tokens ueberleben: Sie gehoeren der Installation, nicht
     * der Person — `api_tokens.created_by` wird nur auf NULL gesetzt.
     *
     * @throws ValidationException
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user) {
            $this->lockAdmins();
            $this->ensureNotLastAdmin($user->refresh());
            $user->delete();
        });
    }

    /** @throws ValidationException */
    public function changePassword(User $user, string $newPassword): void
    {
        Validator::make(['password' => $newPassword], [
            'password' => ['required', 'string', Password::defaults()],
        ])->validate();

        $user->update(['password' => $newPassword]);
    }

    /**
     * Verhindert, dass die Installation ohne handlungsfaehigen Administrator
     * zurueckbleibt. Wird vor Loeschen, Sperren und Degradieren geprueft.
     *
     * @throws ValidationException
     */
    private function ensureNotLastAdmin(User $user): void
    {
        if ($this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => 'Das ist der letzte aktive Administrator. Erst einen weiteren ernennen.',
            ]);
        }
    }

    private function lockAdmins(): void
    {
        User::where('role', Role::Admin)->lockForUpdate()->get(['id']);
    }

    private function normalizeEmail(mixed $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
