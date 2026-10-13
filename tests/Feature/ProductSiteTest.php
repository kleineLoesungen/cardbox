<?php

/*
 * The product page in site/ and the license (docs/specs/csv-und-produktseite.md,
 * stage 2). The page is static; these checks keep it honest.
 */

it('steht unter der MIT-Lizenz', function () {
    expect(file_get_contents(base_path('LICENSE')))->toContain('MIT License')->toContain('Sebastian Willer')
        ->and(json_decode((string) file_get_contents(base_path('composer.json')), true)['license'])->toBe('MIT');
});

it('lädt nichts von fremden Servern außer dem Changelog von GitHub', function () {
    $html = (string) file_get_contents(base_path('site/index.html'));

    // Resources the browser fetches by itself: src=, <link href=>, fetch(), url().
    preg_match_all('/(?:src=|<link[^>]+href=|fetch\(|url\()\s*["\']?(https?:\/\/[^"\'\s)]+)/i', $html, $m);
    $hosts = array_unique(array_map(fn (string $url) => parse_url($url, PHP_URL_HOST), $m[1]));

    expect(array_values($hosts))->toBe([])
        ->and($html)->toContain("'https://raw.githubusercontent.com/kleineLoesungen/cardbox/main/CHANGELOG.md'");
});

it('lässt Migrationszeilen im Changelog-Ausschnitt weg und nutzt dasselbe Icon wie die App', function () {
    $html = (string) file_get_contents(base_path('site/index.html'));

    expect($html)->toContain('!/^Migration:/i.test(')
        ->and(file_get_contents(base_path('site/icon.svg')))->toBe(file_get_contents(base_path('resources/icons/icon.svg')));
});
