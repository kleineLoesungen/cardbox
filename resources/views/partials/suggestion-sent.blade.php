{{-- Answer to "Fehler melden": the card row shows the new status. The sheet closes via HX-Trigger. --}}
<x-card-row :card="$card" :deck="$deck" :can-suggest="true" :suggestion="$suggestion" />
@include('partials.toast-oob', ['toast' => ['kind' => 'success', 'message' => 'Danke! Dein Vorschlag ist bei '.$deck->user->name.'.']])
