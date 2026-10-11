<?php

namespace App\Services;

use App\Enums\Language;
use App\Models\Card;
use App\Models\Deck;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * "Liste einfügen": turns pasted text or a small text file into cards.
 *
 * One card per line. Separators, checked in this order: tab (pasting from
 * Excel, Word, Numbers), "=", ";". Each side gets exactly one meaning.
 * A first line holding only language codes (vocaBox format `de;en;tags`)
 * is skipped; if its codes are the deck's languages in reverse order, all
 * rows are swapped. Further columns are ignored.
 *
 * parse() never writes anything — the preview uses it, and import() parses
 * the same text again, so preview and result cannot drift apart.
 */
class CardImportService
{
    public function __construct(private DeckService $decks) {}

    /**
     * Reads the input (text field or uploaded file) into plain text.
     *
     * @param  array<string, mixed>  $input  text, file
     *
     * @throws ValidationException
     */
    public function textFrom(array $input): string
    {
        $maxKb = (int) config('learning.import_max_kilobytes');

        Validator::make($input, [
            'text' => ['nullable', 'string', 'max:'.($maxKb * 1024)],
            'file' => ['nullable', 'file', "max:{$maxKb}", 'mimes:txt,csv,tsv'],
        ], [
            'file.mimes' => 'Bitte eine Textdatei (.txt oder .csv) wählen.',
            'file.max' => "Die Datei darf höchstens {$maxKb} KB groß sein.",
        ])->validate();

        $file = $input['file'] ?? null;
        $text = $file instanceof UploadedFile ? (string) $file->get() : (string) ($input['text'] ?? '');

        $text = $this->toUtf8($text);

        if (trim($text) === '') {
            throw ValidationException::withMessages(['text' => 'Füge eine Liste ein oder wähle eine Datei.']);
        }

        return $text;
    }

    /**
     * @return array{rows: list<array{line: int, front: string, back: string}>, errors: list<array{line: int, text: string, reason: string}>, swapped: bool}
     *
     * @throws ValidationException
     */
    public function parse(Deck $deck, string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $max = (int) config('learning.import_max_lines');
        $nonEmpty = count(array_filter($lines, fn (string $l) => trim($l) !== ''));

        if ($nonEmpty > $max) {
            throw ValidationException::withMessages([
                'text' => "Höchstens {$max} Zeilen auf einmal. Diese Liste hat {$nonEmpty}.",
            ]);
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $swapped = false;
        $headerChecked = false;
        $maxLength = (int) config('learning.max_meaning_length');

        foreach ($lines as $index => $raw) {
            $lineNo = $index + 1;
            $line = trim($raw);
            if ($line === '') {
                continue;
            }

            if (! $headerChecked) {
                $headerChecked = true;
                $header = $this->headerLanguages($line);
                if ($header !== null) {
                    $swapped = $header === [$deck->back_language, $deck->front_language];

                    continue;
                }
            }

            $parts = $this->split($line);
            if ($parts === null) {
                $errors[] = ['line' => $lineNo, 'text' => $line, 'reason' => 'Kein Trennzeichen gefunden. Schreib „vorne = hinten“.'];

                continue;
            }

            [$front, $back] = $swapped ? [$parts[1], $parts[0]] : $parts;

            $reason = match (true) {
                $front === '' => 'Vorne fehlt.',
                $back === '' => 'Hinten fehlt.',
                mb_strlen($front) > $maxLength || mb_strlen($back) > $maxLength => "Zu lang (höchstens {$maxLength} Zeichen je Seite).",
                default => null,
            };
            if ($reason !== null) {
                $errors[] = ['line' => $lineNo, 'text' => $line, 'reason' => $reason];

                continue;
            }

            $key = $front."\u{0}".$back;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $rows[] = ['line' => $lineNo, 'front' => $front, 'back' => $back];
        }

        return ['rows' => $rows, 'errors' => $errors, 'swapped' => $swapped];
    }

    /**
     * Parses again and creates all valid rows as cards in the user's deck.
     * The text goes through the same checks as for the preview (size,
     * encoding), since this request can be sent without a preview.
     *
     * @param  array<string, mixed>  $input  text
     * @return int Number of created cards
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Deck>
     */
    public function import(User $user, string $deckId, array $input): int
    {
        $deck = $this->decks->getOwned($user, $deckId);
        $text = $this->textFrom(['text' => $input['text'] ?? null]);
        $rows = $this->parse($deck, $text)['rows'];

        if ($rows === []) {
            throw ValidationException::withMessages(['text' => 'In der Liste wurde keine gültige Zeile gefunden.']);
        }

        $now = now();
        DB::transaction(function () use ($rows, $deck, $now) {
            foreach (array_chunk($rows, 500) as $chunk) {
                Card::insert(array_map(fn (array $row) => [
                    'id' => (string) Str::uuid(),
                    'deck_id' => $deck->id,
                    'front' => json_encode([$row['front']], JSON_UNESCAPED_UNICODE),
                    'back' => json_encode([$row['back']], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        return count($rows);
    }

    /**
     * Excel's "Unicode text" is UTF-16 with a byte order mark; files saved on
     * Windows are often Windows-1252. Everything ends up as UTF-8 without BOM.
     */
    private function toUtf8(string $text): string
    {
        if (str_starts_with($text, "\xFF\xFE")) {
            $text = (string) mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($text, "\xFE\xFF")) {
            $text = (string) mb_convert_encoding(substr($text, 2), 'UTF-8', 'UTF-16BE');
        } elseif (! mb_check_encoding($text, 'UTF-8')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        return (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
    }

    /**
     * Splits a line into [front, back], or null without a separator.
     *
     * @return array{0: string, 1: string}|null
     */
    private function split(string $line): ?array
    {
        foreach (["\t", '=', ';'] as $separator) {
            if (str_contains($line, $separator)) {
                $parts = explode($separator, $line, $separator === '=' ? 2 : PHP_INT_MAX);

                return [trim($parts[0]), trim($parts[1] ?? '')];
            }
        }

        return null;
    }

    /**
     * The first two columns if the line consists of language codes only
     * ("de;en;tags"), otherwise null.
     *
     * @return array{0: string, 1: string}|null
     */
    private function headerLanguages(string $line): ?array
    {
        $tokens = array_map(fn (string $t) => mb_strtolower(trim($t)), preg_split('/[\t;,=]/', $line) ?: []);
        $tokens = array_values(array_filter($tokens, fn (string $t) => $t !== '' && $t !== 'tags'));

        if (count($tokens) < 2) {
            return null;
        }

        foreach ($tokens as $token) {
            if (Language::tryFrom($token) === null) {
                return null;
            }
        }

        return [$tokens[0], $tokens[1]];
    }
}
