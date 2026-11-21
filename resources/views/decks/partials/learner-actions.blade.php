{{--
  In the deck menu for somebody else's deck the viewer may learn: remove a
  deck shared by name, and "Lernen" / "Nicht mehr lernen".
--}}
@if ($sharedWithMe && ! $deck->is_shared)
  {{-- Shared by name: out of the own decks; progress stays should it come again. --}}
  <form method="post" action="/decks/{{ $deck->id }}/decline"
        data-confirm-action="Stapel entfernen"
        data-confirm="„{{ $deck->name }}“ aus deinen Stapeln entfernen? {{ $asGuardian ? 'Teilst du' : 'Teilt '.$deck->user->name }} ihn wieder mit dir, ist dein Lernstand noch da.">
    @csrf
    <button type="submit" class="sheet-action">Stapel entfernen</button>
  </form>
@endif
@if ($isFollowing)
  <form method="post" action="/decks/{{ $deck->id }}/unfollow"
        data-confirm-action="Nicht mehr lernen"
        data-confirm="Nicht mehr lernen? Dein Lernstand bleibt, falls du ihn wieder lernst.">
    @csrf
    <button type="submit" class="sheet-action">Nicht mehr lernen</button>
  </form>
@else
  <form method="post" action="/decks/{{ $deck->id }}/follow">
    @csrf
    <button type="submit" class="sheet-action">Lernen</button>
  </form>
@endif
