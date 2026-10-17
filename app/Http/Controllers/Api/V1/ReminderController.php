<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ReminderService;
use Illuminate\Http\JsonResponse;

/**
 * Sends the reminders due now (docs/specs/schreiben-und-erinnern.md, B3).
 * Called every 15 minutes by an outside scheduler; guarded by
 * reminder.token, not by a login or app token. Answers numbers only.
 */
class ReminderController extends Controller
{
    public function __invoke(ReminderService $reminders): JsonResponse
    {
        return response()->json($reminders->run());
    }
}
