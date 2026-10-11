<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Scope;
use App\Http\Controllers\Controller;
use App\Services\ApiTokenService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Verwaltung der App-Tokens unter /admin/tokens.
 *
 * Tokens sind Zugaenge der Installation fuer fremde Systeme — sie gehoeren
 * keinem Benutzer, deshalb sehen alle Administratoren dieselbe Liste.
 */
class TokenController extends Controller
{
    public function __construct(private ApiTokenService $tokens) {}

    public function index(): View
    {
        return view('admin.tokens', [
            'tokens' => $this->tokens->list(),
            'scopes' => Scope::cases(),
        ]);
    }

    /**
     * Mehrere Kontrollkaestchen senden `scopes[]=notes:read&scopes[]=notes:write`.
     * Die eckigen Klammern im `name` sind Pflicht — ohne sie kommt nur der
     * LETZTE Wert an, still und ohne Fehlermeldung.
     */
    public function store(Request $request): View
    {
        $created = $this->tokens->create($request->user(), $request->only('name', 'scopes', 'expires_in_days'));

        Log::info('App-Token ausgestellt', [
            'by' => $request->user()->id,
            'token_id' => $created['token']->token_id,
            'scopes' => $created['token']->scopes,
        ]);

        return view('partials.token-created', $created);
    }

    /** Leere Antwort: die Zeile verschwindet aus der Tabelle. */
    public function revoke(Request $request, string $token): Response
    {
        $revoked = $this->tokens->revoke($token);

        Log::info('App-Token widerrufen', ['by' => $request->user()->id, 'id' => $revoked->id]);

        return response('');
    }
}
