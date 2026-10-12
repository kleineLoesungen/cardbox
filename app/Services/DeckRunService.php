<?php

namespace App\Services;

use App\Enums\StudyPlan;
use App\Models\Deck;
use App\Models\DeckRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Deck runs ("Durchgänge"): a deck learned with one plan. See
 * docs/specs/lernplaene-und-karten.md, A1–A4.
 *
 * * A deck runs at most once per plan (normal, quick, thorough, custom).
 * * The plan's values are copied in at the start and never change. There is
 *   deliberately no way to change them; reset and start again instead.
 * * Runs are always the viewer's own. Other people's runs answer 404.
 */
class DeckRunService
{
    /** Plan choices on the deck page; "standard" is the person's own default. */
    public const CHOICES = ['standard', 'normal', 'quick', 'thorough'];

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
     * Starts a deck with a plan from the deck page ("Mit anderem Lernplan
     * starten"). Learning somebody else's shared deck follows it.
     *
     * @throws ModelNotFoundException<Deck>
     * @throws ValidationException
     */
    public function start(User $user, string $deckId, mixed $choice): DeckRun
    {
        Validator::make(['plan' => $choice], [
            'plan' => ['required', 'string', Rule::in(self::CHOICES)],
        ], [
            'plan.required' => 'Bitte einen Lernplan wählen.',
            'plan.in' => 'Diesen Lernplan gibt es nicht.',
        ])->validate();

        $deck = $this->decks->getVisible($user, $deckId);
        [$key, $boxCount, $gaps] = $this->values($user, (string) $choice);

        try {
            // Nested transaction = savepoint: in PostgreSQL a failed insert
            // would otherwise abort a surrounding transaction.
            $run = DB::transaction(fn () => DeckRun::create([
                'user_id' => $user->id,
                'deck_id' => $deck->id,
                'plan' => $key,
                'box_count' => $boxCount,
                'day_gaps' => $gaps,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['plan' => 'Dieser Stapel läuft schon mit diesem Plan.']);
        }

        $this->followIfForeign($user, $deck);

        return $run;
    }

    /**
     * The run with the person's standard plan, started if needed. Used when
     * "Lernen" is tapped on a deck that has no run yet; the caller follows.
     */
    public function forStandard(User $user, Deck $deck): DeckRun
    {
        [$key, $boxCount, $gaps] = $this->values($user, 'standard');

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
     * Plan key and values for a choice.
     *
     * @return array{0: string, 1: int, 2: list<int>}
     */
    private function values(User $user, string $choice): array
    {
        if ($choice === 'standard') {
            $plan = $this->leitner->plan($user);

            return [$this->standardKey($user), $plan['box_count'], $plan['day_gaps']];
        }

        $preset = StudyPlan::from($choice);

        return [$preset->value, $preset->boxCount(), $preset->dayGaps()];
    }

    /** Learning somebody else's shared deck follows it, so it shows up under "Meine Stapel". */
    private function followIfForeign(User $user, Deck $deck): void
    {
        if (! $deck->isOwnedBy($user)) {
            $user->followedDecks()->syncWithoutDetaching([$deck->id]);
        }
    }
}
