{{-- Answer to a card update: the row replaces itself. The sheet closes via HX-Trigger. --}}
<x-card-row :card="$card" :deck="$deck" :editable="true" />
@include('partials.toast-oob', ['toast' => ['kind' => 'success', 'message' => 'Karte gespeichert.']])
