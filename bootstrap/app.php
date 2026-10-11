<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\LogoutInactiveUsers;
use App\Http\Middleware\RequireScope;
use App\Http\Middleware\TrustProxies;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
            'api.token' => RequireScope::class,
            'scope' => RequireScope::class,
        ]);

        // Gesperrte Konten bei der naechsten Anfrage abmelden — an ALLEN Web-Routen.
        // Vor `auth` einsortiert, sonst liesse `auth` den gesperrten Benutzer noch durch.
        $middleware->web(append: [LogoutInactiveUsers::class]);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: LogoutInactiveUsers::class);

        // X-Forwarded-For nur mit TRUST_PROXY=true glauben.
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);

        // Wer nicht angemeldet ist, landet beim Login. Laravel merkt sich das
        // Ziel (url.intended) und schickt nach dem Anmelden dorthin zurueck.
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // --- JSON-Schnittstelle: Fehler immer als {"error": {...}} -----------
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // Weboberflaeche: Laravels Fehlerseiten unter resources/views/errors/
            }

            if ($e instanceof ValidationException) {
                return response()->json(['error' => [
                    'code' => 'validation_failed',
                    'message' => $e->validator->errors()->first(),
                    'fields' => $e->errors(),
                ]], 422);
            }

            [$status, $code, $message] = match (true) {
                $e instanceof ModelNotFoundException => [404, 'not_found', 'Nicht gefunden'],
                $e instanceof AuthorizationException => [403, 'forbidden', 'Keine Berechtigung'],
                $e instanceof HttpExceptionInterface => [
                    $e->getStatusCode(),
                    match ($e->getStatusCode()) {
                        404 => 'not_found',
                        405 => 'method_not_allowed',
                        default => 'http_error',
                    },
                    $e->getStatusCode() === 404 ? 'Nicht gefunden' : ($e->getMessage() ?: 'Anfrage abgelehnt'),
                ],
                // Serverfehler: Details nur ins Log, nie nach aussen.
                default => [500, 'internal_error', 'Es ist ein interner Fehler aufgetreten.'],
            };

            return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
        });

        // --- htmx: Validierungsfehler als Meldung, ohne Seite umzubauen -------
        //
        // Ohne das wuerde Laravel auf die vorige Seite umleiten, und htmx setzte
        // die GANZE Seite in das Ziel des Formulars ein.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->headers->has('HX-Request')) {
                return null;
            }

            return response()
                ->view('partials.toast-oob', ['toast' => ['kind' => 'danger', 'message' => $e->validator->errors()->first()]], 422)
                ->header('HX-Reswap', 'none');
        });
    })->create();
