<?php

namespace App\Http\Controllers\Moderation;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\CardService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Moderation edits and deletes cards from the dictionary (issue #12): own
 * cards and cards in shared decks of others. Route group `role:moderator`;
 * uses the `…Moderated` flavour of CardService, which never reaches other
 * people's private decks.
 */
class DictionaryCardController extends Controller
{
    public function __construct(private CardService $cards) {}

    public function update(Request $request): Response
    {
        $cards = $this->cards->updateManyModerated($request->user(), $request->input('cards'), $request->input('base'), $request->only('front', 'back', 'hint'));
        $this->log('Karte korrigiert (Moderation)', $request, $cards);

        return $this->dictionaryChanged($cards->count(), 'gespeichert');
    }

    public function destroy(Request $request): Response
    {
        $cards = $this->cards->deleteManyModerated($request->user(), $request->input('cards'));
        $this->log('Karte gelöscht (Moderation)', $request, $cards);

        return $this->dictionaryChanged($cards->count(), 'gelöscht');
    }

    /**
     * One entry per card of somebody else; own cards are no moderation.
     *
     * @param  Collection<int, Card>  $cards
     */
    private function log(string $message, Request $request, Collection $cards): void
    {
        foreach ($cards as $card) {
            if ($card->deck->user_id !== $request->user()->id) {
                Log::info($message, ['by' => $request->user()->id, 'card_id' => $card->id, 'deck_id' => $card->deck_id]);
            }
        }
    }
}
