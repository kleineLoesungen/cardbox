<?php

namespace App\Http\Controllers\Moderation;

use App\Http\Controllers\Controller;
use App\Services\CardMergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * "Doppelte Karten" (issue #6): duplicate cards in shared decks, and merging
 * them. Route group `role:moderator`; all rules are in CardMergeService.
 */
class DuplicateController extends Controller
{
    public function __construct(private CardMergeService $merge) {}

    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query('page', '1'));

        return view('moderation.duplicates', ['page' => $page, ...$this->merge->groups($page)]);
    }

    /** One group, addressed by any of its cards. */
    public function show(Request $request, string $card): View
    {
        $group = $this->merge->group($card);

        return view('moderation.duplicate', [
            'groupCard' => $card,
            'group' => $group,
            'learners' => $this->merge->learners($group),
        ]);
    }

    public function store(Request $request, string $card): RedirectResponse
    {
        $keep = $request->input('keep');
        $seen = $request->input('cards');
        $result = $this->merge->merge(
            $card,
            is_string($keep) ? $keep : '',
            is_array($seen) ? array_values(array_filter($seen, 'is_string')) : [],
            $request->only('front', 'back', 'hint'),
        );

        Log::info('Doppelte Karten zusammengeführt (Moderation)', ['by' => $request->user()->id, 'kept' => $keep, ...$result]);

        $parts = array_filter([
            $result['linked'] > 0 ? ($result['linked'] === 1 ? '1 Karte verknüpft' : "{$result['linked']} Karten verknüpft") : null,
            $result['deleted'] > 0 ? ($result['deleted'] === 1 ? '1 Karte gelöscht' : "{$result['deleted']} Karten gelöscht") : null,
            $result['learners'] > 0 ? ($result['learners'] === 1 ? 'Lernstand von 1 Person übernommen' : "Lernstand von {$result['learners']} Personen übernommen") : null,
        ]);

        return redirect('/moderation/duplicates')->with('toast', [
            'kind' => 'success',
            'message' => 'Zusammengeführt: '.implode(', ', $parts).'.',
        ]);
    }
}
