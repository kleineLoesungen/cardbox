{{-- Answer to POST /decks/{deck}/cards: fresh form, new row on top, new count. --}}
@include('partials.card-add-form', ['deck' => $deck, 'saved' => $card])

<div hx-swap-oob="afterbegin:#card-list">
  <x-card-row :card="$card" :deck="$deck" :editable="true" />
</div>
@include('partials.card-count', ['count' => $count])
@if ($count === 1)
  <div id="cards-empty" hx-swap-oob="delete"></div>
@endif
