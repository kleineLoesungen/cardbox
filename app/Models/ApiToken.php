<?php

namespace App\Models;

use App\Auth\TokenFormat;
use App\Enums\Scope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * App-Token: ein Zugang fuer ein fremdes System, nicht fuer einen Menschen.
 *
 * Ein Token gehoert keinem Benutzer. `created_by` haelt nur fest, welcher
 * Administrator es ausgestellt hat. Was ein Token darf, steht ausschliesslich
 * in `scopes`.
 *
 * @property string $id
 * @property string $name
 * @property string $token_id
 * @property string $token_hash
 * @property list<string> $scopes
 * @property string|null $created_by
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 */
#[Fillable(['name', 'token_id', 'token_hash', 'scopes', 'created_by', 'expires_at'])]
#[Hidden(['token_hash'])]
class ApiToken extends Model
{
    use HasUuids;

    /** Tokens werden nie bearbeitet, nur widerrufen. */
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Das Token der laufenden API-Anfrage. Gesetzt von der Middleware
     * `api.token` bzw. `scope:…` (App\Http\Middleware\RequireScope).
     */
    public static function fromRequest(Request $request): self
    {
        $token = $request->attributes->get(self::class);

        if (! $token instanceof self) {
            throw new \LogicException('Route ohne api.token- oder scope:-Middleware.');
        }

        return $token;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /** Die einzige Autorisierungsfrage, die ein Token beantwortet. */
    public function hasScope(Scope|string $scope): bool
    {
        $name = $scope instanceof Scope ? $scope->value : $scope;

        return in_array($name, $this->scopes, true);
    }

    /** Zum Anzeigen in Listen: `sk_a1b2c3d4…` */
    public function displayHint(): string
    {
        return TokenFormat::PREFIX.substr($this->token_id, 0, 8).'…';
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->isRevoked() => 'widerrufen',
            $this->isExpired() => 'abgelaufen',
            // Gueltig, aber ohne jede Berechtigung — fast immer ein Versehen.
            $this->scopes === [] => 'ohne Rechte',
            default => 'aktiv',
        };
    }

    /** @return list<string> Beschriftete Scopes fuer die Oberflaeche. */
    public function scopeLabels(): array
    {
        return array_map(Scope::labelFor(...), $this->scopes);
    }
}
