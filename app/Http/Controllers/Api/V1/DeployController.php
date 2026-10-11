<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DeployService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * POST /api/v1/deploy — called by deploy.sh after the FTP upload.
 * Guarded by RequireDeployToken (route middleware `deploy.token`).
 */
class DeployController extends Controller
{
    public function __invoke(DeployService $deploy): JsonResponse
    {
        $result = $deploy->run();

        Log::info('Deploy-Hook ausgeführt', ['ok' => $result['ok']]);

        return response()->json($result, $result['ok'] ? 200 : 500);
    }
}
