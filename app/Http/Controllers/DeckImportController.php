<?php

namespace App\Http\Controllers;

use App\Services\CardImportService;
use App\Services\DeckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Liste einfügen": form → preview → import, as plain forms (Post/Redirect/Get
 * at the end). The preview carries the original text in a hidden field;
 * the import parses it again, so preview and result match.
 */
class DeckImportController extends Controller
{
    public function __construct(private DeckService $decks, private CardImportService $import) {}

    public function show(Request $request, string $deck): View
    {
        return view('decks.import', ['deck' => $this->decks->getOwned($request->user(), $deck)]);
    }

    public function preview(Request $request, string $deck): View
    {
        $model = $this->decks->getOwned($request->user(), $deck);
        $text = $this->import->textFrom($request->only('text', 'file'));

        return view('decks.import-preview', [
            'deck' => $model,
            'text' => $text,
            'result' => $this->import->parse($model, $text),
        ]);
    }

    public function store(Request $request, string $deck): RedirectResponse
    {
        $count = $this->import->import($request->user(), $deck, $request->only('text'));

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $count === 1 ? '1 Karte hinzugefügt.' : "{$count} Karten hinzugefügt.",
        ]);
    }
}
