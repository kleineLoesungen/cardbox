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
        // A configuration problem must show in the scheduler (no "sent" → Cronicle reports an error),
        // not as a quiet run with nothing sent.
        $error = $reminders->enabled() ? $reminders->configError() : 'VAPID_PUBLIC_KEY und VAPID_PRIVATE_KEY fehlen.';
        if ($error !== null) {
            return response()->json(['error' => $error], 503);
        }

        return response()->json($reminders->run() + ['scheduled' => $reminders->scheduledCount()]);
    }
}
