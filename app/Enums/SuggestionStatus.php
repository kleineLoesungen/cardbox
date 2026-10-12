<?php

namespace App\Enums;

/** Where a suggestion stands; only open ones can be decided. */
enum SuggestionStatus: string
{
    case Open = 'open';
    case Accepted = 'accepted';
    case Declined = 'declined';

    /** What the sender sees at the card. */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Vorschlag offen',
            self::Accepted => 'Vorschlag übernommen',
            self::Declined => 'Vorschlag abgelehnt',
        };
    }
}
