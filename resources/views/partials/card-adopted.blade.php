{{-- Answer to adopting a card: the result line confirms, the copy appears in the list. --}}
<li class="list-row">
  <span class="card-row-sides">
    <span>{{ $card->frontText() }}</span>
    <span class="text-muted">{{ $card->backText() }}</span>
  </span>
  <span class="badge badge-success shrink-0">übernommen</span>
</li>

<div hx-swap-oob="afterbegin:#card-list">
  <x-card-row :card="$card" :deck="$deck" :editable="true" />
</div>
@include('partials.card-count', ['count' => $count])
@if ($count === 1)
  <div id="cards-empty" hx-swap-oob="delete"></div>
@endif
