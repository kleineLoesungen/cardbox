<?php

namespace App\Support;

/**
 * Searching without caring about accents: "eleve" finds "élève", "garcon"
 * finds "garçon" and the other way round. The same table folds the search
 * term in PHP and the stored text in SQL (translate(), no extension needed:
 * hosted PostgreSQL rarely allows "unaccent").
 *
 * Only the accents: case stays with ILIKE as before, so letters outside the
 * table (Cyrillic, Greek …) are found just as they were, whatever lower()
 * of the server knows. Single letters only — "œ" stays "œ", "ß" stays "ß".
 */
final class Fold
{
    private const FROM = 'ÀÁÂÃÄÅĀĂĄÇĆČĎÈÉÊËĒĖĘĚÌÍÎÏĪĮŁÑŃŇÒÓÔÕÖØŌŐŘŚŠŞŤÙÚÛÜŪŮŰŲÝŸŽŹŻĞİȘȚŢàáâãäåāăąçćčďèéêëēėęěìíîïīįłñńňòóôõöøōőřśšşťùúûüūůűųýÿžźżğıșțţ';

    private const TO = 'AAAAAAAAACCCDEEEEEEEEIIIIIILNNNOOOOOOOORSSSTUUUUUUUUYYZZZGISTTaaaaaaaaacccdeeeeeeeeiiiiiilnnnoooooooorssstuuuuuuuuyyzzzgistt';

    /** The term without accents, for an ILIKE pattern against sql(). */
    public static function text(string $text): string
    {
        return strtr($text, self::map());
    }

    /** SQL removing the accents of `$expression` (a column or expression, never user input). */
    public static function sql(string $expression): string
    {
        return "translate({$expression}, '".self::FROM."', '".self::TO."')";
    }

    /** @return array<string, string> */
    private static function map(): array
    {
        static $map = null;

        return $map ??= array_combine(mb_str_split(self::FROM), mb_str_split(self::TO));
    }
}
