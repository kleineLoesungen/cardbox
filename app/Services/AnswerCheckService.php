<?php

namespace App\Services;

/**
 * Checks a typed answer against the meanings of a card side
 * (docs/specs/schreiben-und-erinnern.md, A3). Pure logic, no database.
 *
 * * Right: equal after normalising (case, spaces, final punctuation), to any
 *   meaning; parts in brackets are optional ("(to) miss").
 * * Close ("fast richtig"): only accents/umlauts differ, or one typo away
 *   (two from 9 characters on, none under 4). Never for exact-only sides
 *   such as arithmetic results.
 * * Wrong: everything else, and an empty answer.
 *
 * `parts` is the expected text split into runs, `diff` marking characters
 * the answer got wrong — plain text, the view escapes it.
 *
 * `expected` is the text shown (for "close" the matching variant, brackets
 * resolved), `meaning` the card's meaning it came from.
 *
 * @phpstan-type Check array{verdict: 'right'|'close'|'wrong', expected: string, meaning: string, parts: list<array{text: string, diff: bool}>}
 */
class AnswerCheckService
{
    /** Longest answer looked at; anything beyond is cut off. */
    private const MAX_LENGTH = 500;

    private const FOLD = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ğ' => 'g', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ı' => 'i',
        'ł' => 'l', 'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ť' => 't',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u',
        'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z', 'æ' => 'ae', 'œ' => 'oe',
    ];

    /**
     * @param  list<string>  $meanings  the answer side, main meaning first
     * @return Check
     */
    public function check(array $meanings, string $typed, bool $exactOnly = false): array
    {
        $input = self::normalize(mb_substr($typed, 0, self::MAX_LENGTH));

        $close = null;
        foreach ($meanings as $meaning) {
            foreach (self::variants($meaning) as $variant) {
                $target = self::normalize($variant);
                if ($input !== '' && $input === $target) {
                    return ['verdict' => 'right', 'expected' => $meaning, 'meaning' => $meaning, 'parts' => [['text' => $meaning, 'diff' => false]]];
                }
                if ($input === '' || $exactOnly || $target === '') {
                    continue;
                }

                // Only accents or umlauts differ ("Bar" for "Bär", "cafe" for "café"): always close.
                $allowed = self::allowedTypos($target);
                $distance = self::base($input) === self::base($target)
                    ? 0
                    : self::distance(self::chars(self::fold($input)), self::chars(self::fold($target)), $allowed);
                if ($distance <= $allowed && ($close === null || $distance < $close[0])) {
                    $close = [$distance, $variant, $meaning];
                }
            }
        }

        if ($close !== null) {
            $display = self::tidy($close[1]);

            return ['verdict' => 'close', 'expected' => $display, 'meaning' => $close[2], 'parts' => self::diffParts($input, $display)];
        }

        $main = $meanings[0] ?? '';

        return ['verdict' => 'wrong', 'expected' => $main, 'meaning' => $main, 'parts' => [['text' => $main, 'diff' => false]]];
    }

    /** Lower case, single spaces, no final punctuation. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(self::tidy($text));

        // Punctuation at the edges does not count ("Hello!", "¿Qué tal?").
        return trim((string) preg_replace(['/[.!?,;:]+$/u', '/^[¿¡]+/u'], '', $text));
    }

    /** Trimmed, inner spaces folded to one; case kept for display. */
    private static function tidy(string $text): string
    {
        // Composed form, so "é" pasted as "e" + accent equals a typed "é" (intl may be missing on a web space).
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Optional parts in brackets, each on its own: "(to) look (at)" gives
     * "look", "to look", "look at" and "to look at"; "colo(u)r" gives
     * "color" and "colour". At most four groups count; beyond that only
     * all or none.
     *
     * @return list<string>
     */
    private static function variants(string $meaning): array
    {
        $pieces = preg_split('/(\([^)]*\))/u', $meaning, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($pieces === false || ! str_contains($meaning, '(')) {
            return [$meaning];
        }

        $groups = array_keys(array_filter($pieces, fn (string $p) => str_starts_with($p, '(') && str_ends_with($p, ')')));
        $masks = count($groups) <= 4 ? range(0, (1 << count($groups)) - 1) : [0, (1 << count($groups)) - 1];

        $variants = [];
        foreach ($masks as $mask) {
            $text = '';
            foreach ($pieces as $k => $piece) {
                $group = array_search($k, $groups, true);
                $text .= $group === false ? $piece : (($mask >> $group) & 1 ? substr($piece, 1, -1) : '');
            }
            $variants[] = self::tidy($text);
        }

        return array_values(array_unique($variants));
    }

    /** Umlauts spelled out ("ae"), accents dropped. */
    private static function fold(string $text): string
    {
        return strtr($text, self::FOLD);
    }

    /** Umlauts and accents dropped alike ("a" for "ä"). */
    private static function base(string $text): string
    {
        return strtr(strtr($text, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u']), self::FOLD);
    }

    private static function allowedTypos(string $target): int
    {
        $length = mb_strlen($target);

        return match (true) {
            $length < (int) config('learning.typo_min_length') => 0,
            $length >= (int) config('learning.typo_two_length') => 2,
            default => 1,
        };
    }

    /** @return list<string> */
    private static function chars(string $text): array
    {
        return mb_str_split($text);
    }

    /**
     * Edits between two texts — insert, delete, replace, swap two neighbours
     * (optimal string alignment) — but only up to `$max`: anything further
     * is `$max + 1`. Bounded, so long answers cost little: texts differing
     * in length by more than `$max` are not compared at all, and only a band
     * of `$max` around the diagonal is computed.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function distance(array $a, array $b, int $max): int
    {
        if (abs(count($a) - count($b)) > $max) {
            return $max + 1;
        }

        return min($max + 1, self::table($a, $b, $max)[count($a)][count($b)] ?? $max + 1);
    }

    /**
     * The edit table within a band of `$band` around the diagonal; cells
     * outside it are not set (read as "too far").
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return array<int, array<int, int>>
     */
    private static function table(array $a, array $b, int $band): array
    {
        $far = PHP_INT_MAX >> 1;
        $at = fn (array $d, int $i, int $j): int => $d[$i][$j] ?? $far;

        $d = [];
        for ($i = 0; $i <= min(count($a), $band); $i++) {
            $d[$i][0] = $i;
        }
        for ($j = 0; $j <= min(count($b), $band); $j++) {
            $d[0][$j] = $j;
        }
        for ($i = 1; $i <= count($a); $i++) {
            for ($j = max(1, $i - $band); $j <= min(count($b), $i + $band); $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $d[$i][$j] = min($at($d, $i - 1, $j) + 1, $at($d, $i, $j - 1) + 1, $at($d, $i - 1, $j - 1) + $cost);
                if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1]) {
                    $d[$i][$j] = min($d[$i][$j], $at($d, $i - 2, $j - 2) + 1);
                }
            }
        }

        return $d;
    }

    /**
     * The expected text in runs, marking the characters the input got
     * wrong (replaced, missing or swapped).
     *
     * @return list<array{text: string, diff: bool}>
     */
    private static function diffParts(string $input, string $display): array
    {
        $a = self::chars($input);
        $shown = self::chars($display);
        $b = self::chars(mb_strtolower($display));
        if (count($b) !== count($shown)) {
            // Lower-casing changed the length (rare letters): no marks rather than wrong ones.
            return [['text' => $display, 'diff' => false]];
        }

        // Only called for close answers: a narrow band is enough (two typos, or an umlaut spelled out).
        $d = self::table($a, $b, 4);
        $at = fn (int $i, int $j): int => $d[$i][$j] ?? PHP_INT_MAX >> 1;
        $marked = array_fill(0, count($b), false);
        $i = count($a);
        $j = count($b);
        while ($i > 0 || $j > 0) {
            if ($i > 1 && $j > 1 && $a[$i - 1] === $b[$j - 2] && $a[$i - 2] === $b[$j - 1] && $a[$i - 1] !== $b[$j - 1]
                && $at($i, $j) === $at($i - 2, $j - 2) + 1) {
                $marked[$j - 1] = $marked[$j - 2] = true;
                $i -= 2;
                $j -= 2;
            } elseif ($i > 0 && $j > 0 && $at($i, $j) === $at($i - 1, $j - 1) + ($a[$i - 1] === $b[$j - 1] ? 0 : 1)) {
                $marked[$j - 1] = $a[$i - 1] !== $b[$j - 1];
                $i--;
                $j--;
            } elseif ($j > 0 && $at($i, $j) === $at($i, $j - 1) + 1) {
                $marked[$j - 1] = true;   // missing in the input
                $j--;
            } else {
                $i--;                     // extra in the input: nothing to mark in the expected text
            }
        }

        // Punctuation at the edges does not count, so it is never marked.
        foreach ($shown as $k => $char) {
            if (preg_match('/^[.!?,;:¿¡]$/u', $char) === 1 && (preg_match('/^[.!?,;:]*$/u', implode('', array_slice($shown, $k))) === 1
                || preg_match('/^[¿¡]*$/u', implode('', array_slice($shown, 0, $k + 1))) === 1)) {
                $marked[$k] = false;
            }
        }

        $parts = [];
        foreach ($shown as $k => $char) {
            $last = count($parts) - 1;
            if ($last >= 0 && $parts[$last]['diff'] === $marked[$k]) {
                $parts[$last]['text'] .= $char;
            } else {
                $parts[] = ['text' => $char, 'diff' => $marked[$k]];
            }
        }

        return $parts;
    }
}
