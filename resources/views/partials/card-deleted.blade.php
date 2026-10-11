{{-- Answer to a card deletion: the row is replaced by nothing; new count (and empty state) out of band. --}}
@include('partials.card-count', ['count' => $count])
@if ($count === 0)
  <div hx-swap-oob="afterend:#card-list">
    @include('decks.partials.cards-empty', ['isOwner' => $isOwner])
  </div>
@endif
@include('partials.toast-oob', ['toast' => ['kind' => 'success', 'message' => 'Karte gelöscht.']])
