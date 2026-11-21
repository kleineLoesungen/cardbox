<?php

namespace App\Http\Controllers;

use App\Services\DeckService;
use App\Services\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Meine Kinder": the progress of the children (docs/specs/eltern-ansicht.md,
 * B) and, for parents of 18 or older, their decks (eltern-konten.md).
 * GuardianService and DeckService check the link; everything else answers 404.
 */
class ChildController extends Controller
{
    public function __construct(private GuardianService $guardians, private DeckService $decks) {}

    public function index(Request $request): View|RedirectResponse
    {
        $children = $this->guardians->childrenOf($request->user());
        // With one child the menu item leads straight to it.
        if ($children->count() === 1) {
            return redirect("/children/{$children->first()->id}");
        }

        return view('children.index', ['children' => $children]);
    }

    public function show(Request $request, string $user): View
    {
        $child = $this->guardians->getChild($request->user(), $user);

        $manages = $this->guardians->isGuardianOf($request->user(), $child);

        return view('children.show', [
            'child' => $child,
            // With one child "Meine Kinder" opens this page directly, so there is nothing to go back to.
            'backToList' => $this->guardians->childCount($request->user()) > 1,
            'overview' => $this->guardians->overview($child),
            // Looking after the decks: parents of 18 or older only.
            'manages' => $manages,
            'childDecks' => $manages ? $this->decks->childDecks($request->user(), $child->id) : collect(),
            'sharedWithChild' => $manages ? $this->decks->sharedWithChild($request->user(), $child->id) : collect(),
            'reviewPending' => $manages ? $this->decks->reviewPending($child) : collect(),
        ]);
    }

    /** "Neuer Stapel für Mia". */
    public function storeDeck(Request $request, string $user): RedirectResponse
    {
        $deck = $this->decks->createForChild($request->user(), $user, $request->only('name', 'description', 'front_language', 'back_language'));

        return redirect("/decks/{$deck->id}?new=1");
    }

    /** "Ablehnen" / "Entfernen" of a deck others gave the child. */
    public function declineDeck(Request $request, string $user, string $deck): RedirectResponse
    {
        $model = $this->decks->declineForChild($request->user(), $user, $deck);

        return redirect("/children/{$user}")->with('toast', ['kind' => 'success', 'message' => "„{$model->name}“ ist nicht mehr bei deinem Kind."]);
    }

    /** "So lassen" / "Privat machen" for a deck the child shared before (eltern-konten.md, F). */
    public function reviewDeck(Request $request, string $user, string $deck): RedirectResponse
    {
        $keep = $request->boolean('keep');
        $model = $this->decks->review($request->user(), $user, $deck, $keep);

        return redirect("/children/{$user}")->with('toast', [
            'kind' => 'success',
            'message' => $keep ? "„{$model->name}“ bleibt geteilt." : "„{$model->name}“ ist jetzt privat.",
        ]);
    }
}
