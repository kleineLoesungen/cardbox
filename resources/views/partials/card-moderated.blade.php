{{-- Answer to a correction by the moderation: the row replaces itself. The sheet closes via HX-Trigger. --}}
<x-card-row :card="$card" :deck="$deck" :can-suggest="true" :can-moderate="true" :suggestion="$suggestion" />
@include('partials.toast-oob', ['toast' => ['kind' => 'success', 'message' => 'Karte korrigiert. Alle, die mit dem Stapel lernen, sehen die Änderung.']])
