<?php

namespace App\Http\Controllers\Moderation;

use App\Http\Controllers\Controller;
use App\Services\DeckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Moderation of shared decks. Route group `role:moderator`.
 *
 * Uses the system-wide `…Shared` flavour of DeckService, which only ever
 * touches shared decks — other people's private decks stay invisible here.
 * Here they end sharing or delete whole decks; correcting single cards is
 * Moderation\CardController.
 */
class DeckController extends Controller
{
    public function __construct(private DeckService $decks) {}

    public function index(Request $request): View
    {
        return view('pages.moderation', [
            'decks' => $this->decks->discover($request->user(), $this->queryText($request, 'q')),
            'search' => $this->queryText($request, 'q'),
        ]);
    }

    public function unshare(Request $request, string $deck): RedirectResponse
    {
        $model = $this->decks->unshareShared($deck);

        Log::info('Teilen beendet (Moderation)', ['by' => $request->user()->id, 'deck_id' => $model->id]);

        return redirect('/decks')->with('toast', ['kind' => 'success', 'message' => "„{$model->name}“ ist nicht mehr geteilt."]);
    }

    public function destroy(Request $request, string $deck): RedirectResponse
    {
        $this->decks->deleteShared($deck);

        Log::info('Stapel gelöscht (Moderation)', ['by' => $request->user()->id, 'deck_id' => $deck]);

        return redirect('/decks')->with('toast', ['kind' => 'success', 'message' => 'Stapel gelöscht.']);
    }
}
