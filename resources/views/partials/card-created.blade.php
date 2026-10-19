{{--
  Answer to POST /decks/{deck}/cards: fresh form, new row on top, new count.
  With $close the sheet closes, so the confirmation comes as a toast instead.
--}}
@include('partials.card-add-form', ['deck' => $deck, 'saved' => $close ? null : $card])
@if ($close)
  @include('partials.toast-oob', ['toast' => ['kind' => 'success', 'message' => 'Gespeichert: '.$card->frontText().' – '.$card->backText()]])
@endif

<div hx-swap-oob="afterbegin:#card-list">
  <x-card-row :card="$card" :deck="$deck" :editable="true" />
</div>
@include('partials.card-count', ['count' => $count])
@if ($count === 1)
  <div id="cards-empty" hx-swap-oob="delete"></div>
@endif
