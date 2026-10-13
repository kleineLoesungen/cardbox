<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use App\Services\CardCsvService;
use App\Services\CardImportService;
use App\Services\DeckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Liste einfügen": form → preview → import, as plain forms (Post/Redirect/Get
 * at the end). The preview carries the original text in a hidden field;
 * the import parses it again, so preview and result match.
 *
 * A file with an "ID" header is an exported CSV and gets its own preview
 * (new / changed / unchanged). Admin\DeckImportController reuses all of
 * this for shared decks of other people.
 */
class DeckImportController extends Controller
{
    public function __construct(
        protected DeckService $decks,
        protected CardImportService $import,
        protected CardCsvService $csv,
    ) {}

    public function show(Request $request, string $deck): View
    {
        $model = $this->deck($request, $deck);
        $draft = $request->session()->get('import_draft');

        return view('decks.import', [
            'deck' => $model,
            'base' => $this->base($model),
            // Only via "Zurück zur Liste" (?back=1) and only for this deck.
            'draft' => $request->boolean('back') && is_array($draft) && ($draft['deck'] ?? null) === $model->id
                ? (string) ($draft['text'] ?? '')
                : '',
        ]);
    }

    public function preview(Request $request, string $deck): View
    {
        $model = $this->deck($request, $deck);
        $text = $this->import->textFrom($request->only('text', 'file'));
        // Kept for "Zurück zur Liste": one draft per session (a new preview
        // replaces it), and only reasonably small lists, since the session is
        // loaded on every request.
        $request->session()->forget('import_draft');
        if (strlen($text) <= 100 * 1024) {
            $request->session()->put('import_draft', ['deck' => $model->id, 'text' => $text]);
        }

        if ($this->csv->isCsv($text)) {
            return view('decks.import-preview-csv', [
                'deck' => $model,
                'base' => $this->base($model),
                'text' => $text,
                'result' => $this->csv->parse($model, $request->user(), $text),
            ]);
        }

        return view('decks.import-preview', [
            'deck' => $model,
            'base' => $this->base($model),
            'text' => $text,
            'result' => $this->import->parse($model, $text),
        ]);
    }

    public function store(Request $request, string $deck): RedirectResponse
    {
        $counts = $this->importFor($request, $deck);
        $request->session()->forget('import_draft');

        $parts = array_filter([
            $counts['new'] > 0 ? ($counts['new'] === 1 ? '1 Karte hinzugefügt' : "{$counts['new']} Karten hinzugefügt") : null,
            $counts['update'] > 0 ? ($counts['update'] === 1 ? '1 Karte geändert' : "{$counts['update']} Karten geändert") : null,
        ]);

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $parts === [] ? 'Nichts geändert – alles war schon so.' : implode(', ', $parts).'.',
        ]);
    }

    /** The deck to import into: one of the user's own. */
    protected function deck(Request $request, string $id): Deck
    {
        return $this->decks->getOwned($request->user(), $id);
    }

    /** Where the forms of the import pages post to. */
    protected function base(Deck $deck): string
    {
        return "/decks/{$deck->id}/import";
    }

    /** @return array{new: int, update: int} */
    protected function importFor(Request $request, string $deck): array
    {
        return $this->import->import($request->user(), $deck, $request->only('text'));
    }
}
