<?php

namespace App\Http\Controllers;

use App\Services\DeckRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resetting deck runs ("Durchgänge") from the deck page.
 * All rules are in DeckRunService; runs are always the viewer's own.
 */
class DeckRunController extends Controller
{
    public function __construct(private DeckRunService $runs) {}

    /** "Fortschritt zurücksetzen": deletes this run with all its progress. */
    public function reset(Request $request, string $deck, string $run): RedirectResponse
    {
        $model = $this->runs->resetOwned($request->user(), $deck, $run);

        return redirect("/decks/{$deck}")
            ->with('toast', ['kind' => 'success', 'message' => "Fortschritt mit Lernplan {$model->label()} gelöscht."]);
    }
}
