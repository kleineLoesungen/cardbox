<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A card: one or more meanings on the front, one or more on the back. The
 * first meaning of a side is its main meaning; the others are alternatives.
 *
 * Belongs to exactly one deck; the languages are the deck's.
 *
 * @property string $id
 * @property string $deck_id
 * @property list<string> $front
 * @property list<string> $back
 * @property string|null $hint short context like "(Zug)", shown before flipping
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['front', 'back', 'hint'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['front' => 'array', 'back' => 'array'];
    }

    /** @return BelongsTo<Deck, $this> */
    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }

    public function frontText(): string
    {
        return $this->front[0] ?? '';
    }

    public function backText(): string
    {
        return $this->back[0] ?? '';
    }

    /**
     * Meanings after the main one, for "auch: …".
     *
     * @param  list<string>  $side
     * @return list<string>
     */
    public static function alternatives(array $side): array
    {
        return array_slice($side, 1);
    }
}
