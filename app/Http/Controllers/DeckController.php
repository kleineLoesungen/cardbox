<?php

namespace App\Http\Controllers;

use App\Enums\Direction;
use App\Enums\StudyPlan;
use App\Services\CardCsvService;
use App\Services\CardSelectionService;
use App\Services\CardService;
use App\Services\CardSuggestionService;
use App\Services\DeckRunService;
use App\Services\DeckService;
use App\Services\LearningOverviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;

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
        private DeckRunService $runs,
        private CardSuggestionService $suggestions,
        private CardSelectionService $selection,
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
                ? $this->decks->mine($user, $search)
                : $this->decks->discover($user, $search, $language),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $deck = $this->decks->create($request->user(), $request->only('name', 'description', 'front_language', 'back_language'));

        // ?new opens the "add card" sheet right away.
        return redirect("/decks/{$deck->id}?new=1");
    }

    /**
     * The deck page. ?run= picks one of the viewer's runs (unknown ids fall
     * back to the oldest), ?direction= the direction.
     */
    public function show(Request $request, string $deck): View
    {
        $user = $request->user();
        $model = $this->decks->getVisible($user, $deck);
        $isOwner = $model->isOwnedBy($user);
        $direction = Direction::tryFrom($this->queryText($request, 'direction')) ?? Direction::Forward;
        $cards = $this->cards->listForDeck($model);
        $runs = $this->runs->listOwned($user, $model);
        // Learning only part of the deck (issue #10): null = every card.
        $activeIds = $this->selection->activeIds($user, $model);
        $run = $runs->firstWhere('id', $this->queryText($request, 'run')) ?? $runs->first();

        return view('decks.show', [
            'deck' => $model,
            'direction' => $direction,
            'runs' => $runs,
            'run' => $run,
            'standardKey' => $this->runs->standardKey($user),
            'presets' => StudyPlan::cases(),
            // Only needed when there is something to learn.
            'activeIds' => $activeIds,
            'boxes' => $cards->isEmpty() ? null : $this->overview->boxes($user, $model, $run, $direction, $activeIds === null ? $cards->count() : count($activeIds)),
            'cards' => $cards,
            'isOwner' => $isOwner,
            'isFollowing' => ! $isOwner && $this->decks->isFollowing($user, $model),
            'followerCount' => $this->decks->followerCount($model),
            'openAddSheet' => $isOwner && $request->boolean('new'),
            'changedOriginals' => $this->cards->pendingOriginalCount($user, $model),
            'openSuggestions' => $this->suggestions->openCountForDeck($user, $model),
            // Status of the viewer's own suggestions, shown at the cards.
            'mySuggestions' => $isOwner ? [] : $this->suggestions->latestByUser($user, $cards),
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

    /** htmx: the cards in one box of a run, for the sheet behind the box bar. */
    public function box(Request $request, string $deck, string $box): View
    {
        $direction = Direction::tryFrom($this->queryText($request, 'direction')) ?? Direction::Forward;
        $result = $this->overview->cardsInBox($request->user(), $deck, $this->queryText($request, 'run'), $direction, (int) $box);

        return view('decks.partials.box-cards', [
            'deckId' => $deck,
            'runId' => $result['run']->id,
            'direction' => $direction,
            'boxCount' => $result['run']->box_count,
            'box' => (int) $box,
            'entries' => $result['entries'],
        ]);
    }

    /** "Als CSV exportieren": any deck the user can see, like "Kopieren". */
    public function export(Request $request, string $deck, CardCsvService $csv): Response
    {
        $model = $this->decks->getVisible($request->user(), $deck);

        // The name is a slug, so plain ASCII: it serves as its own fallback.
        $name = $csv->fileName($model);

        return response($csv->export($model), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $name, $name),
            // A download of the cards as they are now; never cache it.
            'Cache-Control' => 'no-store',
        ]);
    }

    public function copy(Request $request, string $deck): RedirectResponse
    {
        $copy = $this->decks->copy($request->user(), $deck);

        return redirect("/decks/{$copy->id}")->with('toast', ['kind' => 'success', 'message' => 'Kopie angelegt. Sie gehört jetzt dir.']);
    }
}
