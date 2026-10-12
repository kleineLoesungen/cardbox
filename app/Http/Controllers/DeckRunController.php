<?php

namespace App\Http\Controllers;

use App\Services\DeckRunService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Starting and resetting deck runs ("Durchgänge") from the deck page.
 * All rules are in DeckRunService; runs are always the viewer's own.
 */
class DeckRunController extends Controller
{
    public function __construct(private DeckRunService $runs) {}

    /** "Mit anderem Lernplan starten". */
    public function store(Request $request, string $deck): RedirectResponse
    {
        $run = $this->runs->start($request->user(), $deck, $request->input('plan'));

        return redirect("/decks/{$deck}?run={$run->id}")
            ->with('toast', ['kind' => 'success', 'message' => "Gestartet mit Lernplan {$run->label()}."]);
    }

    /** "Fortschritt zurücksetzen": deletes this run with all its progress. */
    public function reset(Request $request, string $deck, string $run): RedirectResponse
    {
        $model = $this->runs->resetOwned($request->user(), $deck, $run);

        return redirect("/decks/{$deck}")
            ->with('toast', ['kind' => 'success', 'message' => "Fortschritt mit Lernplan {$model->label()} gelöscht."]);
    }
}
