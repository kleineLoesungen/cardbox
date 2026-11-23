<?php

namespace App\Services;

use App\Models\User;
use App\Support\Tours;
use Illuminate\Support\Facades\DB;

/**
 * Guided tours (docs/specs/gefuehrte-tour.md): which steps a person has not
 * seen yet, and remembering the ones shown. Own account only.
 */
class TourService
{
    /** At most this many ids per report; a page shows at most a handful. */
    public const MAX_PER_REPORT = 20;

    /**
     * Steps of these pages the person has not seen, in order. A page whose
     * other steps were seen before marks its new ones with "Neu: ".
     *
     * @param  list<string>  $pages  keys of Tours::PAGES; unknown ones are ignored
     * @return list<array{id: string, target: string|null, title: string, text: string}>
     */
    public function pending(User $user, array $pages): array
    {
        $seen = array_flip($user->tour_steps_seen);
        $pending = [];
        foreach ($pages as $page) {
            $steps = Tours::PAGES[$page] ?? [];
            $known = array_filter($steps, fn (array $step) => isset($seen[$step['id']])) !== [];
            foreach ($steps as $step) {
                if (! isset($seen[$step['id']])) {
                    $pending[] = $known ? [...$step, 'title' => 'Neu: '.$step['title']] : $step;
                }
            }
        }

        return $pending;
    }

    /**
     * Remembers steps as seen. Anything that is not a known step id is
     * dropped silently; returns how many were new.
     */
    public function markSeen(User $user, mixed $ids): int
    {
        $valid = array_values(array_intersect(
            array_slice(array_filter(is_array($ids) ? $ids : [], 'is_string'), 0, self::MAX_PER_REPORT),
            Tours::stepIds(),
        ));
        if ($valid === []) {
            return 0;
        }

        return DB::transaction(function () use ($user, $valid) {
            // Locked: two pages reporting at once must not drop each other's ids.
            $current = User::whereKey($user->id)->lockForUpdate()->firstOrFail()->tour_steps_seen;
            $merged = array_values(array_unique([...$current, ...$valid]));
            // Not a change of the account: updated_at stays (as for the last login).
            User::withoutTimestamps(fn () => $user->forceFill(['tour_steps_seen' => $merged])->save());

            return count($merged) - count($current);
        });
    }

    /** "Touren noch einmal zeigen": every step is new again. */
    public function reset(User $user): void
    {
        User::withoutTimestamps(fn () => $user->forceFill(['tour_steps_seen' => []])->save());
    }
}
