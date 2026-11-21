<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use App\Support\PasswordWords;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
            // The first account runs the installation: an adult (eltern-konten.md).
            // Everybody else gets their age from the admin at approval.
            $user->forceFill(['approved_at' => $first ? now() : null, 'is_adult' => $first])->save();

            return $user;
        });
    }

    /**
     * An administrator creates an account. It is usable right away. Under 18
     * it needs at least one parent (docs/specs/eltern-konten.md).
     *
     * @param  array<string, mixed>  $input  name, username, password, age ("minor"/"adult"), parents (usernames)
     *
     * @throws ValidationException
     */
    public function create(array $input, Role $role, ?User $createdBy = null): User
    {
        $data = $this->validateNewAccount($input, confirmPassword: false);
        $family = $this->validateFamily($input);

        return DB::transaction(function () use ($data, $role, $createdBy, $family) {
            $user = new User([...$data, 'role' => $role, 'is_active' => true]);
            $user->forceFill(['approved_at' => now(), 'approved_by' => $createdBy?->id, 'is_adult' => $family['adult']])->save();
            $this->link($user, $family['guardians'], $createdBy);

            return $user;
        });
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
            // For "unter 18" / "Elternteil fehlt" in the list (eltern-konten.md).
            ->withCount(['guardians' => fn ($q) => $q->whereNotNull('guardian_links.confirmed_at')])
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

    /**
     * Makes a waiting account usable, with its age and — under 18 — its
     * parents (eltern-konten.md). Approving twice changes nothing.
     *
     * @param  array<string, mixed>  $input  age ("minor"/"adult"), parents (usernames)
     *
     * @throws ValidationException
     */
    public function approve(User $user, User $approvedBy, array $input): User
    {
        if ($user->isApproved()) {
            return $user;
        }
        $family = $this->validateFamily($input, $user);

        return DB::transaction(function () use ($user, $approvedBy, $family) {
            $user->forceFill(['approved_at' => now(), 'approved_by' => $approvedBy->id, 'is_adult' => $family['adult']])->save();
            $this->link($user, $family['guardians'], $approvedBy);

            return $user;
        });
    }

    /**
     * "unter 18" / "ab 18" for an existing account. To 18 or older the links
     * to the parents end; under 18 needs parents (given or kept) and no own
     * children. Decks shared before become "bitte durchsehen".
     *
     * @param  array<string, mixed>  $input  age, parents (only for "minor")
     *
     * @throws ValidationException
     */
    public function setAge(User $user, array $input, User $by): User
    {
        $family = $this->validateFamily($input, $user, required: $this->confirmedCount($user) === 0);

        return DB::transaction(function () use ($user, $by, $family) {
            User::whereKey($user->id)->lockForUpdate()->first();
            if ($family['adult']) {
                $this->endParentAccess($user, DB::table('guardian_links')->where('child_id', $user->id)->pluck('guardian_id')->all());
                DB::table('guardian_links')->where('child_id', $user->id)->delete();
                $user->forceFill(['is_adult' => true])->save();
                // Of age, the account decides about its shares itself.
                DB::table('decks')->where('user_id', $user->id)->update(['share_review_pending' => false]);

                return $user;
            }
            $this->ensureNotGuardian($user);
            $user->forceFill(['is_adult' => false])->save();
            $this->link($user, $family['guardians'], $by);
            if ($this->guardianCount($user) > GuardianService::MAX_GUARDIANS) {
                throw ValidationException::withMessages(['parents' => 'Höchstens '.GuardianService::MAX_GUARDIANS.' Eltern.']);
            }
            // What the account shared as an adult: a parent looks once.
            DB::table('decks')->where('user_id', $user->id)
                ->where(fn ($q) => $q->where('is_shared', true)
                    ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('deck_shares')->whereColumn('deck_shares.deck_id', 'decks.id')->whereNull('deck_shares.declined_at')))
                ->update(['share_review_pending' => true]);

            return $user;
        });
    }

    /**
     * Adds a parent to an account under 18 (admin only). A link from the
     * time of codes ("nur zuschauen") is confirmed instead.
     *
     * @throws ValidationException
     */
    public function addGuardian(User $child, mixed $username, User $by): User
    {
        if ($child->is_adult) {
            throw ValidationException::withMessages(['parents' => 'Eltern gibt es nur für Konten unter 18.']);
        }
        $guardian = $this->findAdult(self::normalizeUsername(is_string($username) ? $username : ''));
        if ($guardian === null || $guardian->is($child)) {
            throw ValidationException::withMessages(['parents' => 'Kein freigegebenes Konto ab 18 mit diesem Benutzernamen.']);
        }

        return DB::transaction(function () use ($child, $guardian, $by) {
            // Re-read under the lock: the age may have changed meanwhile.
            if (User::whereKey($child->id)->lockForUpdate()->value('is_adult')) {
                throw ValidationException::withMessages(['parents' => 'Eltern gibt es nur für Konten unter 18.']);
            }
            $this->ensureNotGuardian($child, 'parents');
            $link = DB::table('guardian_links')->where('child_id', $child->id)->where('guardian_id', $guardian->id)->first();
            if ($link !== null && $link->confirmed_at !== null) {
                throw ValidationException::withMessages(['parents' => "{$guardian->name} ist schon eingetragen."]);
            }
            if ($link === null && $this->guardianCount($child) >= GuardianService::MAX_GUARDIANS) {
                throw ValidationException::withMessages(['parents' => 'Höchstens '.GuardianService::MAX_GUARDIANS.' Eltern.']);
            }
            $this->link($child, [$guardian], $by);

            return $guardian;
        });
    }

    /**
     * "Bestätigen" for a link from the time of codes: from then on the
     * parent may act for the child (admin only).
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<User>
     */
    public function confirmGuardian(User $child, string $guardianId, User $by): User
    {
        return $this->addGuardian($child, $this->linkedGuardian($child, $guardianId)->username, $by);
    }

    /**
     * Removes a parent (admin only); the last confirmed one stays.
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<User>
     */
    public function removeGuardian(User $child, string $guardianId): User
    {
        $guardian = $this->linkedGuardian($child, $guardianId);

        return DB::transaction(function () use ($child, $guardian) {
            User::whereKey($child->id)->lockForUpdate()->first();
            $confirmed = DB::table('guardian_links')->where('child_id', $child->id)->where('guardian_id', $guardian->id)->whereNotNull('confirmed_at')->exists();
            if ($confirmed && ! $child->is_adult && $this->confirmedCount($child) <= 1) {
                throw ValidationException::withMessages(['parents' => 'Das letzte Elternteil lässt sich nicht entfernen. Erst ein anderes eintragen oder das Konto auf „ab 18“ stellen.']);
            }
            DB::table('guardian_links')->where('child_id', $child->id)->where('guardian_id', $guardian->id)->delete();
            $this->endParentAccess($child, [$guardian->id]);

            return $guardian;
        });
    }

    /** Accounts under 18 without a parent who may act for them. */
    public function minorsWithoutGuardian(): int
    {
        return User::where('is_adult', false)->whereNotNull('approved_at')
            ->whereNotIn('id', DB::table('guardian_links')->whereNotNull('confirmed_at')->select('child_id'))
            ->count();
    }

    /** @throws ModelNotFoundException<User> */
    private function linkedGuardian(User $child, string $guardianId): User
    {
        $guardian = Str::isUuid($guardianId)
            ? User::whereKey($guardianId)->whereIn('id', DB::table('guardian_links')->where('child_id', $child->id)->select('guardian_id'))->first()
            : null;
        if ($guardian === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$guardianId]);
        }

        return $guardian;
    }

    /**
     * No chains: a parent of others cannot become a child.
     *
     * @throws ValidationException
     */
    private function ensureNotGuardian(User $user, string $field = 'age'): void
    {
        if (DB::table('guardian_links')->where('guardian_id', $user->id)->exists()) {
            throw ValidationException::withMessages([$field => "{$user->name} ist für andere als Elternteil eingetragen. Erst diese Zuordnung lösen."]);
        }
    }

    /**
     * When a link ends, so does what came with it: decks of the child that a
     * parent shared with themselves (progress stays, as after any unshare).
     *
     * @param  list<string>  $guardianIds
     */
    private function endParentAccess(User $child, array $guardianIds): void
    {
        if ($guardianIds === []) {
            return;
        }
        $decks = DB::table('decks')->where('user_id', $child->id);
        DB::table('deck_shares')->whereIn('user_id', $guardianIds)->whereIn('deck_id', (clone $decks)->select('id'))->delete();
        DB::table('deck_follows')->whereIn('user_id', $guardianIds)->whereIn('deck_id', (clone $decks)->where('is_shared', false)->select('id'))->delete();
    }

    /** All links, also those from the time of codes. */
    private function guardianCount(User $child): int
    {
        return DB::table('guardian_links')->where('child_id', $child->id)->count();
    }

    /** Parents an admin assigned or confirmed. */
    private function confirmedCount(User $child): int
    {
        return DB::table('guardian_links')->where('child_id', $child->id)->whereNotNull('confirmed_at')->count();
    }

    /**
     * Age and parents from an admin form. Under 18 needs one to four parents
     * of 18 or older (unless `$required` is off because parents are kept).
     *
     * @param  array<string, mixed>  $input
     * @return array{adult: bool, guardians: list<User>}
     *
     * @throws ValidationException
     */
    private function validateFamily(array $input, ?User $self = null, bool $required = true): array
    {
        $age = $input['age'] ?? null;
        if (! in_array($age, ['minor', 'adult'], true)) {
            throw ValidationException::withMessages(['age' => 'Bitte „unter 18“ oder „ab 18“ wählen.']);
        }
        if ($age === 'adult') {
            return ['adult' => true, 'guardians' => []];
        }

        $raw = is_string($input['parents'] ?? null) ? $input['parents'] : '';
        $names = array_values(array_unique(array_filter(array_map(
            fn (string $name) => self::normalizeUsername($name),
            preg_split('/[\s,;]+/u', $raw) ?: [],
        ))));
        if ($names === [] && $required) {
            throw ValidationException::withMessages(['parents' => 'Für ein Konto unter 18 braucht es mindestens ein Elternteil.']);
        }
        if (count($names) > GuardianService::MAX_GUARDIANS) {
            throw ValidationException::withMessages(['parents' => 'Höchstens '.GuardianService::MAX_GUARDIANS.' Eltern.']);
        }
        $guardians = [];
        foreach ($names as $name) {
            $guardian = $this->findAdult($name);
            if ($guardian === null || ($self !== null && $guardian->is($self))) {
                throw ValidationException::withMessages(['parents' => "Kein freigegebenes Konto ab 18 mit dem Benutzernamen „{$name}“."]);
            }
            $guardians[] = $guardian;
        }

        return ['adult' => false, 'guardians' => $guardians];
    }

    /** An approved, active account of 18 or older — the only kind that can be a parent. */
    private function findAdult(string $username): ?User
    {
        return $username === '' ? null : User::where('username', $username)
            ->where('is_adult', true)->where('is_active', true)->whereNotNull('approved_at')
            ->first();
    }

    /** @param  list<User>  $guardians */
    private function link(User $child, array $guardians, ?User $by): void
    {
        foreach ($guardians as $guardian) {
            DB::table('guardian_links')->upsert(
                ['guardian_id' => $guardian->id, 'child_id' => $child->id, 'linked_by' => $by?->id, 'created_at' => now(), 'confirmed_at' => now()],
                ['guardian_id', 'child_id'],
                ['linked_by', 'confirmed_at'],
            );
        }
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
     * Notes a sign-in for the user management. Not as a change of the
     * account: updated_at stays.
     */
    public function recordLogin(User $user): void
    {
        User::withoutTimestamps(fn () => $user->forceFill(['last_login_at' => now()])->save());
    }

    /**
     * Last request of the account in any session, for the user management;
     * null without one (or without database sessions).
     */
    public function lastActivity(User $user): ?Carbon
    {
        if (config('session.driver') !== 'database') {
            return null;
        }
        $at = DB::table(config('session.table'))->where('user_id', $user->id)->max('last_activity');

        return $at === null ? null : Carbon::createFromTimestamp((int) $at);
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
