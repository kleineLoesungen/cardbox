<?php

namespace App\Auth;

/**
 * Format der App-Tokens.
 *
 *     sk_<token_id>_<secret>
 *        └ 32 Zeichen  └ 43 Zeichen (256 Bit)
 *
 * * `token_id` liegt im Klartext in der Datenbank und dient dem Nachschlagen.
 *   Dadurch braucht die Pruefung genau EINEN indizierten Lookup. Die ID ist
 *   hex-kodiert und enthaelt daher selbst kein `_` — nur deshalb ist das erste
 *   `_` nach dem Praefix eindeutig der Trenner. Das Geheimnis ist
 *   base64url-kodiert und darf `_` enthalten.
 * * `secret` wird nur als SHA-256 gespeichert. Der Klartext ist nach der
 *   Ausgabe unwiederbringlich weg.
 *
 * Warum SHA-256 und nicht Argon2 wie beim Passwort? Weil das Geheimnis 256 Bit
 * Zufall ist und nicht erraten werden kann. Argon2 wuerde jeden API-Aufruf um
 * ~100 ms verlangsamen, ohne Sicherheit zu gewinnen. GitHub handhabt seine
 * Personal Access Tokens genauso.
 */
final class TokenFormat
{
    public const PREFIX = 'sk_';

    /**
     * Erzeugt ein neues Token. Der Klartext existiert nur im Rueckgabewert.
     *
     * @return array{plaintext: string, token_id: string, token_hash: string}
     */
    public static function generate(): array
    {
        $tokenId = bin2hex(random_bytes(16));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [
            'plaintext' => self::PREFIX.$tokenId.'_'.$secret,
            'token_id' => $tokenId,
            'token_hash' => self::hashSecret($secret),
        ];
    }

    /**
     * Zerlegt ein eingehendes Token in `[token_id, secret]`.
     * Liefert `null`, wenn das Format nicht stimmt.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parse(string $token): ?array
    {
        if (! str_starts_with($token, self::PREFIX)) {
            return null;
        }

        $parts = explode('_', substr($token, strlen(self::PREFIX)), 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    public static function hashSecret(string $secret): string
    {
        return hash('sha256', $secret);
    }

    /** Vergleich in konstanter Zeit, damit die Laufzeit nichts ueber den Hash verraet. */
    public static function verifySecret(string $secret, string $storedHash): bool
    {
        return hash_equals($storedHash, self::hashSecret($secret));
    }
}
