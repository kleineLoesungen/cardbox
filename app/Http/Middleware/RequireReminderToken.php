<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards POST /api/v1/reminders/run with REMINDER_TOKEN, sent as
 * "X-Cron-Token: …" by Cronicle, cron-job.org or the host's cron. A custom
 * header, because FastCGI on web spaces drops "Authorization".
 *
 * Every failure answers 404 — switched off, no token, wrong token — like the
 * deploy hook, so nobody learns whether the address exists.
 */
class RequireReminderToken
{
    /** Shorter tokens are treated as not configured. */
    public const MIN_LENGTH = 32;

    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('push.reminder_token');
        $presented = $request->header('X-Cron-Token');

        if (! is_string($expected) || strlen($expected) < self::MIN_LENGTH
            || ! is_string($presented) || ! hash_equals($expected, $presented)) {
            abort(404);
        }

        return $next($request);
    }
}
