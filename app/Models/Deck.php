<?php

namespace App\Models;

use App\Enums\Language;
use Database\Factories\DeckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A deck ("Stapel"): a named set of cards with a front and a back language.
 *
 * Private or shared with everyone. Who may see or change a deck is decided
 * in App\Services\DeckService, not here.
 *
 * @property string $id
 * @property string $user_id
 * @property string $name
 * @property string|null $description
 * @property string $front_language
 * @property string $back_language
 * @property bool $is_shared
 * @property string|null $copied_from_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read int|null $cards_count
 * @property-read int|null $followers_count
 */
#[Fillable(['name', 'description', 'front_language', 'back_language'])]
class Deck extends Model
{
    /** @use HasFactory<DeckFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['is_shared' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Card, $this> */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'deck_follows');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function frontLabel(): string
    {
        return Language::labelFor($this->front_language);
    }

    public function backLabel(): string
    {
        return Language::labelFor($this->back_language);
    }

    /** "Deutsch → Englisch" */
    public function directionLabel(): string
    {
        return $this->frontLabel().' → '.$this->backLabel();
    }
}
