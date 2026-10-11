<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/*
 * Der Waechter ueber routes/web.php und routes/api.php.
 *
 * Wer eine Route anlegt und die Gruppe vergisst, bekommt hier einen roten
 * Test statt eines offenen Endpunkts. Soll eine Route wirklich oeffentlich
 * sein, gehoert sie AUSDRUECKLICH in die Liste unten.
 */

const PUBLIC_ROUTES = [
    'GET /',
    'GET styleguide',
    'GET manifest.webmanifest',
    'GET sw.js',
    'GET offline',
    'GET login',
    'POST login',
    'GET register',
    'POST register',
    'GET api/v1/health',
];

/** @return array<string, Route> */
function applicationRoutes(): array
{
    $routes = [];
    foreach (Router::getRoutes() as $route) {
        $action = $route->getActionName();
        // Nur eigene Controller, keine Routen aus Paketen.
        if (! str_starts_with($action, 'App\\')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD']) as $method) {
            $routes["{$method} {$route->uri()}"] = $route;
        }
    }

    return $routes;
}

it('schützt jede Route, die nicht ausdrücklich öffentlich ist', function () {
    $unprotected = [];

    foreach (applicationRoutes() as $key => $route) {
        if (in_array($key, PUBLIC_ROUTES, true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $isProtected = in_array('auth', $middleware, true)
            || in_array('api.token', $middleware, true)
            || collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'scope:'));

        if (! $isProtected) {
            $unprotected[] = $key;
        }
    }

    expect($unprotected)->toBe([], 'Ungeschützte Routen: '.implode(', ', $unprotected));
});

it('schützt alle Verwaltungsrouten mit der Rolle Administrator', function () {
    foreach (applicationRoutes() as $key => $route) {
        if (str_contains($key, ' admin/')) {
            expect(in_array('role:admin', $route->gatherMiddleware(), true))->toBeTrue("{$key} fehlt role:admin");
        }
    }
});

it('schützt die API nie über Rollen oder Sitzungen', function () {
    foreach (applicationRoutes() as $key => $route) {
        if (str_contains($key, ' api/')) {
            expect($route->gatherMiddleware())->not->toContain('auth')
                ->and(collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'role:')))->toBeFalse();
        }
    }
});

it('führt keine veraltete Ausnahme in der Liste der öffentlichen Routen', function () {
    expect(array_diff(PUBLIC_ROUTES, array_keys(applicationRoutes())))->toBe([]);
});
