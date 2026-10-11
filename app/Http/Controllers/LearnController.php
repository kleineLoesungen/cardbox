<?php

namespace App\Http\Controllers;

use App\Services\LearningOverviewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Heute" — the start page: what is due, the streak, "Meine Stapel".
 */
class LearnController extends Controller
{
    public function __construct(private LearningOverviewService $overview) {}

    public function today(Request $request): View
    {
        $user = $request->user();
        [$decks, $stats] = $this->overview->myDecksWithStats($user);

        return view('learn.today', [
            'user' => $user,
            'today' => $this->overview->today($user),
            'decks' => $decks,
            'stats' => $stats,
        ]);
    }
}
