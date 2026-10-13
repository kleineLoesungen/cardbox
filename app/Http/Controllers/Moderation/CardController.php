<?php

namespace App\Http\Controllers\Moderation;

use App\Http\Controllers\Controller;
use App\Services\CardService;
use App\Services\CardSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Moderation corrects cards in shared decks of other people (issue #6).
 * Route group `role:moderator`; uses the system-wide `…Shared` flavour of
 * CardService, which only touches shared decks. Never deletes single cards.
 */
class CardController extends Controller
{
    public function __construct(private CardService $cards, private CardSuggestionService $suggestions) {}

    /** htmx: the edit sheet, as for the owner but marked as moderation. */
    public function edit(Request $request, string $deck, string $card): View
    {
        $model = $this->cards->getShared($deck, $card);

        return view('partials.card-edit', [
            'deck' => $model->deck,
            'card' => $model,
            'followerCount' => 0,
            'moderation' => true,
        ]);
    }

    /** htmx: the card row replaces itself; the sheet closes. */
    public function update(Request $request, string $deck, string $card): Response
    {
        $model = $this->cards->updateShared($deck, $card, $request->only('front', 'back', 'hint'));

        Log::info('Karte korrigiert (Moderation)', ['by' => $request->user()->id, 'card_id' => $model->id, 'deck_id' => $model->deck_id]);

        return response()
            ->view('partials.card-moderated', [
                'deck' => $model->deck,
                'card' => $model,
                'suggestion' => $this->suggestions->latestByUser($request->user(), $model->newCollection([$model]))[$model->id] ?? null,
            ])
            ->header('HX-Trigger', 'sheet-close');
    }
}
