<?php

namespace App\Http\Controllers;

use App\Enums\Direction;
use App\Services\CardService;
use App\Services\DeckService;
use App\Services\LearningOverviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Decks in the web UI. Thin: input to DeckService, result to the view.
 * Only the owned/visible flavours of the service are used here.
 */
class DeckController extends Controller
{
    public function __construct(
        private DeckService $decks,
        private CardService $cards,
        private LearningOverviewService $overview,
    ) {}

    /** "Meine" and "Entdecken" on one page, switched by ?tab=. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'discover' ? 'discover' : 'mine';
        $search = $this->queryText($request, 'q');
        $language = $this->queryText($request, 'lang');

        return view('decks.index', [
            'tab' => $tab,
            'search' => $search,
            'language' => $language,
            'decks' => $tab === 'mine'
                ? $this->decks->mine($user)
                : $this->decks->discover($user, $search, $language),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $deck = $this->decks->create($request->user(), $request->only('name', 'description', 'front_language', 'back_language'));

        // ?new opens the "add card" sheet right away.
        return redirect("/decks/{$deck->id}?new=1");
    }

    public function show(Request $request, string $deck): View
    {
        $user = $request->user();
        $model = $this->decks->getVisible($user, $deck);
        $isOwner = $model->isOwnedBy($user);
        $direction = Direction::tryFrom($this->queryText($request, 'direction')) ?? Direction::Forward;
        $cards = $this->cards->listForDeck($model);

        return view('decks.show', [
            'deck' => $model,
            'direction' => $direction,
            // Only needed when there is something to learn.
            'boxes' => $cards->isEmpty() ? null : $this->overview->boxes($user, $model, $direction, $cards->count()),
            'cards' => $cards,
            'isOwner' => $isOwner,
            'isFollowing' => ! $isOwner && $this->decks->isFollowing($user, $model),
            'followerCount' => $this->decks->followerCount($model),
            'openAddSheet' => $isOwner && $request->boolean('new'),
        ]);
    }

    public function update(Request $request, string $deck): RedirectResponse
    {
        $this->decks->updateOwned($request->user(), $deck, $request->only('name', 'description', 'front_language', 'back_language'));

        return redirect("/decks/{$deck}")->with('toast', ['kind' => 'success', 'message' => 'Stapel gespeichert.']);
    }

    public function share(Request $request, string $deck): RedirectResponse
    {
        $shared = $request->boolean('shared');
        $this->decks->setSharedOwned($request->user(), $deck, $shared);

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $shared ? 'Der Stapel ist jetzt mit allen geteilt.' : 'Der Stapel ist jetzt privat.',
        ]);
    }

    public function destroy(Request $request, string $deck): RedirectResponse
    {
        $this->decks->deleteOwned($request->user(), $deck);

        return redirect('/decks')->with('toast', ['kind' => 'success', 'message' => 'Stapel gelöscht.']);
    }

    public function follow(Request $request, string $deck): RedirectResponse
    {
        $model = $this->decks->follow($request->user(), $deck);

        return redirect("/decks/{$model->id}")->with('toast', ['kind' => 'success', 'message' => 'Du folgst jetzt diesem Stapel.']);
    }

    public function unfollow(Request $request, string $deck): RedirectResponse
    {
        $this->decks->unfollow($request->user(), $deck);

        return redirect('/decks')->with('toast', ['kind' => 'success', 'message' => 'Du folgst dem Stapel nicht mehr.']);
    }

    /** "Fortschritt zurücksetzen": the user's own progress on this deck, both directions. */
    public function reset(Request $request, string $deck): RedirectResponse
    {
        $this->overview->resetDeck($request->user(), $deck);

        return redirect("/decks/{$deck}")->with('toast', ['kind' => 'success', 'message' => 'Fortschritt zurückgesetzt. Alle Karten sind wieder neu.']);
    }

    /** htmx: the cards in one box, for the sheet behind the box bar. */
    public function box(Request $request, string $deck, string $box): View
    {
        $direction = Direction::tryFrom($this->queryText($request, 'direction')) ?? Direction::Forward;

        return view('decks.partials.box-cards', [
            'deckId' => $deck,
            'direction' => $direction,
            'boxCount' => $this->overview->boxCount($request->user()),
            'box' => (int) $box,
            'entries' => $this->overview->cardsInBox($request->user(), $deck, $direction, (int) $box),
        ]);
    }

    public function copy(Request $request, string $deck): RedirectResponse
    {
        $copy = $this->decks->copy($request->user(), $deck);

        return redirect("/decks/{$copy->id}")->with('toast', ['kind' => 'success', 'message' => 'Kopie angelegt. Sie gehört jetzt dir.']);
    }
}
