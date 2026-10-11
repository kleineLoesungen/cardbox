<?php

namespace App\Http\Controllers;

use App\Services\NoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Notizen in der Weboberflaeche — das Muster zum Kopieren.
 *
 * Duenn: Eingabe an den Service, Ergebnis an das Template. Ausschliesslich
 * die `Owned`-Fassungen des Service — ein Mensch sieht nur seine Notizen.
 */
class NoteController extends Controller
{
    public function __construct(private NoteService $notes) {}

    public function index(Request $request): View
    {
        return view('pages.notes', ['notes' => $this->notes->listForUser($request->user())]);
    }

    /** Antwort auf htmx: genau EIN Eintrag, oben in die Liste geschoben, plus Meldung. */
    public function store(Request $request): View
    {
        $note = $this->notes->create($request->user()->id, $request->only('title', 'body'));

        return view('partials.note-created', [
            'note' => $note,
            'toast' => ['kind' => 'success', 'message' => 'Notiz gespeichert.'],
        ]);
    }

    /** Leere Antwort: htmx ersetzt den Eintrag durch nichts, er verschwindet. */
    public function destroy(Request $request, string $note): Response
    {
        $this->notes->deleteOwned($request->user(), $note);

        return response('');
    }
}
