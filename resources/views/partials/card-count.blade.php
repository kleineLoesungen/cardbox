{{--
  Card count in the deck header. Sent out of band after changes; the deck
  page itself includes it with 'oob' => false.
--}}
@php($oob ??= true)
<span id="card-count" @if ($oob) hx-swap-oob="true" @endif>{{ $count === 1 ? '1 Karte' : $count.' Karten' }}</span>
