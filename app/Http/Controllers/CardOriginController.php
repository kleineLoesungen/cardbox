<?php

namespace App\Http\Controllers;

use App\Services\CardService;
use App\Services\DeckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Änderungen im Original": copied cards whose original changed. Only for
 * the owner of the copy; all checks are in CardService.
 */
class CardOriginController extends Controller
{
    public function __construct(private CardService $cards, private DeckService $decks) {}

    public function index(Request $request, string $deck): View|RedirectResponse
    {
        $user = $request->user();
        $entries = $this->cards->pendingOriginalUpdates($user, $deck);
        if ($entries === []) {
            return redirect("/decks/{$deck}");
        }

        return view('decks.origin', [
            'deck' => $this->decks->getOwned($user, $deck),
            'entries' => $entries,
        ]);
    }

    public function take(Request $request, string $deck, string $card): RedirectResponse
    {
        $this->cards->takeOriginal($request->user(), $deck, $card);

        return redirect("/decks/{$deck}/origin")->with('toast', ['kind' => 'success', 'message' => 'Änderung übernommen.']);
    }

    public function keep(Request $request, string $deck, string $card): RedirectResponse
    {
        $this->cards->keepMine($request->user(), $deck, $card);

        return redirect("/decks/{$deck}/origin")->with('toast', ['kind' => 'success', 'message' => 'Deine Karte bleibt, wie sie ist.']);
    }

    public function takeAll(Request $request, string $deck): RedirectResponse
    {
        $count = $this->cards->takeAllOriginals($request->user(), $deck);

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $count === 1 ? '1 Änderung übernommen.' : "{$count} Änderungen übernommen.",
        ]);
    }
}
