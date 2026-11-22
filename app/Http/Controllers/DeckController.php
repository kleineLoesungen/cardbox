<?php

namespace App\Http\Controllers;

use App\Enums\Direction;
use App\Services\CardCsvService;
use App\Services\CardSelectionService;
use App\Services\CardService;
use App\Services\CardSuggestionService;
use App\Services\DeckPreferenceService;
use App\Services\DeckRunService;
use App\Services\DeckService;
use App\Services\LearningOverviewService;
use App\Services\LeitnerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
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
        private DeckPreferenceService $preferences,
        private LeitnerService $leitner,
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
            // Shared with the viewer by name, waiting for "Lernen" or "Ablehnen".
            'pending' => $tab === 'mine' && $search === '' ? $this->decks->pendingShares($user) : collect(),
            // "Lerne ich", "pausiert" or neither, for sections and badges.
            'states' => $this->decks->learningStates($user),
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
     * back to the oldest), ?direction= the direction of the box tiles.
     */
    public function show(Request $request, string $deck): View
    {
        $user = $request->user();
        // Visible: own, shared with everybody or with the viewer — then the
        // viewer may learn it, with their own progress.
        $canLearn = true;
        try {
            $model = $this->decks->getVisible($user, $deck);
        } catch (ModelNotFoundException $e) {
            // A parent opens a child's private deck to edit it (eltern-konten.md).
            $model = $this->decks->getManaged($user, $deck);
            $canLearn = false;
        }
        $isOwner = $model->isOwnedBy($user);
        $asGuardian = $this->decks->managesAsGuardian($user, $model);
        $canEdit = $isOwner || $asGuardian;
        $withReverse = $this->preferences->withReverse($user, $model);
        // Direction only switches the box tiles, and only when back → front is learned.
        $direction = $withReverse ? (Direction::tryFrom($this->queryText($request, 'direction')) ?? Direction::Forward) : Direction::Forward;
        $cards = $this->cards->listForDeck($model);
        // A parent only edits a child's private deck: no runs, no boxes.
        $runs = $canLearn ? $this->runs->listOwned($user, $model) : collect();
        // Learning only part of the deck (issue #10): null = every card.
        $activeIds = $this->selection->activeIds($user, $model);
        $run = $runs->firstWhere('id', $this->queryText($request, 'run')) ?? $runs->first();
        $isFollowing = ! $isOwner && $canLearn && $this->decks->isFollowing($user, $model);

        // Box tiles for one direction; "Nur diesen Stapel lernen" counts every learned one.
        $boxes = null;
        $learnable = 0;
        if ($cards->isNotEmpty() && $canLearn) {
            $count = $activeIds === null ? $cards->count() : count($activeIds);
            // Other directions only matter for "Nur diesen Stapel lernen", shown to learners.
            $directions = $withReverse && ($isOwner || $isFollowing) ? Direction::cases() : [$direction];
            foreach ($directions as $d) {
                $box = $this->overview->boxes($user, $model, $run, $d, $count);
                $learnable += $box['due'] + $box['new'];
                $boxes = $d === $direction ? $box : $boxes;
            }
        }

        return view('decks.show', [
            'deck' => $model,
            'direction' => $direction,
            'runs' => $runs,
            'run' => $run,
            'withReverse' => $withReverse,
            'typeAnswers' => $this->preferences->typeAnswers($user, $model),
            'learningState' => $isOwner || $isFollowing ? $this->preferences->learningState($user, $model) : null,
            // Cards often answered wrong (runden-und-knifflige-woerter.md, B).
            'hardIds' => $isOwner || $isFollowing ? $this->overview->hardCardIds($user, $model) : [],
            // Only needed when there is something to learn.
            'activeIds' => $activeIds,
            'boxes' => $boxes,
            'learnable' => $learnable,
            'cards' => $cards,
            'isOwner' => $isOwner,
            'isFollowing' => $isFollowing,
            // Parents of the owner edit like the owner (eltern-konten.md); only adults share.
            'asGuardian' => $asGuardian,
            'canEdit' => $canEdit,
            'canLearn' => $canLearn,
            'canShare' => $this->decks->canShare($user, $model),
            'canManageSharing' => $this->decks->canManageSharing($user, $model),
            'followerCount' => $this->decks->followerCount($model),
            // Sharing with single persons (stapel-mit-einzelnen-teilen.md).
            'shares' => $isOwner || $asGuardian ? $this->decks->sharesOwned($user, $model->id) : collect(),
            'sharedWithMe' => ! $isOwner && $this->decks->isSharedWith($user, $model),
            'openAddSheet' => $canEdit && $request->boolean('new'),
            'changedOriginals' => $this->cards->pendingOriginalCount($user, $model),
            'openSuggestions' => $this->suggestions->openCountForDeck($user, $model),
            // Status of the viewer's own suggestions, shown at the cards.
            'mySuggestions' => $isOwner || $asGuardian ? [] : $this->suggestions->latestByUser($user, $cards),
        ]);
    }

    public function update(Request $request, string $deck): RedirectResponse
    {
        $this->decks->updateManaged($request->user(), $deck, $request->only('name', 'description', 'front_language', 'back_language'));

        return redirect("/decks/{$deck}")->with('toast', ['kind' => 'success', 'message' => 'Stapel gespeichert.']);
    }

    /** "Teilen": private, with single persons or with everybody (stapel-mit-einzelnen-teilen.md). */
    public function visibility(Request $request, string $deck): RedirectResponse
    {
        $model = $this->decks->setVisibility($request->user(), $deck, $request->input('visibility'));
        $message = match (true) {
            $model->is_shared => 'Der Stapel ist jetzt mit allen geteilt.',
            $this->decks->sharesOwned($request->user(), $model->id)->isNotEmpty() => 'Der Stapel ist jetzt nur noch mit bestimmten Personen geteilt.',
            default => 'Der Stapel ist jetzt privat.',
        };

        return redirect("/decks/{$model->id}")->with('toast', ['kind' => 'success', 'message' => $message]);
    }

    public function shareWith(Request $request, string $deck): RedirectResponse
    {
        try {
            $person = $this->decks->shareWith($request->user(), $deck, $request->input('username'));
        } catch (ValidationException $e) {
            throw $e->errorBag('share')->redirectTo("/decks/{$deck}");
        }

        return redirect("/decks/{$deck}")
            ->with('toast', ['kind' => 'success', 'message' => "Mit {$person->name} geteilt."])
            ->with('open_share', true);
    }

    public function unshareWith(Request $request, string $deck, string $person): RedirectResponse
    {
        $removed = $this->decks->unshareWith($request->user(), $deck, $person);

        return redirect("/decks/{$deck}")
            ->with('toast', ['kind' => 'success', 'message' => "{$removed->name} hat den Stapel nicht mehr."])
            ->with('open_share', true);
    }

    /** "Ablehnen" / "Stapel entfernen" for a deck shared with the viewer by name. */
    public function decline(Request $request, string $deck): RedirectResponse
    {
        $model = $this->decks->decline($request->user(), $deck);

        return redirect('/decks')->with('toast', ['kind' => 'success', 'message' => "„{$model->name}“ ist nicht mehr in deinen Stapeln."]);
    }

    public function destroy(Request $request, string $deck): RedirectResponse
    {
        $model = $this->decks->deleteManaged($request->user(), $deck);
        // A parent deleting a child's deck goes back to the child.
        $back = $model->user_id === $request->user()->id ? '/decks' : "/children/{$model->user_id}";

        return redirect($back)->with('toast', ['kind' => 'success', 'message' => 'Stapel gelöscht.']);
    }

    public function follow(Request $request, string $deck): RedirectResponse
    {
        $model = $this->decks->follow($request->user(), $deck);

        return redirect("/decks/{$model->id}")->with('toast', ['kind' => 'success', 'message' => 'Du lernst diesen Stapel jetzt. Die Karten kommen unter Heute dran.']);
    }

    public function unfollow(Request $request, string $deck): RedirectResponse
    {
        $this->decks->unfollow($request->user(), $deck);

        return redirect('/decks')->with('toast', ['kind' => 'success', 'message' => 'Du lernst den Stapel nicht mehr.']);
    }

    /** "Antworten eintippen" on or off, for the viewer and this deck. */
    public function typing(Request $request, string $deck): RedirectResponse
    {
        $on = $request->boolean('on');
        $this->preferences->setTypeAnswers($request->user(), $deck, $on);

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $on ? 'Bei diesem Stapel tippst du die Antworten jetzt ein.' : 'Bei diesem Stapel drehst du die Karten jetzt wieder um.',
        ]);
    }

    /** "Lernen" / "Wieder lernen" (on) or "Pausieren" (off) for the viewer and this deck. */
    public function learning(Request $request, string $deck): RedirectResponse
    {
        $on = $request->boolean('on');
        $this->preferences->setLearning($request->user(), $deck, $on);

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $on ? 'Lerne ich: Der Stapel ist jetzt auf „Heute“ dabei.' : 'Pausiert. Der Stapel kommt nicht mehr auf „Heute“.',
        ]);
    }

    /** "Auch rückwärts lernen" on or off, for the viewer and this deck. */
    public function reverse(Request $request, string $deck): RedirectResponse
    {
        $on = $request->boolean('on');
        $this->preferences->setReverse($request->user(), $deck, $on);

        return redirect("/decks/{$deck}")->with('toast', [
            'kind' => 'success',
            'message' => $on ? 'Du lernst diesen Stapel jetzt auch rückwärts.' : 'Du lernst diesen Stapel nur noch vorwärts.',
        ]);
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
            'roundSize' => $this->leitner->roundSize($request->user()),
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
