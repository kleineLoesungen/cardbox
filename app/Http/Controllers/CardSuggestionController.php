<?php

namespace App\Http\Controllers;

use App\Services\CardSuggestionService;
use App\Services\DeckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Fehler melden" (sender, htmx sheet) and the owner's list of suggestions.
 * All checks are in CardSuggestionService.
 */
class CardSuggestionController extends Controller
{
    public function __construct(private CardSuggestionService $suggestions, private DeckService $decks) {}

    /** htmx: the form in #suggest-sheet. */
    public function create(Request $request, string $deck, string $card): View
    {
        $model = $this->suggestions->getSuggestable($request->user(), $deck, $card);

        return view('decks.partials.suggest-form', [
            'deck' => $model->deck,
            'card' => $model,
            'open' => $this->suggestions->openFor($request->user(), $model),
            'fromDictionary' => $request->query('context') === 'dictionary',
        ]);
    }

    /** htmx: the card row replaces itself with the new status (in the dictionary only a toast); the sheet closes. */
    public function store(Request $request, string $deck, string $card): Response
    {
        $suggestion = $this->suggestions->createFor(
            $request->user(), $deck, $card, $request->only('type', 'front', 'back', 'comment'),
        );
        $model = $this->suggestions->getSuggestable($request->user(), $deck, $card);
        $toast = ['kind' => 'success', 'message' => 'Danke! Dein Vorschlag ist bei '.$model->deck->user->name.'.'];

        // From the dictionary there is no card row to replace (issue #12).
        if ($request->input('context') === 'dictionary') {
            return response()->view('partials.toast-oob', ['toast' => $toast])->header('HX-Trigger', 'sheet-close');
        }

        return response()
            ->view('partials.suggestion-sent', ['deck' => $model->deck, 'card' => $model, 'suggestion' => $suggestion])
            ->header('HX-Trigger', 'sheet-close');
    }

    /** The owner's list of open suggestions for one deck. */
    public function index(Request $request, string $deck): View|RedirectResponse
    {
        $user = $request->user();
        $list = $this->suggestions->listForOwnedDeck($user, $deck);
        if ($list->isEmpty()) {
            return redirect("/decks/{$deck}");
        }

        return view('decks.suggestions', ['deck' => $this->decks->getOwned($user, $deck), 'suggestions' => $list]);
    }

    public function accept(Request $request, string $deck, string $suggestion): RedirectResponse
    {
        $model = $this->suggestions->accept($request->user(), $deck, $suggestion);

        return redirect("/decks/{$deck}/suggestions")->with('toast', [
            'kind' => 'success',
            'message' => $model->proposesText() ? 'Vorschlag übernommen, Karte geändert.' : 'Als erledigt markiert.',
        ]);
    }

    public function decline(Request $request, string $deck, string $suggestion): RedirectResponse
    {
        $this->suggestions->decline($request->user(), $deck, $suggestion);

        return redirect("/decks/{$deck}/suggestions")->with('toast', ['kind' => 'success', 'message' => 'Vorschlag abgelehnt.']);
    }
}
