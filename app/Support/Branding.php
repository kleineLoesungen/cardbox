<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Erscheinungsbild nach aussen: Seitentitel, installierte App, Browserleiste, Mails.
 *
 * Kommt aus Umgebungsvariablen (APP_NAME, APP_SHORT_NAME, PWA_THEME_COLOR,
 * PWA_BACKGROUND_COLOR), gelesen in config/app.php und config/pwa.php.
 * In Templates als `$branding` verfuegbar.
 */
final readonly class Branding
{
    public function __construct(
        public string $name,
        public string $shortName,
        public string $themeColor,
        public string $backgroundColor,
    ) {}

    public static function current(): self
    {
        return new self(
            name: (string) config('app.name'),
            shortName: (string) config('app.short_name'),
            themeColor: strtolower((string) config('pwa.theme_color')),
            backgroundColor: strtolower((string) config('pwa.background_color')),
        );
    }

    /** Erster Buchstabe des Namens, fuer das Logo-Quadrat in der Kopfzeile. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1)) ?: '·';
    }

    /**
     * Streng, weil der Wert unveraendert im Manifest und in einem Meta-Tag landet.
     *
     * @throws InvalidArgumentException
     */
    public static function assertValid(): void
    {
        foreach (['pwa.theme_color' => 'PWA_THEME_COLOR', 'pwa.background_color' => 'PWA_BACKGROUND_COLOR'] as $key => $env) {
            $value = (string) config($key);
            if (! self::isHexColor($value)) {
                throw new InvalidArgumentException("{$env}=\"{$value}\" ist keine Farbe. Erwartet wird #rgb oder #rrggbb.");
            }
        }
    }

    public static function isHexColor(string $value): bool
    {
        return (bool) preg_match('/\A#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})\z/', $value);
    }
}
