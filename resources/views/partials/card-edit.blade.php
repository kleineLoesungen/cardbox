{{-- Edit form inside the #card-sheet; loaded via htmx when a card row is tapped. --}}
@php($frontTag = \App\Enums\Language::tryFrom($deck->front_language)?->tag())
@php($backTag = \App\Enums\Language::tryFrom($deck->back_language)?->tag())
<div class="stack">
  <form class="stack" hx-post="/decks/{{ $deck->id }}/cards/{{ $card->id }}"
        hx-target="#card-{{ $card->id }}" hx-swap="outerHTML">
    <div class="field">
      <span class="label">{{ $deck->frontLabel() }}</span>
      <div class="stack gap-2" id="edit-front-group">
        @foreach ($card->front as $i => $meaning)
          <input class="input input-lg" type="text" name="front[]" value="{{ $meaning }}" lang="{{ $frontTag }}"
                 aria-label="{{ $deck->frontLabel() }} {{ $i + 1 }}" autocomplete="off">
        @endforeach
      </div>
      <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="edit-front-group">+ weitere Bedeutung</button>
    </div>

    <div class="field">
      <span class="label">{{ $deck->backLabel() }}</span>
      <div class="stack gap-2" id="edit-back-group">
        @foreach ($card->back as $i => $meaning)
          <input class="input input-lg" type="text" name="back[]" value="{{ $meaning }}" lang="{{ $backTag }}"
                 aria-label="{{ $deck->backLabel() }} {{ $i + 1 }}" autocomplete="off">
        @endforeach
      </div>
      <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="edit-back-group">+ weitere Bedeutung</button>
      <p class="hint">Ein Feld leeren, um eine Bedeutung zu entfernen.</p>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">Speichern</button>
  </form>

  <form hx-post="/decks/{{ $deck->id }}/cards/{{ $card->id }}/delete"
        hx-target="#card-{{ $card->id }}" hx-swap="outerHTML"
        hx-confirm="{{ $deck->is_shared && $followerCount > 0 ? 'Karte löschen? Auch alle, die diesem Stapel folgen, verlieren sie samt Lernstand.' : 'Karte löschen?' }}">
    <button type="submit" class="btn btn-ghost btn-block text-danger">Karte löschen</button>
  </form>
</div>
