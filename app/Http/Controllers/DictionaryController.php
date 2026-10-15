<?php

namespace App\Http\Controllers;

use App\Enums\Language;
use App\Services\CardSelectionService;
use App\Services\CardService;
use App\Services\DictionaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Wörterbuch": look words up in every visible card, read them aloud,
 * take one into an own deck, and edit or delete own cards. All rules are in
 * DictionaryService and CardService.
 */
class DictionaryController extends Controller
{
    public function __construct(
        private DictionaryService $dictionary,
        private CardService $cards,
        private CardSelectionService $selection,
    ) {}

    /** The page; with ?q= it also shows results (reload, no JavaScript). */
    public function index(Request $request): View
    {
        $user = $request->user();
        $term = $this->queryText($request, 'q');
        $language = $this->queryText($request, 'lang');

        return view('pages.dictionary', [
            'term' => $term,
            'language' => Language::tryFrom($language)->value ?? '',
            'languages' => $this->dictionary->languages($user),
            'result' => $this->dictionary->search($user, $term, $language),
        ]);
    }

    /** htmx: the results for the search field. */
    public function search(Request $request): View
    {
        $term = $this->queryText($request, 'q');

        return view('partials.dictionary-results', [
            'term' => $term,
            'result' => $this->dictionary->search($request->user(), $term, $this->queryText($request, 'lang')),
        ]);
    }

    /**
     * htmx: "Folgen und diese Karte lernen" / "Diese Karte lernen" (issue #10):
     * follows the card's deck if needed and makes the card active.
     */
    public function learn(Request $request, string $card): Response
    {
        $model = $this->selection->learnCard($request->user(), $card);

        return response()->view('partials.dictionary-learning', ['deck' => $model->deck]);
    }

    /** htmx: copies the card into one of the user's decks; the button turns into a confirmation. */
    public function adopt(Request $request, string $card): Response
    {
        $deck = $request->input('deck');
        $copy = $this->cards->adopt($request->user(), is_string($deck) ? $deck : '', $card);

        return response()->view('partials.dictionary-adopted', ['deck' => $copy->deck]);
    }

    /**
     * htmx: the sheet "Karte bearbeiten" (issue #12). With several cards in
     * the hit it first offers a choice; ?selected= is a card id or "all".
     */
    public function edit(Request $request, string $card): View
    {
        $selected = $request->query('selected');

        return view('partials.dictionary-edit', [
            'group' => $this->dictionary->group($request->user(), $card),
            'selected' => is_string($selected) ? $selected : null,
        ]);
    }

    /** htmx: saves own cards; the sheet closes and the results reload. */
    public function update(Request $request): Response
    {
        $cards = $this->cards->updateManyOwned($request->user(), $request->input('cards'), $request->input('base'), $request->only('front', 'back', 'hint'));

        return $this->dictionaryChanged($cards->count(), 'gespeichert');
    }

    /** htmx: deletes own cards; the sheet closes and the results reload. */
    public function destroy(Request $request): Response
    {
        $cards = $this->cards->deleteManyOwned($request->user(), $request->input('cards'));

        return $this->dictionaryChanged($cards->count(), 'gelöscht');
    }
}
