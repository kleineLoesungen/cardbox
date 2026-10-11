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
        $model = $this->decks->getOwned($request->user(), $deck);
        $draft = $request->session()->get('import_draft');

        return view('decks.import', [
            'deck' => $model,
            // Only via "Zurück zur Liste" (?back=1) and only for this deck.
            'draft' => $request->boolean('back') && is_array($draft) && ($draft['deck'] ?? null) === $model->id
                ? (string) ($draft['text'] ?? '')
                : '',
        ]);
    }

    public function preview(Request $request, string $deck): View
    {
        $model = $this->decks->getOwned($request->user(), $deck);
        $text = $this->import->textFrom($request->only('text', 'file'));
        // Kept for "Zurück zur Liste": one draft per session (a new preview
        // replaces it), and only reasonably small lists, since the session is
        // loaded on every request.
        $request->session()->forget('import_draft');
        if (strlen($text) <= 100 * 1024) {
            $request->session()->put('import_draft', ['deck' => $model->id, 'text' => $text]);
        }

        return view('decks.import-preview', [
            'deck' => $model,
            'text' => $text,
            'result' => $this->import->parse($model, $text),
        ]);
    }

    public function store(Request $request, string $deck): RedirectResponse
    {
        $count = $this->import->import($request->user(), $deck, $request->only('text'));
        $request->session()->forget('import_draft');

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $count === 1 ? '1 Karte hinzugefügt.' : "{$count} Karten hinzugefügt.",
        ]);
    }
}
