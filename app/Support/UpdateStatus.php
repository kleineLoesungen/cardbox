<?php

namespace App\Support;

use App\Services\UpdateCheck;
use Illuminate\Support\Carbon;

/**
 * Was die Systemseite über Version und Update-Prüfung anzeigt.
 *
 * @see UpdateCheck
 */
final readonly class UpdateStatus
{
    /**
     * @param  bool  $enabled  `false`, wenn keine Prüfadresse gesetzt ist
     * @param  list<ChangelogEntry>  $newer  Einträge, die neuer sind als die installierte Version
     * @param  bool  $ok  `false`, wenn der letzte Abruf fehlschlug
     */
    public function __construct(
        public bool $enabled,
        public ?string $installed,
        public ?string $latest = null,
        public array $newer = [],
        public bool $ok = false,
        public ?Carbon $checkedAt = null,
    ) {}

    /** Liegt etwas Neueres vor? */
    public function updateAvailable(): bool
    {
        return $this->newer !== [];
    }

    public function installedLabel(): ?string
    {
        return $this->installed === null ? null : Changelog::versionLabel($this->installed);
    }

    public function latestLabel(): ?string
    {
        return $this->latest === null ? null : Changelog::versionLabel($this->latest);
    }
}
