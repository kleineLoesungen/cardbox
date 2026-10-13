<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\DeckImportController as OwnDeckImportController;
use App\Models\Deck;
use Illuminate\Http\Request;

/**
 * "Liste einfügen" for administrators: the same pages, but for any shared
 * deck, so they can overwrite other people's cards (e.g. from a CSV).
 * Only reachable through the role:admin route group. Private decks of
 * other people stay invisible here too (DeckService::getShared).
 */
class DeckImportController extends OwnDeckImportController
{
    protected function deck(Request $request, string $id): Deck
    {
        return $this->decks->getShared($id);
    }

    protected function base(Deck $deck): string
    {
        return "/admin/decks/{$deck->id}/import";
    }

    protected function importFor(Request $request, string $deck): array
    {
        return $this->import->importShared($request->user(), $deck, $request->only('text'));
    }
}
