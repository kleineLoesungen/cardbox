<?php

namespace App\Http\Resources;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON-Form einer Notiz. Hier steht ausdruecklich, welche Felder die
 * Schnittstelle verlassen — ein neues Datenbankfeld erscheint NICHT von selbst.
 *
 * Laravel huellt die Antwort automatisch in {"data": …}.
 *
 * @mixin Note
 */
class NoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'body' => $this->body,
            // Zeitangaben durchgehend RFC 3339 in UTC.
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
