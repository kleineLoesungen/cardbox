<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function landing(UserService $users): View
    {
        return view('pages.landing', ['firstSetup' => $users->count() === 0]);
    }

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        return view('pages.dashboard', [
            'user' => $user,
            'noteCount' => $user->notes()->count(),
            // Nur Administratoren sehen die Tokens — fuer alle anderen null.
            'tokenCount' => $user->isAdmin() ? ApiToken::whereNull('revoked_at')->count() : null,
        ]);
    }

    /**
     * Platzhalter, der zeigt, wie eine rollengeschuetzte Seite aussieht:
     * Die Route steht in der Gruppe `role:moderator` — mehr ist nicht noetig.
     */
    public function moderation(): View
    {
        return view('pages.moderation');
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
