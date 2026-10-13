<?php

namespace App\Http\Controllers;

use App\Enums\Language;
use App\Services\CardService;
use App\Services\DictionaryService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "Wörterbuch": look words up in every visible card, read them aloud and
 * take one into an own deck. All rules are in DictionaryService and
 * CardService::adopt.
 */
class DictionaryController extends Controller
{
    public function __construct(private DictionaryService $dictionary, private CardService $cards) {}

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

    /** htmx: copies the card into one of the user's decks; the button turns into a confirmation. */
    public function adopt(Request $request, string $card): Response
    {
        $deck = $request->input('deck');
        $copy = $this->cards->adopt($request->user(), is_string($deck) ? $deck : '', $card);

        return response()->view('partials.dictionary-adopted', ['deck' => $copy->deck]);
    }
}
