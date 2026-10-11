<?php

namespace App\Http\Controllers;

use App\Services\LeitnerService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Signed-in people go straight to "Heute". */
    public function landing(Request $request, UserService $users): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect('/learn');
        }

        return view('pages.landing', ['firstSetup' => $users->isEmpty()]);
    }

    /** "So funktioniert der Karteikasten" — shows the person's own plan. */
    public function help(Request $request, LeitnerService $leitner): View
    {
        return view('pages.help', ['plan' => $leitner->plan($request->user())]);
    }

    /**
     * Lebende Design-Dokumentation. Oeffentlich, damit man das Aussehen auch
     * vor der Ersteinrichtung pruefen kann.
     */
    public function styleguide(): View
    {
        return view('pages.styleguide', [
            'swatches' => [
                ['class' => 'bg-surface', 'role' => 'Seitenhintergrund'],
                ['class' => 'bg-surface-raised', 'role' => 'Karten, Kopfzeile'],
                ['class' => 'bg-surface-sunken', 'role' => 'Vertiefungen'],
                ['class' => 'bg-border', 'role' => 'Linien'],
                ['class' => 'bg-content', 'role' => 'Text'],
                ['class' => 'bg-muted', 'role' => 'Nebentext'],
                ['class' => 'bg-brand', 'role' => 'Marke'],
                ['class' => 'bg-brand-subtle', 'role' => 'Marke, zurückhaltend'],
                ['class' => 'bg-success', 'role' => 'Erfolg'],
                ['class' => 'bg-warning', 'role' => 'Warnung'],
                ['class' => 'bg-danger', 'role' => 'Fehler'],
                ['class' => 'bg-danger-subtle', 'role' => 'Fehler, Fläche'],
            ],
        ]);
    }
}
