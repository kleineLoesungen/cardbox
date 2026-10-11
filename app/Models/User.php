<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Ein Benutzerkonto.
 *
 * Das Modell beschreibt Daten und kleine Ableitungen daraus (Initialen,
 * Rollenvergleich). Regeln wie „der letzte Administrator darf nicht gehen"
 * stehen in App\Services\UserService, nicht hier.
 *
 * @property string $id
 * @property string $email
 * @property string $name
 * @property string $password
 * @property Role $role
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids;

    /** Kein "Angemeldet bleiben" — die Spalte gibt es nicht. */
    protected $rememberTokenName = '';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * E-Mail-Adressen immer kleingeschrieben und ohne Leerraum speichern.
     *
     * @return Attribute<never, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtolower(trim($value)),
        );
    }

    /** @return HasMany<Note, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** `true`, wenn der Benutzer mindestens die Rolle `$required` hat. */
    public function hasRole(Role $required): bool
    {
        return $this->role->atLeast($required);
    }

    /** Initialen fuer den Avatar in der Kopfzeile. */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(implode('', array_map(
            fn (string $word) => mb_substr($word, 0, 1),
            array_slice($words, 0, 2),
        )));
    }
}
