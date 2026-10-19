<?php

namespace App\Http\Controllers;

use App\Services\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Meine Kinder": the progress of children who shared it
 * (docs/specs/eltern-ansicht.md, B). Read-only; GuardianService checks the
 * link, everything else answers 404.
 */
class ChildController extends Controller
{
    public function __construct(private GuardianService $guardians) {}

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

        return view('children.show', [
            'child' => $child,
            // With one child "Meine Kinder" opens this page directly, so there is nothing to go back to.
            'backToList' => $this->guardians->childCount($request->user()) > 1,
            'overview' => $this->guardians->overview($child),
        ]);
    }
}
