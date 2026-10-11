<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verlangt eine Mindestrolle. Benutzung in routes/web.php:
 *
 *     Route::middleware(['auth', 'role:moderator'])->group(…);
 *     Route::middleware(['auth', 'role:admin'])->group(…);
 *
 * Rollen sind hierarchisch: Ein Administrator kommt ueberall hin, wo ein
 * Moderator hindarf. Nie `if ($user->role === …)` im Controller schreiben —
 * die Pruefung gehoert an die Route, dann kann sie niemand vergessen.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $required = Role::from($role);
        $user = $request->user();

        abort_unless($user?->hasRole($required), 403);

        return $next($request);
    }
}
