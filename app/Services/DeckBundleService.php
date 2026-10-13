<?php

namespace App\Services;

use App\Enums\Language;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Several decks as one ZIP file: export a selection, import it again.
 * See docs/specs/stapel-export.md (issue #9).
 *
 * The ZIP holds "stapel.csv" (one line per deck: file, ID, name,
 * description, languages, number of cards) and one card CSV per deck in the
 * format of CardCsvService. Importing updates the person's own deck with
 * that ID, or their own copy of it (decks.source_deck_id), and creates a
 * new private deck otherwise. Nothing is ever deleted; progress stays.
 *
 * @phpstan-type PlanEntry array{
 *     file: string, name: string, description: string|null,
 *     front: string|null, back: string|null,
 *     action: 'update'|'new'|'skip', deck: Deck|null, copy: bool,
 *     sourceId: string|null, reason: string|null, rename: array{from: string, to: string}|null,
 *     counts: array{new: int, update: int, same: int}, errors: int, text: string
 * }
 */
class DeckBundleService
{
    /** Name of the overview file inside the ZIP. */
    public const OVERVIEW = 'stapel.csv';

    private const OVERVIEW_HEADER = ['Datei', 'ID', 'Name', 'Beschreibung', 'Vorne', 'Hinten', 'Karten'];

    public function __construct(private DeckService $decks, private CardCsvService $csv) {}

    /** Whether this PHP can read and write ZIP files at all. */
    public function available(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * Decks the person may export: own and followed ones, by name.
     *
     * @return Collection<int, Deck>
     */
    public function exportable(User $user): Collection
    {
        return $this->decks->mine($user);
    }

    /**
     * Writes the ZIP for the chosen decks to a temporary file and returns
     * its path; the caller sends and deletes it. Ids that are not among the
     * person's own or followed decks are ignored.
     *
     * @param  list<string>  $deckIds
     *
     * @throws ValidationException
     */
    public function export(User $user, array $deckIds): string
    {
        $this->ensureAvailable();

        $decks = $this->decks->mineQuery($user)
            ->whereIn('id', array_values(array_filter($deckIds, fn ($id) => Str::isUuid($id))))
            ->withCount('cards')
            ->orderBy('name')
            ->get();
        if ($decks->isEmpty()) {
            throw ValidationException::withMessages(['decks' => 'Bitte mindestens einen Stapel wählen.']);
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'cardbox-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $overview = [self::OVERVIEW_HEADER];
        $used = [];
        foreach ($decks as $deck) {
            $file = $this->uniqueName($this->csv->fileName($deck), $used);
            $zip->addFromString($file, $this->csv->export($deck));
            $overview[] = [$file, $deck->id, $deck->name, $deck->description ?? '', $deck->front_language, $deck->back_language, (string) $deck->cards_count];
        }
        $zip->addFromString(self::OVERVIEW, $this->table($overview));
        $zip->close();

        return $path;
    }

    /** File name for the download, e.g. "cardbox-stapel-2026-10-14.zip". */
    public function fileName(): string
    {
        return 'cardbox-stapel-'.now()->format('Y-m-d').'.zip';
    }

    /**
     * What an import would do, deck by deck. Writes nothing.
     *
     * @return list<PlanEntry>
     *
     * @throws ValidationException
     */
    public function plan(User $user, string $zipPath): array
    {
        $files = $this->read($zipPath);
        $plan = isset($files[self::OVERVIEW])
            ? $this->planFromOverview($user, $files)
            : $this->planWithoutOverview($user, $files);

        if ($plan === []) {
            throw ValidationException::withMessages(['file' => 'In der Datei wurde kein Stapel gefunden.']);
        }

        return $plan;
    }

    /**
     * Imports everything the plan does not skip, in one transaction.
     *
     * @return array{updated: int, created: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function import(User $user, string $zipPath): array
    {
        $plan = $this->plan($user, $zipPath);
        $todo = array_filter($plan, fn (array $entry) => $entry['action'] !== 'skip');
        if ($todo === []) {
            throw ValidationException::withMessages(['file' => 'Kein Stapel der Datei lässt sich importieren.']);
        }

        DB::transaction(function () use ($user, $todo) {
            foreach ($todo as $entry) {
                $input = [
                    'name' => $entry['name'],
                    'description' => $entry['description'],
                    'front_language' => $entry['front'],
                    'back_language' => $entry['back'],
                ];
                if ($entry['action'] === 'update' && $entry['deck'] !== null) {
                    $deck = $this->decks->updateOwned($user, $entry['deck']->id, $input);
                } else {
                    $deck = $this->decks->create($user, $input);
                    // Never user input, so not fillable (as when copying).
                    $deck->forceFill(['source_deck_id' => $entry['sourceId']])->save();
                }
                $this->csv->import($deck, $user, $entry['text'], allowEmpty: true);
            }
        });

        return [
            'updated' => count(array_filter($todo, fn (array $e) => $e['action'] === 'update')),
            'created' => count(array_filter($todo, fn (array $e) => $e['action'] === 'new')),
            'skipped' => count($plan) - count($todo),
        ];
    }

    // -------------------------------------------------------------------------
    // Reading the ZIP
    // -------------------------------------------------------------------------

    /**
     * The CSV files of the ZIP, by name (without folder), in memory. Checks
     * the size limits before unpacking anything.
     *
     * @return array<string, string>
     *
     * @throws ValidationException
     */
    private function read(string $zipPath): array
    {
        $this->ensureAvailable();

        $maxKb = (int) config('learning.bundle_max_kilobytes');
        if (! is_file($zipPath) || filesize($zipPath) > $maxKb * 1024) {
            throw ValidationException::withMessages(['file' => "Die ZIP-Datei darf höchstens {$maxKb} KB groß sein."]);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw ValidationException::withMessages(['file' => 'Das ist keine lesbare ZIP-Datei.']);
        }

        try {
            $entries = [];
            $unpacked = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string) ($stat['name'] ?? '');
                // macOS adds "__MACOSX/…" and "._…" files; folders end in "/".
                if (str_starts_with($name, '__MACOSX/') || str_ends_with($name, '/') || str_starts_with(basename($name), '.')
                    || ! str_ends_with(mb_strtolower($name), '.csv') || substr_count($name, '/') > 1) {
                    continue;
                }
                $unpacked += (int) ($stat['size'] ?? 0);
                $entries[$i] = basename($name);
            }

            $maxUnpacked = (int) config('learning.bundle_max_unpacked_kilobytes');
            if ($unpacked > $maxUnpacked * 1024) {
                throw ValidationException::withMessages(['file' => "Entpackt ist die Datei größer als {$maxUnpacked} KB."]);
            }
            $maxDecks = (int) config('learning.bundle_max_decks');
            if (count($entries) - (in_array(self::OVERVIEW, $entries, true) ? 1 : 0) > $maxDecks) {
                throw ValidationException::withMessages(['file' => "Höchstens {$maxDecks} Stapel auf einmal."]);
            }

            $files = [];
            foreach ($entries as $index => $name) {
                $content = $zip->getFromIndex($index);
                if (is_string($content) && ! isset($files[$name])) {
                    $files[$name] = $this->toUtf8($content);
                }
            }

            return $files;
        } finally {
            $zip->close();
        }
    }

    // -------------------------------------------------------------------------
    // Planning
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, string>  $files
     * @return list<PlanEntry>
     */
    private function planFromOverview(User $user, array $files): array
    {
        $rows = $this->rows($files[self::OVERVIEW]);
        $header = array_map(fn ($c) => mb_strtolower(trim((string) $c)), array_shift($rows) ?? []);
        $col = fn (string $name) => array_search(mb_strtolower($name), $header, true);

        $plan = [];
        foreach ($rows as $row) {
            $get = fn (string $name) => ($i = $col($name)) === false ? '' : trim((string) ($row[$i] ?? ''));
            $file = $get('Datei');
            if ($file === '') {
                continue;
            }

            $entry = $this->entry(
                $user,
                $file,
                $files[basename($file)] ?? null,
                $get('ID') !== '' ? $get('ID') : null,
                $get('Name') !== '' ? $get('Name') : $this->nameFromFile($file),
                $get('Beschreibung') !== '' ? $get('Beschreibung') : null,
                [$get('Vorne'), $get('Hinten')],
            );
            $plan[] = $entry;
        }

        return $plan;
    }

    /**
     * Hand-made ZIP without overview: every CSV becomes a new deck, named
     * after the file, languages from its header.
     *
     * @param  array<string, string>  $files
     * @return list<PlanEntry>
     */
    private function planWithoutOverview(User $user, array $files): array
    {
        $plan = [];
        foreach ($files as $name => $text) {
            $plan[] = $this->entry($user, $name, $text, null, $this->nameFromFile($name), null, $this->csv->headerLanguages($text) ?? ['', '']);
        }

        return $plan;
    }

    /**
     * One deck of the file: what happens to it, and what its cards would do.
     *
     * @param  array{0: string, 1: string}  $languages
     * @return PlanEntry
     */
    private function entry(User $user, string $file, ?string $text, ?string $id, string $name, ?string $description, array $languages): array
    {
        [$front, $back] = $languages;
        $entry = [
            'file' => $file, 'name' => mb_substr($name, 0, 100), 'description' => $description === null ? null : mb_substr($description, 0, 500),
            'front' => $front !== '' ? $front : null, 'back' => $back !== '' ? $back : null,
            'action' => 'skip', 'deck' => null, 'copy' => false, 'sourceId' => $id !== null && Str::isUuid($id) ? $id : null,
            'reason' => null, 'rename' => null, 'counts' => ['new' => 0, 'update' => 0, 'same' => 0], 'errors' => 0, 'text' => $text ?? '',
        ];

        if ($text === null) {
            return ['reason' => 'Datei fehlt im ZIP.'] + $entry;
        }
        if (! $this->csv->isCsv($text)) {
            return ['reason' => 'Keine Karten-Datei (erste Zeile muss mit „ID;“ beginnen).'] + $entry;
        }

        [$deck, $copy] = $this->target($user, $entry['sourceId']);
        if ($deck !== null) {
            $entry['front'] ??= $deck->front_language;
            $entry['back'] ??= $deck->back_language;
            if ([$entry['front'], $entry['back']] !== [$deck->front_language, $deck->back_language]
                && [$entry['front'], $entry['back']] !== [$deck->back_language, $deck->front_language]) {
                return ['reason' => "Sprachen passen nicht ({$this->pair($entry['front'], $entry['back'])} statt {$this->pair($deck->front_language, $deck->back_language)})."] + $entry;
            }
            // The deck keeps its own language order; the CSV header decides how cards are read.
            $entry['front'] = $deck->front_language;
            $entry['back'] = $deck->back_language;
        } elseif (Language::tryFrom((string) $entry['front']) === null || Language::tryFrom((string) $entry['back']) === null || $entry['front'] === $entry['back']) {
            return ['reason' => 'Sprachen fehlen oder sind ungültig.'] + $entry;
        }

        try {
            // A deck that does not exist yet is planned as an unsaved model: no cards, its languages.
            $result = $this->csv->parse($deck ?? new Deck(['front_language' => $entry['front'], 'back_language' => $entry['back']]), $user, $text);
        } catch (ValidationException $e) {
            return ['reason' => $e->validator->errors()->first()] + $entry;
        }

        return [
            'action' => $deck !== null ? 'update' : 'new',
            'deck' => $deck,
            'copy' => $copy,
            'rename' => $deck !== null && $deck->name !== $entry['name'] ? ['from' => $deck->name, 'to' => $entry['name']] : null,
            'counts' => $result['counts'],
            'errors' => count($result['errors']),
        ] + $entry;
    }

    /**
     * The person's own deck with this id, or else their latest own copy of
     * it; [deck, is a copy].
     *
     * @return array{0: Deck|null, 1: bool}
     */
    private function target(User $user, ?string $id): array
    {
        if ($id === null) {
            return [null, false];
        }

        $own = $user->decks()->find($id);
        if ($own !== null) {
            return [$own, false];
        }

        $copy = $user->decks()->where('source_deck_id', $id)->latest('created_at')->latest('id')->first();

        return [$copy, $copy !== null];
    }

    // -------------------------------------------------------------------------

    private function ensureAvailable(): void
    {
        if (! $this->available()) {
            throw ValidationException::withMessages(['file' => 'ZIP-Dateien sind auf diesem Server nicht verfügbar (PHP-Erweiterung „zip“ fehlt).']);
        }
    }

    /** "englisch-unit-3_de-en.csv" → "Englisch unit 3". */
    private function nameFromFile(string $file): string
    {
        $base = (string) preg_replace('/(_[a-z]+-[a-z]+)?\.csv$/i', '', basename($file));
        $name = trim(str_replace(['-', '_'], ' ', $base));

        return $name === '' ? 'Importierter Stapel' : Str::ucfirst($name);
    }

    private function pair(?string $front, ?string $back): string
    {
        return (Language::tryFrom((string) $front)?->label() ?? (string) $front).' → '.(Language::tryFrom((string) $back)?->label() ?? (string) $back);
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueName(string $name, array &$used): string
    {
        $candidate = $name;
        for ($n = 2; isset($used[$candidate]) || $candidate === self::OVERVIEW; $n++) {
            $candidate = (string) preg_replace('/\.csv$/', "-{$n}.csv", $name);
        }
        $used[$candidate] = true;

        return $candidate;
    }

    /**
     * The overview as CSV text (same conventions as the card CSV).
     *
     * @param  list<list<string>>  $rows
     */
    private function table(array $rows): string
    {
        $out = fopen('php://temp', 'r+');
        assert($out !== false);
        foreach ($rows as $row) {
            fputcsv($out, $row, ';', '"', '', "\r\n");
        }
        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return "\xEF\xBB\xBF".$csv;
    }

    /**
     * Records of the overview; the delimiter is taken from its first line.
     *
     * @return list<list<string|null>>
     */
    private function rows(string $text): array
    {
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $first = strtok($text, "\r\n") ?: '';
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        if (substr_count($first, "\t") > substr_count($first, $delimiter)) {
            $delimiter = "\t";
        }

        $in = fopen('php://temp', 'r+');
        assert($in !== false);
        fwrite($in, $text);
        rewind($in);
        $rows = [];
        while (($row = fgetcsv($in, null, $delimiter, '"', '')) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }
        fclose($in);

        return $rows;
    }

    /** As in CardImportService: Excel's UTF-16 and Windows-1252 become UTF-8. */
    private function toUtf8(string $text): string
    {
        if (str_starts_with($text, "\xFF\xFE")) {
            return (string) mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE');
        }
        if (str_starts_with($text, "\xFE\xFF")) {
            return (string) mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
        }

        return mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
}
