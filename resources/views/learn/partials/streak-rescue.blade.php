{{--
  Yesterday was missed: twice the daily goal today keeps the streak
  (issue #27, LeitnerService::streakStatus). Before the goal: what it
  takes; after the first goal: "noch einmal".

  @param array{days: int, target: int, answers: int} $rescue
  @param int $goal  today's daily goal
--}}
@php
    $left = max(0, $rescue['target'] - $rescue['answers']);
    $streakText = $rescue['days'] === 1 ? '1 Tag' : $rescue['days'].' Tage';
@endphp
@if ($rescue['answers'] >= $goal)
  Schaffst du heute noch einmal dein Tagesziel – noch {{ $left === 1 ? '1 Karte' : $left.' Karten' }} –, geht deine Lernfolge weiter: {{ $streakText }}.
@else
  Gestern hast du ausgesetzt. Schaffst du heute dein Tagesziel zweimal ({{ $rescue['target'] }} Karten), geht deine Lernfolge weiter: {{ $streakText }}.
@endif
