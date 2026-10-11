<?php

namespace App\Enums;

/**
 * Ready-made learning plans ("Lernplan"). A plan is a number of boxes N and
 * an interval in days for each box 1 … N−1; box N is "Kann ich".
 */
enum StudyPlan: string
{
    case Normal = 'normal';
    case Quick = 'quick';
    case Thorough = 'thorough';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Quick => 'Schnell',
            self::Thorough => 'Gründlich',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Normal => 'Für jeden Tag. Passt meistens.',
            self::Quick => 'Wenn bald eine Prüfung ist: Karten kommen öfter dran.',
            self::Thorough => 'Für lange merken: mehr Fächer, größere Abstände.',
        };
    }

    public function boxCount(): int
    {
        return match ($this) {
            self::Normal => 5,
            self::Quick => 4,
            self::Thorough => 6,
        };
    }

    /** @return list<int> */
    public function dayGaps(): array
    {
        return match ($this) {
            self::Normal => [1, 2, 4, 7],
            self::Quick => [1, 1, 2],
            self::Thorough => [1, 2, 4, 7, 14],
        };
    }

    /**
     * The preset with exactly these values, or null for a custom plan.
     *
     * @param  list<int>  $dayGaps
     */
    public static function matching(int $boxCount, array $dayGaps): ?self
    {
        foreach (self::cases() as $plan) {
            if ($plan->boxCount() === $boxCount && $plan->dayGaps() === $dayGaps) {
                return $plan;
            }
        }

        return null;
    }
}
