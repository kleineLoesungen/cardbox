<?php

namespace App\Http\Controllers;

use App\Services\DeckBundleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Several decks as one ZIP file (issue #9): choose and download, upload,
 * preview, import. All rules are in DeckBundleService.
 *
 * Between preview and import the uploaded ZIP waits on the local disk
 * (storage/app/private/imports), referenced from the session — a file field
 * cannot be sent a second time. It is deleted after the import; files left
 * behind are removed after an hour.
 */
class DeckBundleController extends Controller
{
    private const SESSION_KEY = 'deck_bundle';

    private const FOLDER = 'imports';

    public function __construct(private DeckBundleService $bundles) {}

    public function exportForm(Request $request): View
    {
        return view('decks.export', [
            'available' => $this->bundles->available(),
            'decks' => $this->bundles->exportable($request->user()),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $ids = $request->input('decks');
        $path = $this->bundles->export($request->user(), is_array($ids) ? array_values(array_filter($ids, 'is_string')) : []);

        return response()->download($path, $this->bundles->fileName(), ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function importForm(): View
    {
        return view('decks.import-bundle', ['available' => $this->bundles->available()]);
    }

    public function preview(Request $request): View
    {
        $maxKb = (int) config('learning.bundle_max_kilobytes');
        Validator::make($request->only('file'), [
            'file' => ['required', 'file', "max:{$maxKb}", 'mimes:zip'],
        ], [
            'file.required' => 'Bitte eine ZIP-Datei wählen.',
            'file.mimes' => 'Bitte eine ZIP-Datei wählen.',
            'file.max' => "Die ZIP-Datei darf höchstens {$maxKb} KB groß sein.",
        ])->validate();

        $this->forget($request);
        $this->removeStale();
        $stored = (string) Storage::disk('local')->putFileAs(self::FOLDER, $request->file('file'), Str::uuid().'.zip');
        $request->session()->put(self::SESSION_KEY, $stored);

        try {
            $plan = $this->bundles->plan($request->user(), Storage::disk('local')->path($stored));
        } catch (\Throwable $e) {
            $this->forget($request);

            throw $e;
        }

        return view('decks.import-bundle-preview', ['plan' => $plan]);
    }

    public function store(Request $request): RedirectResponse
    {
        $stored = $request->session()->get(self::SESSION_KEY);
        if (! is_string($stored) || ! Storage::disk('local')->exists($stored)) {
            return redirect('/decks/import')->withErrors(['file' => 'Bitte die Datei noch einmal wählen.']);
        }

        $result = $this->bundles->import($request->user(), Storage::disk('local')->path($stored));
        $this->forget($request);

        $parts = array_filter([
            $result['updated'] > 0 ? ($result['updated'] === 1 ? '1 Stapel aktualisiert' : "{$result['updated']} Stapel aktualisiert") : null,
            $result['created'] > 0 ? ($result['created'] === 1 ? '1 Stapel neu angelegt' : "{$result['created']} Stapel neu angelegt") : null,
            $result['skipped'] > 0 ? ($result['skipped'] === 1 ? '1 übersprungen' : "{$result['skipped']} übersprungen") : null,
        ]);

        return redirect('/decks')->with('toast', ['kind' => 'success', 'message' => implode(', ', $parts).'.']);
    }

    /** Deletes the waiting ZIP of this session. */
    private function forget(Request $request): void
    {
        $stored = $request->session()->pull(self::SESSION_KEY);
        if (is_string($stored) && str_starts_with($stored, self::FOLDER.'/')) {
            Storage::disk('local')->delete($stored);
        }
    }

    /** Uploads nobody imported within an hour. */
    private function removeStale(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files(self::FOLDER) as $file) {
            if ($disk->lastModified($file) < now()->subHour()->getTimestamp()) {
                $disk->delete($file);
            }
        }
    }
}
