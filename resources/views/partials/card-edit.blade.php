{{--
  Edit form inside the #card-sheet; loaded via htmx when a card row is
  tapped. Fields in partials/card-fields.

  @param bool $moderation  correcting somebody else's shared card
                           (Moderation\CardController): other form target,
                           a note whose card it is, no delete
--}}
@php($moderation ??= false)
<div class="stack">
  @if ($moderation)
    <p class="alert alert-warning">Moderation: Du änderst eine Karte von {{ $deck->user->name }}. Alle, die mit dem Stapel lernen, sehen die Änderung.</p>
  @endif
  <form class="stack" hx-post="{{ $moderation ? '/moderation' : '' }}/decks/{{ $deck->id }}/cards/{{ $card->id }}"
        hx-target="#card-{{ $card->id }}" hx-swap="outerHTML">
    @include('partials.card-fields', ['idPrefix' => 'edit'])

    <button type="submit" class="btn btn-primary btn-lg btn-block">Speichern</button>
  </form>

  {{-- Moderation deletes single cards only from the dictionary (issue #12), not here. --}}
  @unless ($moderation)
  <form hx-post="/decks/{{ $deck->id }}/cards/{{ $card->id }}/delete"
        hx-target="#card-{{ $card->id }}" hx-swap="outerHTML"
        data-confirm-action="Karte löschen"
        hx-confirm="{{ $deck->is_shared && $followerCount > 0 ? 'Karte löschen? Auch alle, die mit diesem Stapel lernen, verlieren sie samt Lernstand.' : 'Karte löschen?' }}">
    <button type="submit" class="btn btn-ghost btn-block text-danger">Karte löschen</button>
  </form>
  @endunless
</div>
