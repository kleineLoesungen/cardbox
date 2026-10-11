<?php

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Beispielressource „Notiz".
 *
 * Vollstaendiges, kopierbares Muster: Modell hier, Fachlogik in
 * App\Services\NoteService, HTML in NoteController, JSON in Api\V1\NoteController.
 *
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['user_id', 'title', 'body'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory, HasUuids;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
