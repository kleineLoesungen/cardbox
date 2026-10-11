<?php

namespace App\Http\Middleware;

use App\Enums\Scope;
use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentifizierung der JSON-Schnittstelle ueber App-Tokens.
 *
 * Benutzung in routes/api.php:
 *
 *     ->middleware('api.token')            // irgendein gueltiges Token
 *     ->middleware('scope:notes:read')     // Token MIT diesem Scope
 *
 * `scope:` prueft das Token selbst mit — `api.token` davor ist nicht noetig.
 *
 * Wichtig: Hinter einem Token steht KEIN Benutzer. `$request->user()` bleibt
 * in der API leer. Das Token liegt unter ApiToken::fromRequest($request).
 */
class RequireScope
{
    public function __construct(private ApiTokenService $tokens) {}

    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $token = $request->attributes->get(ApiToken::class);

        if (! $token instanceof ApiToken) {
            $presented = $request->bearerToken();
            $token = $presented ? $this->tokens->authenticate($presented) : null;

            if ($token === null) {
                return self::unauthorized();
            }

            $this->tokens->touch($token);
            $request->attributes->set(ApiToken::class, $token);
        }

        if ($scope !== null) {
            // Tippfehler an der Route sollen laut scheitern, nicht still 403 liefern.
            $required = Scope::from($scope);

            if (! $token->hasScope($required)) {
                return self::missingScope($required);
            }
        }

        return $next($request);
    }

    /** 401 mit `WWW-Authenticate`, wie es RFC 6750 fuer Bearer-Tokens vorsieht. */
    private static function unauthorized(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'unauthorized',
                'message' => 'Gültiges App-Token erforderlich. Header: Authorization: Bearer sk_...',
            ],
        ], 401, ['WWW-Authenticate' => 'Bearer realm="api"']);
    }

    /** 403 mit Angabe des fehlenden Scopes — der Client soll wissen, was ihm fehlt. */
    private static function missingScope(Scope $scope): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'insufficient_scope',
                'message' => "Dieses Token hat die Berechtigung \"{$scope->value}\" nicht.",
                'required_scope' => $scope->value,
            ],
        ], 403, ['WWW-Authenticate' => "Bearer realm=\"api\", scope=\"{$scope->value}\""]);
    }
}
