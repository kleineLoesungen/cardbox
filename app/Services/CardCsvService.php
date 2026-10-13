<?php

namespace App\Services;

use App\Enums\Language;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cards as CSV: export, and importing an edited export back into a deck.
 * See docs/specs/csv-und-produktseite.md, stage 1.
 *
 * Format: UTF-8 with BOM, ";" separated, CRLF, header
 * "ID;<front language>;<back language>;Hinweis" with the deck's language
 * codes. Several meanings share a cell, separated by " | ".
 *
 * Importing never deletes and never touches the deck itself: a row with the
 * ID of a card in this deck changes that card in place (so its progress
 * stays), everything else becomes a new card.
 *
 * Ownership is checked by the callers (CardImportService); this service
 * gets the deck it may write to.
 *
 * @phpstan-type CsvRow array{line: int, action: 'new'|'update'|'same', card: Card|null, source: Card|null, front: list<string>, back: list<string>, hint: string|null, note: string|null}
 * @phpstan-type CsvResult array{rows: list<CsvRow>, errors: list<array{line: int, text: string, reason: string}>, swapped: bool, counts: array{new: int, update: int, same: int}}
 */
class CardCsvService
{
    /** Separates several meanings inside one cell. */
    public const MEANING_SEPARATOR = ' | ';

    /** Characters that make a spreadsheet treat a cell as a formula. */
    private const FORMULA_START = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(private DeckService $decks) {}

    // -------------------------------------------------------------------------
    // Export
    // -------------------------------------------------------------------------

    /** The whole deck as CSV text, oldest card first. */
    public function export(Deck $deck): string
    {
        $out = fopen('php://temp', 'r+');
        assert($out !== false);

        $this->writeRow($out, ['ID', $deck->front_language, $deck->back_language, 'Hinweis']);
        foreach ($deck->cards()->orderBy('created_at')->orderBy('id')->get() as $card) {
            $this->writeRow($out, [
                $card->id,
                $this->protect(implode(self::MEANING_SEPARATOR, $card->front)),
                $this->protect(implode(self::MEANING_SEPARATOR, $card->back)),
                $this->protect($card->hint ?? ''),
            ]);
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        // BOM: Excel otherwise reads UTF-8 as Windows-1252.
        return "\xEF\xBB\xBF".$csv;
    }

    /** File name for the download, e.g. "englisch-unit-3_de-en.csv". */
    public function fileName(Deck $deck): string
    {
        $name = Str::slug($deck->name) ?: 'stapel';

        return "{$name}_{$deck->front_language}-{$deck->back_language}.csv";
    }

    // -------------------------------------------------------------------------
    // Import
    // -------------------------------------------------------------------------

    /** Whether the text is a CSV with an "ID" header (as exported). */
    public function isCsv(string $text): bool
    {
        return $this->delimiter($text) !== null;
    }

    /**
     * What an import would do, row by row. Writes nothing.
     *
     * @return CsvResult
     *
     * @throws ValidationException
     */
    public function parse(Deck $deck, User $user, string $text): array
    {
        $delimiter = $this->delimiter($text);
        if ($delimiter === null) {
            throw ValidationException::withMessages(['text' => 'Die Datei hat keine Kopfzeile „ID;…“.']);
        }

        $records = $this->read($text, $delimiter);
        $header = array_map(fn ($c) => mb_strtolower(trim((string) $c)), array_shift($records) ?? []);
        $swapped = $this->checkLanguages($deck, $header);
        $hintColumn = $this->hintColumn($header);

        $max = (int) config('learning.import_max_lines');
        if (count($records) > $max) {
            throw ValidationException::withMessages([
                'text' => "Höchstens {$max} Zeilen auf einmal. Diese Datei hat ".count($records).'.',
            ]);
        }

        $own = $deck->cards()->get()->keyBy('id');
        $foreignIds = [];
        foreach ($records as $record) {
            $id = trim((string) ($record[0] ?? ''));
            if (Str::isUuid($id) && ! $own->has($id)) {
                $foreignIds[] = $id;
            }
        }
        // Cards from other decks the person can see: copied with their origin.
        $visible = $foreignIds === [] ? collect() : Card::query()
            ->whereIn('id', array_unique($foreignIds))
            ->whereIn('deck_id', $this->decks->visibleTo($user)->select('id'))
            ->get()
            ->keyBy('id');

        $rows = [];
        $errors = [];
        $seen = [];
        $counts = ['new' => 0, 'update' => 0, 'same' => 0];

        foreach ($records as $index => $record) {
            // Line 1 is the header.
            $line = $index + 2;
            $cells = array_map(fn ($c) => $this->unprotect(trim((string) $c)), $record);
            $raw = implode(';', $cells);

            $id = $cells[0] ?? '';
            $front = $this->meanings($cells[$swapped ? 2 : 1] ?? '');
            $back = $this->meanings($cells[$swapped ? 1 : 2] ?? '');
            $hint = $hintColumn !== null && ($cells[$hintColumn] ?? '') !== '' ? $cells[$hintColumn] : null;

            $reason = $this->problem($front, $back, $hint);
            if ($reason === null && $id !== '' && $own->has($id) && isset($seen[$id])) {
                $reason = 'Diese ID steht schon weiter oben.';
            }
            if ($reason !== null) {
                $errors[] = ['line' => $line, 'text' => $raw, 'reason' => $reason];

                continue;
            }

            $card = $own->get($id);
            if ($card !== null) {
                $seen[$id] = true;
                $action = $card->front === $front && $card->back === $back && $card->hint === $hint ? 'same' : 'update';
                $rows[] = ['line' => $line, 'action' => $action, 'card' => $card, 'source' => null, 'front' => $front, 'back' => $back, 'hint' => $hint, 'note' => null];
                $counts[$action]++;

                continue;
            }

            $source = $id === '' ? null : $visible->get($id);
            $note = match (true) {
                $id === '' => null,
                $source !== null => 'Aus einem anderen Stapel – wird als neue Karte übernommen.',
                default => 'Unbekannte ID – wird neu angelegt.',
            };
            $rows[] = ['line' => $line, 'action' => 'new', 'card' => null, 'source' => $source, 'front' => $front, 'back' => $back, 'hint' => $hint, 'note' => $note];
            $counts['new']++;
        }

        return ['rows' => $rows, 'errors' => $errors, 'swapped' => $swapped, 'counts' => $counts];
    }

    /**
     * Applies the import: new cards are created, changed ones updated in
     * place. Nothing is deleted.
     *
     * @return array{new: int, update: int, same: int}
     *
     * @throws ValidationException
     */
    public function import(Deck $deck, User $user, string $text): array
    {
        $result = $this->parse($deck, $user, $text);
        if ($result['counts']['new'] + $result['counts']['update'] + $result['counts']['same'] === 0) {
            throw ValidationException::withMessages(['text' => 'In der Datei wurde keine gültige Zeile gefunden.']);
        }

        DB::transaction(function () use ($deck, $result) {
            foreach ($result['rows'] as $row) {
                if ($row['action'] === 'update' && $row['card'] !== null) {
                    $row['card']->update(['front' => $row['front'], 'back' => $row['back'], 'hint' => $row['hint']]);
                } elseif ($row['action'] === 'new') {
                    $card = new Card(['front' => $row['front'], 'back' => $row['back'], 'hint' => $row['hint']]);
                    $card->deck_id = $deck->id;
                    if ($row['source'] !== null) {
                        // Never user input, so not fillable (as when adopting a card).
                        $card->forceFill(['source_card_id' => $row['source']->id, 'source_updated_at' => $row['source']->updated_at]);
                    }
                    $card->save();
                }
            }
        });

        return $result['counts'];
    }

    // -------------------------------------------------------------------------

    /**
     * The delimiter right after "ID" in the first non-empty line, or null
     * when the text is not such a CSV.
     */
    private function delimiter(string $text): ?string
    {
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            return preg_match('/^\s*"?id"?\s*([;,\t])/i', $line, $m) === 1 ? $m[1] : null;
        }

        return null;
    }

    /**
     * All records, with quoted fields (";" or line breaks inside quotes).
     *
     * @return list<list<string|null>>
     */
    private function read(string $text, string $delimiter): array
    {
        $in = fopen('php://temp', 'r+');
        assert($in !== false);
        fwrite($in, (string) preg_replace('/^\xEF\xBB\xBF/', '', $text));
        rewind($in);

        $records = [];
        while (($record = fgetcsv($in, null, $delimiter, '"', '')) !== false) {
            // Blank lines come back as [null].
            if ($record === [null] || implode('', array_map('strval', $record)) === '') {
                continue;
            }
            $records[] = $record;
        }
        fclose($in);

        return $records;
    }

    /**
     * Whether the sides are swapped: the header's language codes against the
     * deck's. "Vorne;Hinten" is taken as it is.
     *
     * @param  list<string>  $header  lower case
     *
     * @throws ValidationException
     */
    private function checkLanguages(Deck $deck, array $header): bool
    {
        $codes = [$header[1] ?? '', $header[2] ?? ''];
        if ($codes === ['vorne', 'hinten']) {
            return false;
        }

        $front = Language::tryFrom($codes[0]);
        $back = Language::tryFrom($codes[1]);
        if ($front === null || $back === null) {
            throw ValidationException::withMessages(['text' => 'In der Kopfzeile fehlen die Sprachen, z. B. ID;de;en;Hinweis.']);
        }

        if ($codes === [$deck->front_language, $deck->back_language]) {
            return false;
        }
        if ($codes === [$deck->back_language, $deck->front_language]) {
            return true;
        }

        throw ValidationException::withMessages([
            'text' => "Die Datei ist für {$front->label()} → {$back->label()}, der Stapel ist {$deck->frontLabel()} → {$deck->backLabel()}.",
        ]);
    }

    /**
     * Index of the "Hinweis" column, if there is one.
     *
     * @param  list<string>  $header  lower case
     */
    private function hintColumn(array $header): ?int
    {
        foreach ($header as $i => $name) {
            if ($i >= 3 && in_array($name, ['hinweis', 'hint'], true)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Why a row cannot be imported, or null.
     *
     * @param  list<string>  $front
     * @param  list<string>  $back
     */
    private function problem(array $front, array $back, ?string $hint): ?string
    {
        $maxMeanings = (int) config('learning.max_meanings');
        $maxLength = (int) config('learning.max_meaning_length');
        $maxHint = (int) config('learning.max_hint_length');

        foreach ([...$front, ...$back] as $meaning) {
            if (mb_strlen($meaning) > $maxLength) {
                return "Zu lang (höchstens {$maxLength} Zeichen je Bedeutung).";
            }
        }

        return match (true) {
            $front === [] => 'Vorne fehlt.',
            $back === [] => 'Hinten fehlt.',
            count($front) > $maxMeanings || count($back) > $maxMeanings => "Höchstens {$maxMeanings} Bedeutungen je Seite.",
            $hint !== null && mb_strlen($hint) > $maxHint => "Hinweis zu lang (höchstens {$maxHint} Zeichen).",
            default => null,
        };
    }

    /**
     * One cell → meanings, split at "|".
     *
     * @return list<string>
     */
    private function meanings(string $cell): array
    {
        return CardService::normalizeSide(explode('|', $cell));
    }

    /** Stops spreadsheets from running a cell as a formula. */
    private function protect(string $value): string
    {
        return $value !== '' && in_array($value[0], self::FORMULA_START, true) ? "'".$value : $value;
    }

    /** Removes exactly the "'" that protect() added. */
    private function unprotect(string $value): string
    {
        return strlen($value) > 1 && $value[0] === "'" && in_array($value[1], self::FORMULA_START, true)
            ? substr($value, 1)
            : $value;
    }

    /**
     * @param  resource  $out
     * @param  list<string>  $fields
     */
    private function writeRow($out, array $fields): void
    {
        fputcsv($out, $fields, ';', '"', '', "\r\n");
    }
}
