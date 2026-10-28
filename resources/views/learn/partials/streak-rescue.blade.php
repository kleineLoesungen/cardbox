{{--
  Yesterday was missed: three times the daily goal today keeps the streak
  (issue #27, LeitnerService::streakStatus). Before the goal: what it
  takes; once the goal is reached: how many cards are still missing.

  @param array{days: int, target: int, answers: int} $rescue
  @param int $goal  today's daily goal
--}}
@php
    $left = max(0, $rescue['target'] - $rescue['answers']);
    $streakText = $rescue['days'] === 1 ? '1 Tag' : $rescue['days'].' Tage';
@endphp
@if ($rescue['answers'] >= $goal)
  Tagesziel geschafft! Noch {{ $left === 1 ? '1 Karte' : $left.' Karten' }} heute, dann geht deine Lernfolge weiter: {{ $streakText }}.
@else
  Gestern hast du ausgesetzt. Schaffst du heute dein Tagesziel dreimal ({{ $rescue['target'] }} Karten), geht deine Lernfolge weiter: {{ $streakText }}.
@endif
