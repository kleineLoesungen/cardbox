<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Zustand fuer Loadbalancer und Container-Orchestrierung. Ohne Anmeldung.
 * Prueft auch die Datenbank — ein laufender Prozess ohne Datenbank ist nicht gesund.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (\Throwable $e) {
            Log::error('Health-Check: Datenbank nicht erreichbar', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'degraded', 'database' => 'unreachable'], 503);
        }

        return response()->json(['status' => 'ok', 'database' => 'ok']);
    }
}
