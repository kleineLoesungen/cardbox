<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One player of a duel: a member (user_id) or a guest (guest_name and a
 * secret link, stored as keyed hash).
 *
 * @property string $id
 * @property string $duel_id
 * @property string|null $user_id
 * @property string|null $guest_name
 * @property string|null $guest_token_hash
 * @property int $position
 * @property int $correct
 * @property int $time_ms
 * @property Carbon|null $shown_at
 * @property Carbon $joined_at
 * @property Carbon|null $finished_at
 */
class DuelPlayer extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'correct' => 'integer',
            'time_ms' => 'integer',
            'shown_at' => 'datetime',
            'joined_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Duel, $this> */
    public function duel(): BelongsTo
    {
        return $this->belongsTo(Duel::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isGuest(): bool
    {
        return $this->user_id === null;
    }

    /** The name shown in a duel: display name, or the guest's name with "(Gast)". */
    public function displayName(): string
    {
        return $this->isGuest() ? $this->guest_name.' (Gast)' : $this->user->name;
    }
}
