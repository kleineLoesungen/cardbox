<?php

namespace App\Http\Controllers;

use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Installierbare App (PWA): Manifest, Service Worker, Offline-Seite.
 *
 * **Was der Service Worker bewusst NICHT tut:** Seiten zwischenspeichern.
 * Die Anwendung ist angemeldet und serverseitig gerendert — eine gecachte
 * Seite koennte veraltete Daten zeigen oder, schlimmer, nach dem Abmelden
 * noch den Inhalt des vorherigen Benutzers.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $brand = Branding::current();

        return response()->json([
            'id' => '/',
            'name' => $brand->name,
            'short_name' => $brand->shortName,
            'lang' => 'de',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'theme_color' => $brand->themeColor,
            'background_color' => $brand->backgroundColor,
            'icons' => [
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ['src' => '/icons/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Liegt unter /sw.js und nicht unter /js/: Ein Worker steuert nur Seiten
     * unterhalb seines eigenen Pfads.
     */
    public function serviceWorker(): Response
    {
        // Neue Cache-Version, sobald sich Stylesheet oder htmx aendern. Der
        // Browser erkennt daran einen geaenderten Worker und verwirft den alten
        // Zwischenspeicher — nach einem Deployment gibt es kein veraltetes CSS.
        $version = substr(md5(implode('|', array_map(
            fn (string $file) => is_file($file) ? (string) filemtime($file) : '',
            [public_path('css/app.css'), public_path('js/htmx.min.js')],
        ))), 0, 12);

        return response()
            ->view('pwa.service-worker', [
                'cache' => Str::slug(config('app.name')).'-'.$version,
            ])
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            // Bei jedem Laden nachfragen, ob sich der Worker geaendert hat.
            ->header('Cache-Control', 'no-cache');
    }

    public function offline(): View
    {
        return view('pages.offline');
    }
}
