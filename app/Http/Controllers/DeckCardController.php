<?php

namespace App\Http\Controllers;

use App\Services\CardService;
use App\Services\DeckService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Cards inside a deck — answered as htmx fragments (partials/…), never as
 * redirects or whole pages. Validation errors become a toast via
 * bootstrap/app.php, the sheet stays open.
 */
class DeckCardController extends Controller
{
    public function __construct(private CardService $cards, private DeckService $decks) {}

    /** Saves a card and answers with a fresh form plus the new row (out of band). */
    public function store(Request $request, string $deck): View
    {
        $card = $this->cards->createOwned($request->user(), $deck, $request->only('front', 'back'));

        return view('partials.card-created', [
            'deck' => $card->deck,
            'card' => $card,
            'count' => $card->deck->cards()->count(),
        ]);
    }

    public function edit(Request $request, string $deck, string $card): View
    {
        $model = $this->cards->getOwned($request->user(), $deck, $card);

        return view('partials.card-edit', [
            'deck' => $model->deck,
            'card' => $model,
            'followerCount' => $this->decks->followerCount($model->deck),
        ]);
    }

    public function update(Request $request, string $deck, string $card): Response
    {
        $model = $this->cards->updateOwned($request->user(), $deck, $card, $request->only('front', 'back'));

        return response()
            ->view('partials.card-updated', ['deck' => $model->deck, 'card' => $model])
            ->header('HX-Trigger', 'sheet-close');
    }

    public function destroy(Request $request, string $deck, string $card): Response
    {
        $model = $this->cards->getOwned($request->user(), $deck, $card);
        $this->cards->deleteOwned($request->user(), $deck, $card);

        return response()
            ->view('partials.card-deleted', [
                'deck' => $model->deck,
                'count' => $model->deck->cards()->count(),
                'isOwner' => true,
            ])
            ->header('HX-Trigger', 'sheet-close');
    }

    /** Hint below the front field: "Gibt es schon in diesem Stapel". */
    public function frontCheck(Request $request, string $deck): View
    {
        $model = $this->decks->getOwned($request->user(), $deck);
        $front = $request->query('front');
        $first = is_array($front) ? ($front[0] ?? '') : $front;

        return view('partials.front-hint', [
            'exists' => is_string($first) && $this->cards->frontExists($model, $first),
        ]);
    }

    /** "Aus anderen Stapeln übernehmen": search results. */
    public function search(Request $request, string $deck): View
    {
        $term = $this->queryText($request, 'q');

        return view('partials.adopt-results', [
            'deck' => $this->decks->getOwned($request->user(), $deck),
            'term' => $term,
            'results' => $this->cards->searchToAdopt($request->user(), $deck, $term),
        ]);
    }

    public function adopt(Request $request, string $deck, string $card): View
    {
        $copy = $this->cards->adopt($request->user(), $deck, $card);

        return view('partials.card-adopted', [
            'deck' => $copy->deck,
            'card' => $copy,
            'count' => $copy->deck->cards()->count(),
        ]);
    }
}
