<?php

namespace App\Http\Controllers;

use App\Services\LearningOverviewService;
use App\Services\LeitnerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Heute" — the start page: what is due, the streak and the last 7 days, "Meine Stapel".
 */
class LearnController extends Controller
{
    public function __construct(
        private LearningOverviewService $overview,
        private LeitnerService $leitner,
    ) {}

    public function today(Request $request): View
    {
        $user = $request->user();
        [$decks, $stats] = $this->overview->myDecksWithStats($user);

        return view('learn.today', [
            'user' => $user,
            'today' => $this->overview->today($user),
            'vocabulary' => $this->overview->vocabulary($user),
            'week' => $this->leitner->recentDays($user),
            'decks' => $decks,
            'stats' => $stats,
        ]);
    }
}
