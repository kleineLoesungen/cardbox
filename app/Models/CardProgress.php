<?php

namespace App\Models;

use App\Enums\Direction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Where one card stands in one run (person + deck + plan) and direction.
 *
 * The rules that move it between boxes live in App\Services\LeitnerService.
 *
 * @property string $id
 * @property string $user_id
 * @property string $deck_run_id
 * @property string $card_id
 * @property Direction $direction
 * @property int $box
 * @property Carbon|null $due_on
 * @property Carbon|null $last_reviewed_at
 * @property int $review_count
 * @property int $correct_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['user_id', 'deck_run_id', 'card_id', 'direction', 'box', 'due_on', 'last_reviewed_at', 'review_count', 'correct_count'])]
class CardProgress extends Model
{
    use HasUuids;

    protected $table = 'card_progress';

    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'box' => 'integer',
            'due_on' => 'date',
            'last_reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<DeckRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(DeckRun::class, 'deck_run_id');
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }
}
