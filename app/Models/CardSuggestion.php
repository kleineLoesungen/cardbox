<?php

namespace App\Models;

use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * "Fehler melden": a suggestion to the owner of a shared deck. Only the
 * owner changes the card (CardSuggestionService::accept).
 *
 * @property string $id
 * @property string $card_id
 * @property string $user_id the sender
 * @property SuggestionType $type
 * @property list<string>|null $proposed_front
 * @property list<string>|null $proposed_back
 * @property string|null $comment
 * @property SuggestionStatus $status
 * @property Carbon $card_updated_at
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['type', 'proposed_front', 'proposed_back', 'comment'])]
class CardSuggestion extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'type' => SuggestionType::class,
            'status' => SuggestionStatus::class,
            'proposed_front' => 'array',
            'proposed_back' => 'array',
            'card_updated_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Card, $this> */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whether it proposes new text at all (otherwise it is a comment only). */
    public function proposesText(): bool
    {
        return $this->proposed_front !== null || $this->proposed_back !== null;
    }
}
