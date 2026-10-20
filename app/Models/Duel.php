<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A duel: ten words of a deck for a set number of players
 * (docs/specs/duell.md). Rules live in App\Services\DuelService. Nothing
 * is mass assignable: the service sets every field with forceFill().
 *
 * @property string $id
 * @property string $creator_id
 * @property string|null $deck_id
 * @property string $deck_name
 * @property string $front_language
 * @property string $back_language
 * @property string $mode
 * @property int $player_count
 * @property string $code_hash
 * @property string $code_encrypted
 * @property Carbon $expires_at
 * @property Carbon|null $finished_at
 * @property Carbon $created_at
 */
class Duel extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'player_count' => 'integer',
            'expires_at' => 'datetime',
            'finished_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /** @return HasMany<DuelPlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(DuelPlayer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function isFinished(): bool
    {
        return $this->finished_at !== null;
    }
}
