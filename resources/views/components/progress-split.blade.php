{{--
  A deck's cards as one bar: "Kann ich" · almost (the box right before,
  issue #26) · in the box · not learned yet (the track). With `legend`
  the line under it says the numbers. LearningOverviewService::deckProgress.

    <x-progress-split :progress="$progress" />
    <x-progress-split :progress="$progress" :who="$child->name" legend />

  @param array $progress  deckProgress() of one deck
  @param string|null $who  "kann Mia" instead of "kann ich"
--}}
@props(['progress', 'who' => null, 'legend' => false])
@php
    $known = $who === null ? 'kann ich' : 'kann '.$who;
    $text = "{$progress['mastered']} {$known} · {$progress['almost']} fast geschafft · {$progress['learning']} im Karteikasten · {$progress['new']} noch nicht gelernt";
@endphp
<span {{ $attributes->merge(['class' => 'progress-split']) }} role="img" aria-label="{{ $text }}">
  <span class="progress-split-mastered" style="width: {{ $progress['mastered_percent'] }}%"></span>
  <span class="progress-split-almost" style="width: {{ $progress['almost_percent'] }}%"></span>
  <span class="progress-split-learning" style="width: {{ $progress['learning_percent'] }}%"></span>
</span>
@if ($legend)
  <span class="mt-1 block text-xs text-muted" aria-hidden="true">{{ $text }}</span>
@endif
