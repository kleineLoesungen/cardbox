<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * One rule for "Zurück" in the whole app (docs/specs/navigation.md):
 *
 * * Sections (Bereiche) are the pages of the tab bar and the menu: Heute,
 *   Stapel, Wörterbuch, Mein Konto, … They have no back link.
 * * Visiting a section remembers it (with its query, e.g. the "Entdecken"
 *   tab) in the session. A page below a section leads back there and keeps
 *   that section marked in the tab bar — a deck opened from "Heute" goes
 *   back to "Heute", opened from "Stapel" back to "Stapel".
 * * Pages below a page (e.g. "Karten auswählen" below a deck) lead back to
 *   that page, written in their template.
 */
final class Navigation
{
    private const SESSION_KEY = 'nav.section';

    /** Sections of the tab bar on phones; the others are under "Mehr". */
    public const TAB_BAR = ['learn', 'decks', 'dictionary'];

    /** Pages with a fixed place, whatever was visited before (path prefix => section). */
    private const FIXED = [
        'learn/settings' => 'account',
        'admin/users/' => 'admin/users',
        'admin/tokens/' => 'admin/tokens',
        'moderation/' => 'moderation',
        'children/' => 'children',
    ];

    /** Section key => [path, label]. The key is also the path without the slash. */
    public const SECTIONS = [
        'learn' => ['/learn', 'Heute'],
        'decks' => ['/decks', 'Stapel'],
        'dictionary' => ['/dictionary', 'Wörterbuch'],
        'help' => ['/help', 'Hilfe'],
        'account' => ['/account', 'Mein Konto'],
        'children' => ['/children', 'Meine Kinder'],
        'moderation' => ['/moderation', 'Moderation'],
        'admin/users' => ['/admin/users', 'Benutzer'],
        'admin/tokens' => ['/admin/tokens', 'Tokens'],
        'admin/system' => ['/admin/system', 'System'],
    ];

    /** Remembers the section of a full page view; called by RememberSection. */
    public static function remember(Request $request): void
    {
        $key = $request->path();
        if (isset(self::SECTIONS[$key])) {
            $query = $request->getQueryString();
            $request->session()->put(self::SESSION_KEY, ['key' => $key, 'url' => self::SECTIONS[$key][0].($query ? '?'.$query : '')]);
        }
    }

    /**
     * The section the current page belongs to: the page itself if it is
     * one, a fixed one for pages with a fixed place, else the remembered one.
     */
    public static function current(Request $request): ?string
    {
        $path = $request->path();
        if (isset(self::SECTIONS[$path])) {
            return $path;
        }
        foreach (self::FIXED as $prefix => $key) {
            if ($path === rtrim($prefix, '/') || str_starts_with($path, $prefix)) {
                return $key;
            }
        }
        $remembered = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        if (is_array($remembered) && isset(self::SECTIONS[$remembered['key'] ?? ''])) {
            return $remembered['key'];
        }

        return str_starts_with($path, 'decks/') ? 'decks' : null;
    }

    /**
     * Where "Zurück" leads from a page below a section: the remembered
     * section, or `$default` when none is remembered (e.g. a shared link).
     *
     * @return array{url: string, label: string}
     */
    public static function back(Request $request, string $default): array
    {
        $remembered = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;
        if (is_array($remembered) && isset(self::SECTIONS[$remembered['key'] ?? ''], $remembered['url'])) {
            return ['url' => (string) $remembered['url'], 'label' => self::SECTIONS[$remembered['key']][1]];
        }

        return ['url' => self::SECTIONS[$default][0], 'label' => self::SECTIONS[$default][1]];
    }
}
