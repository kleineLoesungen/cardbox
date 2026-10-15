<?php

namespace App\Http\Controllers;

use App\Services\LeitnerService;
use App\Services\StudyRoundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * A learning round in full screen. The round state lives in the session
 * (key `study_round`), so reloading continues at the same card and a new
 * round replaces the old one. All rules are in StudyRoundService.
 *
 * @phpstan-import-type Round from StudyRoundService
 */
class StudyRoundController extends Controller
{
    private const SESSION_KEY = 'study_round';

    public function __construct(private StudyRoundService $rounds, private LeitnerService $leitner) {}

    /** Starts a round: a deck (learn, practice, test) or, without a deck, "Los geht's". */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();
        $deck = $request->input('deck');
        $direction = $request->input('direction');
        $run = $request->input('run');
        $run = is_string($run) ? $run : null;

        $round = match (true) {
            $request->input('mode') === 'practice' => $this->rounds->startPractice(
                $user,
                is_string($deck) ? $deck : '',
                is_string($direction) ? $direction : '',
                $this->boxInput($request),
                $run,
            ),
            $request->input('mode') === 'test' => $this->rounds->startTest($user, is_string($deck) ? $deck : ''),
            is_string($deck) && $deck !== '' => $this->rounds->startForDeck($user, $deck, $run),
            default => $this->rounds->startToday($user, $request->boolean('more')),
        };

        $request->session()->put(self::SESSION_KEY, $round);

        return redirect('/learn/round');
    }

    public function show(Request $request): View|RedirectResponse
    {
        $round = $this->stored($request);
        if ($round === null) {
            return redirect('/learn');
        }

        return view('learn.round', $this->state($request, $round));
    }

    /** htmx: rates the card that is up and answers with the next one (or the end). */
    public function answer(Request $request): View|Response|RedirectResponse
    {
        $round = $this->stored($request);
        if ($round === null) {
            return $request->headers->has('HX-Request')
                ? response('', 200)->header('HX-Redirect', '/learn')
                : redirect('/learn');
        }

        $card = $request->input('card');
        $step = $request->input('step');
        $round = $this->rounds->answer(
            $request->user(),
            $round,
            is_string($card) ? $card : '',
            is_numeric($step) ? (int) $step : -1,
            $request->boolean('known'),
        );

        if (! $request->headers->has('HX-Request')) {
            $request->session()->put(self::SESSION_KEY, $round);

            return redirect('/learn/round');
        }

        return view('learn.partials.round-body', $this->state($request, $round));
    }

    /**
     * Optional box for practice: null when absent, -1 for anything that is
     * not a small number (the service then rejects it).
     */
    private function boxInput(Request $request): ?int
    {
        if (! $request->has('box')) {
            return null;
        }
        $box = $request->input('box');

        return is_string($box) && ctype_digit($box) && strlen($box) <= 2 ? (int) $box : -1;
    }

    /**
     * The round from the session. Only StudyRoundService ever writes it.
     *
     * @return Round|null
     */
    private function stored(Request $request): ?array
    {
        $round = $request->session()->get(self::SESSION_KEY);

        /** @var Round|null */
        return is_array($round) && isset($round['queue'], $round['mode'], $round['step']) ? $round : null;
    }

    /**
     * Resolves the current card, stores the round and prepares the view data.
     *
     * @param  Round  $round
     * @return array<string, mixed>
     */
    private function state(Request $request, array $round): array
    {
        [$round, $current] = $this->rounds->current($request->user(), $round);
        $request->session()->put(self::SESSION_KEY, $round);

        return [
            'round' => $round,
            'current' => $current,
            'position' => $this->rounds->position($round),
            'streak' => $current === null ? $this->leitner->streak($request->user()) : 0,
        ];
    }
}
