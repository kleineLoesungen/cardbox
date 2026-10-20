<?php

namespace App\Support;

/**
 * Short codes people pass on by hand: for parents (GuardianService) and for
 * duels (DuelService). Eight characters without look-alikes (0/O, 1/I/L),
 * shown as "XXXX-XXXX"; about 10¹² possibilities.
 *
 * Stored only as a keyed hash: 31⁸ codes are few enough to try all against a
 * plain sha256 from a leaked backup.
 */
final class ShareCode
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const LENGTH = 8;

    /** A new random code, without the hyphen. */
    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /** "K7P2M9QX" → "K7P2-M9QX". */
    public static function format(string $code): string
    {
        return substr($code, 0, 4).'-'.substr($code, 4);
    }

    /**
     * What someone typed, without spaces and hyphens, upper case: '' when
     * nothing was typed, null when it cannot be a code.
     */
    public static function normalize(mixed $input): ?string
    {
        $code = is_string($input) ? strtoupper((string) preg_replace('/[\s-]+/u', '', $input)) : '';
        if ($code === '') {
            return '';
        }

        return strlen($code) === self::LENGTH && strspn($code, self::ALPHABET) === self::LENGTH ? $code : null;
    }

    public static function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
