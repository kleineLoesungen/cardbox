<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the deploy hook (POST /api/v1/deploy) with DEPLOY_HOOK_TOKEN.
 *
 * The hook is not an app token: it has to work on the very first deploy,
 * when the database is still empty and nobody could have issued one.
 *
 * Every failure answers 404 — switched off, no token, wrong token — so the
 * response does not reveal whether the hook exists on this installation.
 */
class RequireDeployToken
{
    /** Shorter tokens are treated as not configured. */
    public const MIN_LENGTH = 32;

    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('deploy.hook_token');
        $presented = $request->bearerToken();

        if (! is_string($expected) || strlen($expected) < self::MIN_LENGTH
            || ! is_string($presented) || ! hash_equals($expected, $presented)) {
            abort(404);
        }

        return $next($request);
    }
}
