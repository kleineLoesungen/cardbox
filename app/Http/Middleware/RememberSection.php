<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers the section (Heute, Stapel, …) of a full page view, so pages
 * below it lead back there (docs/specs/navigation.md). htmx fragments and
 * non-GET requests are no page views.
 */
class RememberSection
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only a page that was really shown: no 403 for a role one lacks, no redirect.
        if ($request->isMethod('GET') && ! $request->headers->has('HX-Request') && $request->user() !== null && $response->isSuccessful()) {
            Navigation::remember($request);
        }

        return $response;
    }
}
