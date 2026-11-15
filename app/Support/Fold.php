<?php

namespace App\Support;

/**
 * Searching without caring about accents: "eleve" finds "élève", "garcon"
 * finds "garçon", "coeur" finds "cœur", "Strasse" finds "Straße" and the
 * other way round. The same rules fold the search term in PHP and the stored
 * text in SQL (replace() and translate(), no extension needed: hosted
 * PostgreSQL rarely allows "unaccent").
 *
 * Umlauts both ways: "schuler" and "Schueler" find "Schüler" — the caller
 * matches either fold (`umlauts: false` turns ü into u, `true` into ue).
 *
 * Only the letters: case stays with ILIKE as before, so letters outside the
 * tables (Cyrillic, Greek …) are found just as they were, whatever lower()
 * of the server knows.
 */
final class Fold
{
    /** Letters written with two: always, before the single ones. */
    private const PAIRS = ['ß' => 'ss', 'ẞ' => 'SS', 'œ' => 'oe', 'Œ' => 'OE', 'æ' => 'ae', 'Æ' => 'AE'];

    /** Umlauts as German writes them without the dots (`umlauts: true`). */
    private const UMLAUTS = ['ä' => 'ae', 'Ä' => 'AE', 'ö' => 'oe', 'Ö' => 'OE', 'ü' => 'ue', 'Ü' => 'UE'];

    private const FROM = 'ÀÁÂÃÄÅĀĂĄÇĆČĎÈÉÊËĒĖĘĚÌÍÎÏĪĮŁÑŃŇÒÓÔÕÖØŌŐŘŚŠŞŤÙÚÛÜŪŮŰŲÝŸŽŹŻĞİȘȚŢàáâãäåāăąçćčďèéêëēėęěìíîïīįłñńňòóôõöøōőřśšşťùúûüūůűųýÿžźżğıșțţ';

    private const TO = 'AAAAAAAAACCCDEEEEEEEEIIIIIILNNNOOOOOOOORSSSTUUUUUUUUYYZZZGISTTaaaaaaaaacccdeeeeeeeeiiiiiilnnnoooooooorssstuuuuuuuuyyzzzgistt';

    /** The term folded, for an ILIKE pattern against sql() with the same `$umlauts`. */
    public static function text(string $text, bool $umlauts = false): string
    {
        return strtr(strtr($text, self::pairs($umlauts)), self::map());
    }

    /** SQL folding `$expression` (a column or expression, never user input). */
    public static function sql(string $expression, bool $umlauts = false): string
    {
        $sql = $expression;
        foreach (self::pairs($umlauts) as $from => $to) {
            $sql = "replace({$sql}, '{$from}', '{$to}')";
        }

        return "translate({$sql}, '".self::FROM."', '".self::TO."')";
    }

    /**
     * Condition and bindings: one meaning of the JSON list `$column` (a fixed
     * column name, never user input) contains `$term` in either fold. Single
     * meanings, not the JSON text: no hits across meanings or on quotes.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function anyMeaning(string $column, string $term): array
    {
        $pattern = fn (bool $umlauts) => '%'.addcslashes(self::text($term, $umlauts), '\\%_').'%';

        return [
            "exists (select 1 from jsonb_array_elements_text({$column}) m where ".self::sql('m').' ilike ? or '.self::sql('m', true).' ilike ?)',
            [$pattern(false), $pattern(true)],
        ];
    }

    /** @return array<string, string> */
    private static function pairs(bool $umlauts): array
    {
        return $umlauts ? self::PAIRS + self::UMLAUTS : self::PAIRS;
    }

    /** @return array<string, string> */
    private static function map(): array
    {
        static $map = null;

        return $map ??= array_combine(mb_str_split(self::FROM), mb_str_split(self::TO));
    }
}
