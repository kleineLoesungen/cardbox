<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Services\NoteService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Notizen ueber die JSON-Schnittstelle.
 *
 * Ein Maschinen-Client arbeitet systemweit, nicht aus der Sicht einer Person —
 * deshalb die systemweiten Fassungen des Service (listAll, get, update, delete).
 * Wo ein Besitzer gebraucht wird, kommt er ausdruecklich aus der Anfrage.
 */
class NoteController extends Controller
{
    public function __construct(private NoteService $notes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->query('user_id');

        return NoteResource::collection($this->notes->listAll(is_string($userId) ? $userId : null));
    }

    public function show(string $id): NoteResource
    {
        return new NoteResource($this->notes->get($id));
    }

    public function store(Request $request): NoteResource
    {
        $note = $this->notes->create(
            (string) $request->input('user_id'),
            $request->only('title', 'body'),
        );

        return new NoteResource($note); // 201, weil das Modell gerade angelegt wurde
    }

    public function update(Request $request, string $id): NoteResource
    {
        return new NoteResource($this->notes->update($id, $request->only('title', 'body')));
    }

    public function destroy(string $id): Response
    {
        $this->notes->delete($id);

        return response()->noContent();
    }
}
