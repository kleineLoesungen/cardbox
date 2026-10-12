<?php

namespace App\Models;

use App\Enums\StudyPlan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A deck learned by one person with one plan ("Durchgang"). The plan is
 * copied in when the run starts and never changes; to learn with another
 * plan, the person resets the run or starts a second one.
 *
 * Rules live in App\Services\DeckRunService and LeitnerService.
 *
 * @property string $id
 * @property string $user_id
 * @property string $deck_id
 * @property string $plan normal | quick | thorough | custom
 * @property int $box_count
 * @property list<int> $day_gaps
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['user_id', 'deck_id', 'plan', 'box_count', 'day_gaps'])]
class DeckRun extends Model
{
    use HasUuids;

    /** Key of a run started with a custom standard plan ("Mein Plan"). */
    public const CUSTOM = 'custom';

    protected function casts(): array
    {
        return ['box_count' => 'integer', 'day_gaps' => 'array'];
    }

    /** @return BelongsTo<Deck, $this> */
    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class);
    }

    /** @return HasMany<CardProgress, $this> */
    public function progress(): HasMany
    {
        return $this->hasMany(CardProgress::class);
    }

    /** "Normal", "Schnell", "Gründlich" or "Mein Plan". */
    public function label(): string
    {
        return StudyPlan::tryFrom($this->plan)?->label() ?? 'Mein Plan';
    }
}
