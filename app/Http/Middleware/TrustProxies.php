<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;

/**
 * Glaubt `X-Forwarded-For` nur, wenn TRUST_PROXY=true gesetzt ist.
 *
 * Hinter einem Reverse-Proxy steht in der TCP-Verbindung nur der Proxy; die
 * echte Adresse liefert dieser Kopf. Den kann aber jeder Client frei setzen —
 * ihm zu glauben ist NUR richtig, wenn ein Proxy davorsteht, der ihn
 * ueberschreibt. Sonst liesse sich die Anmeldebremse pro IP aushebeln.
 */
class TrustProxies extends Middleware
{
    /** @return string|null `'*'` = jedem Proxy glauben, `null` = keinem. */
    protected function proxies()
    {
        return config('app.trust_proxy') ? '*' : null;
    }
}
