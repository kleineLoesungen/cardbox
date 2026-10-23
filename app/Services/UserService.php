<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Support\PasswordWords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * User management.
 *
 * Every rule — usernames are unique and well-formed, passwords are long
 * enough, the last administrator stays, self-registered accounts wait for
 * approval — lives here and nowhere else.
 */
class UserService
{
    /**
     * Mindestlaenge fuer Passwoerter. Bewusst laengenbasiert statt
     * Zeichenklassen-Regeln — das entspricht der aktuellen NIST-Empfehlung.
     * Gesetzt in App\Providers\AppServiceProvider ueber Password::defaults().
     */
    public const MIN_PASSWORD_LENGTH = 12;

    /** Lower-case letter first, then 2–29 of a–z, digits, dot, dash, underscore. */
    public const USERNAME_PATTERN = '/^[a-z][a-z0-9._-]{2,29}$/';

    public const USERNAME_MAX_LENGTH = 30;

    /** Unapproved self-registrations are deleted after this many days. */
    public const PENDING_RETENTION_DAYS = 30;

    /**
     * Self-registration via /register.
     *
     * The very first account of an installation becomes an approved
     * administrator — otherwise nobody could ever reach the user management.
     * Every later account is a plain user waiting for approval. The decision
     * is made here, so a hand-crafted form cannot skip the queue.
     *
     * @param  array<string, mixed>  $input  name, username, password, password_confirmation
     *
     * @throws ValidationException
     */
    public function register(array $input): User
    {
        $data = $this->validateNewAccount($input, confirmPassword: true);

        return DB::transaction(function () use ($data) {
            // Serialises concurrent registrations on an empty installation so
            // that two people cannot both become the first administrator.
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32('users.register')]);

            $first = User::count() === 0;

            $user = new User([...$data, 'role' => $first ? Role::Admin : Role::User, 'is_active' => true]);
            $user->forceFill(['approved_at' => $first ? now() : null])->save();

            return $user;
        });
    }

    /**
     * An administrator creates an account. It is usable right away.
     *
     * @param  array<string, mixed>  $input  name, username, password
     *
     * @throws ValidationException
     */
    public function create(array $input, Role $role, ?User $createdBy = null): User
    {
        $data = $this->validateNewAccount($input, confirmPassword: false);

        $user = new User([...$data, 'role' => $role, 'is_active' => true]);
        $user->forceFill(['approved_at' => now(), 'approved_by' => $createdBy?->id])->save();

        return $user;
    }

    /**
     * Checks username and password. `null` on every failure — deliberately
     * without distinction, so nobody can tell whether the account exists.
     *
     * Locked and unapproved accounts are returned; the caller checks
     * `is_active` and `isApproved()` only after the correct password, so
     * those messages reveal nothing about other people's accounts.
     */
    public function findByCredentials(string $username, string $password): ?User
    {
        $user = User::where('username', self::normalizeUsername($username))->first();

        if ($user === null) {
            // Hash anyway so the response time does not reveal a missing account.
            Hash::make($password);

            return null;
        }

        return Hash::check($password, $user->password) ? $user : null;
    }

    /**
     * All approved users, optionally narrowed by a search term.
     *
     * Searches name and username, case-insensitive. Deliberately without
     * pagination: at the scale Cardbox targets a full scan costs nothing.
     * If the list grows long, use ->paginate() here and {{ $users->links() }}
     * in the view.
     *
     * @return Collection<int, User>
     */
    public function list(?string $search = null): Collection
    {
        $search = trim((string) $search);

        return User::query()
            ->whereNotNull('approved_at')
            ->when($search !== '', function ($query) use ($search) {
                // Escape LIKE wildcards so a typed `%` really searches for a percent sign.
                $pattern = '%'.addcslashes($search, '\\%_').'%';
                $query->where(fn ($q) => $q
                    ->where('name', 'ilike', $pattern)
                    ->orWhere('username', 'ilike', $pattern));
            })
            ->latest()
            ->get();
    }

    /**
     * Self-registered accounts waiting for approval, oldest first.
     *
     * @return Collection<int, User>
     */
    public function pending(): Collection
    {
        return User::whereNull('approved_at')->oldest()->get();
    }

    public function pendingCount(): int
    {
        return User::whereNull('approved_at')->count();
    }

    /** Counts approved accounts only. "Is there any account?" is isEmpty(). */
    public function countApproved(): int
    {
        return User::whereNotNull('approved_at')->count();
    }

    /** `true` while the installation has no account at all. */
    public function isEmpty(): bool
    {
        return ! User::query()->exists();
    }

    /** Anzahl der Konten, die sich anmelden UND verwalten koennen. */
    public function countActiveAdmins(): int
    {
        return User::where('role', Role::Admin)
            ->where('is_active', true)
            ->whereNotNull('approved_at')
            ->count();
    }

    public function isLastActiveAdmin(User $user): bool
    {
        return $user->role === Role::Admin && $user->is_active && $this->countActiveAdmins() <= 1;
    }

    /**
     * Changes the display name and — only when the key is present — the
     * username. /account passes the name alone, so people cannot rename
     * their own login; the admin form passes both.
     *
     * @param  array<string, mixed>  $input  name, username (optional)
     *
     * @throws ValidationException
     */
    public function updateProfile(User $user, array $input): User
    {
        $rules = ['name' => ['required', 'string', 'max:255']];

        if (array_key_exists('username', $input)) {
            $input['username'] = self::normalizeUsername($input['username']);
            $rules['username'] = $this->usernameRules($user);
        }

        /** @var array{name: string, username?: string} $data */
        $data = Validator::make($input, $rules, $this->usernameMessages($input, $user))->validate();

        $user->update($data);

        return $user;
    }

    /** Makes a waiting account usable. Approving twice changes nothing. */
    public function approve(User $user, User $approvedBy): User
    {
        if (! $user->isApproved()) {
            $user->forceFill(['approved_at' => now(), 'approved_by' => $approvedBy->id])->save();
        }

        return $user;
    }

    /**
     * Rejects a waiting account by deleting it.
     *
     * @throws ValidationException
     */
    public function reject(User $user): void
    {
        if ($user->isApproved()) {
            throw ValidationException::withMessages([
                'user' => 'Nur Konten, die auf Freigabe warten, lassen sich ablehnen.',
            ]);
        }

        $user->delete();
    }

    /**
     * Deletes self-registrations nobody approved within the retention
     * period. Called whenever the user management is opened — there is no
     * scheduler in the container.
     *
     * @return int Number of deleted accounts
     */
    public function pruneUnapproved(): int
    {
        return User::whereNull('approved_at')
            ->where('created_at', '<', now()->subDays(self::PENDING_RETENTION_DAYS))
            ->delete();
    }

    /**
     * Deletes sessions of nobody signed in (login page, guests, bots) after a
     * day: sessions last 30 days (issue #24), these need not. At most once an
     * hour; called from the login page and the user management.
     *
     * @return int Number of deleted sessions
     */
    public function pruneGuestSessions(): int
    {
        if (config('session.driver') !== 'database' || ! Cache::add('guest-session-prune', true, 3600)) {
            return 0;
        }

        return DB::table(config('session.table'))
            ->whereNull('user_id')
            ->where('last_activity', '<', now()->subDay()->getTimestamp())
            ->delete();
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
     * Derives a free username from a display name:
     * "Lena Müller" → "lena.mueller", or "lena.mueller2" if taken.
     */
    public function suggestUsername(string $name): string
    {
        $base = mb_strtolower(trim($name));
        $base = strtr($base, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
        $base = (string) preg_replace('/\s+/u', '.', $base);
        $base = (string) preg_replace('/[^a-z0-9._-]/', '', $base);
        $base = (string) preg_replace('/^[^a-z]+/', '', $base);
        $base = trim(substr($base, 0, self::USERNAME_MAX_LENGTH - 3), '.-_');

        if (strlen($base) < 3) {
            $base = 'user';
        }

        $taken = User::where('username', 'like', addcslashes($base, '\\%_').'%')
            ->pluck('username')
            ->flip();

        if (! $taken->has($base)) {
            return $base;
        }

        for ($n = 2; ; $n++) {
            if (! $taken->has($base.$n)) {
                return $base.$n;
            }
        }
    }

    /** A memorable password such as "Wolke-Tiger-Apfel-47". */
    public function suggestPassword(): string
    {
        $words = PasswordWords::WORDS;
        $picked = [];
        for ($i = 0; $i < 3; $i++) {
            $picked[] = $words[random_int(0, count($words) - 1)];
        }

        return implode('-', $picked).'-'.random_int(10, 99);
    }

    public static function normalizeUsername(mixed $username): string
    {
        return mb_strtolower(trim((string) $username));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{name: string, username: string, password: string}
     *
     * @throws ValidationException
     */
    private function validateNewAccount(array $input, bool $confirmPassword): array
    {
        $input['username'] = self::normalizeUsername($input['username'] ?? null);

        $password = ['required', 'string', Password::defaults()];
        if ($confirmPassword) {
            $password[] = 'confirmed';
        }

        /** @var array{name: string, username: string, password: string} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'username' => $this->usernameRules(),
            'password' => $password,
        ], $this->usernameMessages($input))->validate();

        return ['name' => $data['name'], 'username' => $data['username'], 'password' => $data['password']];
    }

    /** @return list<mixed> */
    private function usernameRules(?User $ignore = null): array
    {
        $unique = Rule::unique('users', 'username');

        return [
            'required',
            'string',
            'max:'.self::USERNAME_MAX_LENGTH,
            'regex:'.self::USERNAME_PATTERN,
            $ignore === null ? $unique : $unique->ignore($ignore->id),
        ];
    }

    /**
     * The "taken" message carries a concrete free alternative.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function usernameMessages(array $input, ?User $ignore = null): array
    {
        $username = (string) ($input['username'] ?? '');

        $taken = $username !== '' && User::where('username', $username)
            ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->id))
            ->exists();

        return $taken
            ? ['username.unique' => 'Diesen Benutzernamen gibt es schon. Versuch zum Beispiel '.$this->suggestUsername($username).'.']
            : [];
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
}
