<?php

namespace App\Services;

use App\Enums\StudyPlan;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Deck runs ("Durchgänge"): a deck learned with one plan. See
 * docs/specs/lernplaene-und-karten.md, A1–A4.
 *
 * * A deck runs at most once per plan (normal, quick, thorough, custom).
 *   New runs only start with the person's standard plan; starting another
 *   plan on the deck page is gone (docs/specs/einfacher-lernen.md, C), but
 *   runs started before keep working.
 * * The plan's values are copied in at the start and never change. There is
 *   deliberately no way to change them; reset and start again instead.
 * * Runs are always the viewer's own. Other people's runs answer 404.
 */
class DeckRunService
{
    public function __construct(private DeckService $decks, private LeitnerService $leitner) {}

    /**
     * The person's runs of one deck, oldest first.
     *
     * @return Collection<int, DeckRun>
     */
    public function listOwned(User $user, Deck $deck): Collection
    {
        return DeckRun::query()
            ->where('user_id', $user->id)
            ->where('deck_id', $deck->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * One of the person's runs of a visible deck.
     *
     * @throws ModelNotFoundException<DeckRun|Deck>
     */
    public function getOwned(User $user, string $deckId, string $runId): DeckRun
    {
        $deck = $this->decks->getVisible($user, $deckId);
        if (! Str::isUuid($runId)) {
            throw (new ModelNotFoundException)->setModel(DeckRun::class, [$runId]);
        }

        return DeckRun::query()
            ->where('user_id', $user->id)
            ->where('deck_id', $deck->id)
            ->findOrFail($runId);
    }

    /**
     * Key of the person's standard plan: a preset, or "custom".
     */
    public function standardKey(User $user): string
    {
        $plan = $this->leitner->plan($user);

        return StudyPlan::matching($plan['box_count'], $plan['day_gaps'])->value ?? DeckRun::CUSTOM;
    }

    /**
     * The run with the person's standard plan, started if needed. Used when
     * "Lernen" is tapped on a deck that has no run yet; the caller follows.
     */
    public function forStandard(User $user, Deck $deck): DeckRun
    {
        [$key, $boxCount, $gaps] = $this->standardValues($user);

        // createOrFirst: two taps at once must not end in a unique violation.
        $run = DeckRun::createOrFirst(
            ['user_id' => $user->id, 'deck_id' => $deck->id, 'plan' => $key],
            ['box_count' => $boxCount, 'day_gaps' => $gaps],
        );

        return $run;
    }

    /**
     * "Fortschritt zurücksetzen": deletes the run with all its progress.
     * Other runs of the deck and other people stay untouched.
     *
     * @throws ModelNotFoundException<DeckRun|Deck>
     */
    public function resetOwned(User $user, string $deckId, string $runId): DeckRun
    {
        $run = $this->getOwned($user, $deckId, $runId);
        $run->delete();

        return $run;
    }

    /**
     * Key and values of the person's standard plan.
     *
     * @return array{0: string, 1: int, 2: list<int>}
     */
    private function standardValues(User $user): array
    {
        $plan = $this->leitner->plan($user);

        return [$this->standardKey($user), $plan['box_count'], $plan['day_gaps']];
    }
}
