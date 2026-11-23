<?php

namespace App\Http\Controllers;

use App\Services\TourService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Guided tours (docs/specs/gefuehrte-tour.md); own account only. */
class TourController extends Controller
{
    public function __construct(private TourService $tours) {}

    /** Called by the tour script in the background when a tour closes. */
    public function seen(Request $request): Response
    {
        $this->tours->markSeen($request->user(), $request->input('steps'));

        return response()->noContent();
    }

    /** "Touren noch einmal zeigen" (help page, account). */
    public function reset(Request $request): RedirectResponse
    {
        $this->tours->reset($request->user());

        return redirect('/learn')->with('toast', ['kind' => 'success', 'message' => 'Die Touren kommen jetzt wieder.']);
    }
}
