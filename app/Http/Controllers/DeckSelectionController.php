<?php

namespace App\Http\Controllers;

use App\Services\CardSelectionService;
use App\Services\DeckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Karten auswählen": which cards of a deck the person learns (issue #10).
 * Any visible deck, own or followed; the choice is the person's own. All
 * rules are in CardSelectionService.
 */
class DeckSelectionController extends Controller
{
    public function __construct(private CardSelectionService $selection, private DeckService $decks) {}

    public function edit(Request $request, string $deck): View
    {
        $user = $request->user();
        $model = $this->decks->getVisible($user, $deck);

        return view('decks.selection', [
            'deck' => $model,
            'cards' => $this->selection->cardsInOrder($user, $deck),
            'activeIds' => $this->selection->activeIds($user, $model),
        ]);
    }

    public function update(Request $request, string $deck): RedirectResponse
    {
        $cards = $request->input('cards');
        $this->selection->select($request->user(), $deck, is_array($cards) ? array_values($cards) : []);

        return redirect("/decks/{$deck}")->with('toast', ['kind' => 'success', 'message' => 'Auswahl gespeichert.']);
    }

    public function all(Request $request, string $deck): RedirectResponse
    {
        $this->selection->selectAll($request->user(), $deck);

        return redirect("/decks/{$deck}")->with('toast', ['kind' => 'success', 'message' => 'Du lernst wieder alle Karten.']);
    }
}
