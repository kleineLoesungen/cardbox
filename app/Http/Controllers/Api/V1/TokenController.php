<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gibt Name, Berechtigungen und Ablauf des benutzten Tokens zurueck —
 * gut geeignet, um eine Einrichtung zu pruefen. Kein Scope noetig.
 */
class TokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = ApiToken::fromRequest($request);

        return response()->json(['data' => [
            'id' => $token->id,
            'name' => $token->name,
            'scopes' => $token->scopes,
            'expires_at' => $token->expires_at?->toIso8601ZuluString(),
        ]]);
    }
}
