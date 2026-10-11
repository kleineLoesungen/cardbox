<?php

namespace App\Services;

use App\Enums\StudyPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The person's learning plan ("Lernplan"): one of the presets or a custom
 * plan with 2–10 boxes and an interval of 1–365 days per box.
 *
 * Changing the plan rewrites no progress. Cards in a box ≥ N simply count
 * as mastered (LeitnerService).
 */
class LearningSettingsService
{
    public const MIN_BOXES = 2;

    public const MAX_BOXES = 10;

    public const MAX_GAP_DAYS = 365;

    public function __construct(private LeitnerService $leitner) {}

    /**
     * @return array{preset: StudyPlan|null, box_count: int, day_gaps: list<int>}
     */
    public function current(User $user): array
    {
        $plan = $this->leitner->plan($user);

        return [
            'preset' => StudyPlan::matching($plan['box_count'], $plan['day_gaps']),
            'box_count' => $plan['box_count'],
            'day_gaps' => $plan['day_gaps'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input  plan (normal|quick|thorough|custom), box_count, day_gaps[]
     * @return array{box_count: int, day_gaps: list<int>}
     *
     * @throws ValidationException
     */
    public function save(User $user, array $input): array
    {
        Validator::make($input, [
            'plan' => ['required', Rule::in([...array_column(StudyPlan::cases(), 'value'), 'custom'])],
        ], [
            'plan.required' => 'Bitte einen Lernplan wählen.',
            'plan.in' => 'Bitte einen der angebotenen Lernpläne wählen.',
        ])->validate();

        $preset = StudyPlan::tryFrom((string) $input['plan']);
        [$boxCount, $gaps] = $preset !== null
            ? [$preset->boxCount(), $preset->dayGaps()]
            : $this->validateCustom($input);

        DB::table('learning_settings')->upsert(
            [[
                'user_id' => $user->id,
                'box_count' => $boxCount,
                'day_gaps' => json_encode($gaps),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id'],
            ['box_count', 'day_gaps', 'updated_at'],
        );
        $this->leitner->forgetPlan($user);

        return ['box_count' => $boxCount, 'day_gaps' => $gaps];
    }

    /**
     * Only the first N−1 intervals count: the form keeps fields for the
     * largest plan so the "−"/"+" buttons work without a round trip.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: list<int>}
     *
     * @throws ValidationException
     */
    private function validateCustom(array $input): array
    {
        Validator::make($input, [
            'box_count' => ['required', 'integer', 'min:'.self::MIN_BOXES, 'max:'.self::MAX_BOXES],
            'day_gaps' => ['required', 'array'],
        ], [
            'box_count.required' => 'Bitte die Anzahl der Fächer angeben.',
            'box_count.integer' => 'Die Anzahl der Fächer muss eine ganze Zahl sein.',
            'box_count.min' => 'Mindestens '.self::MIN_BOXES.' Fächer.',
            'box_count.max' => 'Höchstens '.self::MAX_BOXES.' Fächer.',
        ])->validate();

        $boxCount = (int) $input['box_count'];
        $gaps = array_slice(array_values((array) $input['day_gaps']), 0, $boxCount - 1);

        Validator::make(['day_gaps' => $gaps], [
            'day_gaps' => ['required', 'array', 'size:'.($boxCount - 1)],
            'day_gaps.*' => ['required', 'integer', 'min:1', 'max:'.self::MAX_GAP_DAYS],
        ], [
            'day_gaps.size' => 'Für jedes Fach fehlt noch die Anzahl der Tage.',
            'day_gaps.*.required' => 'Für jedes Fach fehlt noch die Anzahl der Tage.',
            'day_gaps.*.integer' => 'Die Tage müssen eine ganze Zahl sein.',
            'day_gaps.*.min' => 'Mindestens 1 Tag je Fach.',
            'day_gaps.*.max' => 'Höchstens '.self::MAX_GAP_DAYS.' Tage je Fach.',
        ])->validate();

        return [$boxCount, array_map('intval', $gaps)];
    }
}
