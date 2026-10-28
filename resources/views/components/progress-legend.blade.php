{{--
  What the colours of <x-progress-split> mean, once above a list.

  @param string|null $who  "kann Mia" instead of "kann ich"
--}}
@props(['who' => null])
<p {{ $attributes->merge(['class' => 'flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted']) }} aria-hidden="true">
  <span><span class="legend-swatch progress-split-mastered"></span> {{ $who === null ? 'kann ich' : 'kann '.$who }}</span>
  <span><span class="legend-swatch progress-split-almost"></span> fast geschafft</span>
  <span><span class="legend-swatch progress-split-learning"></span> im Karteikasten</span>
  <span><span class="legend-swatch progress-split-new"></span> noch nicht gelernt</span>
</p>
