<?php

namespace App\Services;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Fachlogik der Beispielressource „Notiz".
 *
 * Kennt WEDER Request NOCH Response NOCH View — nur einfache Werte rein,
 * Modelle raus. Web- und API-Controller rufen dieselben Funktionen auf; die
 * Validierung existiert dadurch genau einmal.
 *
 * Die Funktionen kommen in zwei Ausfuehrungen, und der Unterschied ist
 * sicherheitsrelevant:
 *
 * * `…ForUser` / `…Owned` — auf einen Benutzer eingeschraenkt.
 *   Fuer die HTML-Oberflaeche, wo ein Mensch nur seine eigenen Daten sieht.
 * * `listAll` / `get` / `update` / `delete` — systemweit, ohne Eigentumsfilter.
 *   Fuer die Maschinen-Schnittstelle, deren Zugriff ueber Scopes geregelt ist.
 *
 * Wer in einem Web-Controller versehentlich die systemweite Fassung benutzt,
 * oeffnet fremde Daten. Im Zweifel die `Owned`-Fassung nehmen.
 */
class NoteService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{title: string, body: string}
     *
     * @throws ValidationException
     */
    private function validate(array $input): array
    {
        /** @var array{title: string, body?: string|null} $data */
        $data = Validator::make($input, [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:20000'],
        ])->validate();

        return ['title' => $data['title'], 'body' => $data['body'] ?? ''];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(string $userId, array $input): Note
    {
        $data = $this->validate($input);

        if (! Str::isUuid($userId)) {
            throw ValidationException::withMessages(['user_id' => 'Diesen Benutzer gibt es nicht.']);
        }

        try {
            return Note::create([...$data, 'user_id' => $userId]);
        } catch (QueryException $e) {
            // 23503 = Fremdschluessel verletzt. Kommt vor, wenn die Schnittstelle
            // eine unbekannte user_id schickt — ein Eingabefehler, kein Serverfehler.
            if ($e->getCode() === '23503') {
                throw ValidationException::withMessages(['user_id' => 'Diesen Benutzer gibt es nicht.']);
            }
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Auf einen Besitzer eingeschraenkt — fuer die Weboberflaeche.
    // -------------------------------------------------------------------------

    /** @return Collection<int, Note> */
    public function listForUser(User $user): Collection
    {
        return $user->notes()->latest()->get();
    }

    /**
     * Laedt eine Notiz und stellt dabei sicher, dass sie dem Benutzer gehoert.
     * Fremde Notizen ergeben 404 statt 403 — so verraet die Antwort nicht,
     * welche IDs existieren.
     *
     * @throws ModelNotFoundException<Note>
     */
    public function getOwned(User $user, string $id): Note
    {
        $this->ensureUuid($id);

        return $user->notes()->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Note>
     */
    public function updateOwned(User $user, string $id, array $input): Note
    {
        $note = $this->getOwned($user, $id);
        $note->update($this->validate($input));

        return $note;
    }

    /** @throws ModelNotFoundException<Note> */
    public function deleteOwned(User $user, string $id): void
    {
        $this->getOwned($user, $id)->delete();
    }

    // -------------------------------------------------------------------------
    // Systemweit — fuer die Maschinen-Schnittstelle.
    // Kein Eigentumsfilter. Der Zugriff wird ueber Scopes geregelt, nicht hier.
    // -------------------------------------------------------------------------

    /** @return Collection<int, Note> */
    public function listAll(?string $userId = null): Collection
    {
        if ($userId !== null && ! Str::isUuid($userId)) {
            return new Collection;
        }

        return Note::query()
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->latest()
            ->get();
    }

    /** @throws ModelNotFoundException<Note> */
    public function get(string $id): Note
    {
        $this->ensureUuid($id);

        return Note::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     * @throws ModelNotFoundException<Note>
     */
    public function update(string $id, array $input): Note
    {
        $note = $this->get($id);
        $note->update($this->validate($input));

        return $note;
    }

    /** @throws ModelNotFoundException<Note> */
    public function delete(string $id): void
    {
        $this->get($id)->delete();
    }

    /**
     * Postgres lehnt eine ungueltige UUID mit einem Fehler ab — das waere ein
     * 500er fuer eine schlicht falsche Adresse. Deshalb vorher 404.
     */
    private function ensureUuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw (new ModelNotFoundException)->setModel(Note::class, [$id]);
        }
    }
}
