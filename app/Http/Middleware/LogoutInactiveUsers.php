<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wer gesperrt wird, fliegt bei der naechsten Anfrage raus — nicht erst,
 * wenn seine Sitzung irgendwann ablaeuft.
 *
 * Haengt an JEDER Web-Route (bootstrap/app.php), nicht nur an einzelnen
 * Gruppen. So kann man es beim Anlegen einer neuen Route nicht vergessen.
 */
class LogoutInactiveUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $next($request);
    }
}
