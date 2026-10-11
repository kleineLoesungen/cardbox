{{--
  Learning on the deck page: direction switch, box tiles, "Lernen" and
  "Freies Üben". Only shown when the deck has cards.

  @param \App\Models\Deck $deck
  @param \App\Enums\Direction $direction
  @param array $boxes  from LearningOverviewService::boxes
--}}
@php($other = $direction === \App\Enums\Direction::Forward ? \App\Enums\Direction::Backward : \App\Enums\Direction::Forward)
@php($n = $boxes['box_count'])
<section class="mb-4 stack" aria-label="Lernen">
  <nav class="segmented" aria-label="Richtung">
    @foreach (\App\Enums\Direction::cases() as $d)
      <a href="/decks/{{ $deck->id }}?direction={{ $d->value }}"
         class="{{ $d === $direction ? 'is-active' : '' }}" @if ($d === $direction) aria-current="true" @endif>
        {{ $d->label($deck) }}
      </a>
    @endforeach
  </nav>

  @if ($boxes['started'])
    {{-- Box tiles: tap one to see its cards. --}}
    <div class="box-tiles" style="--boxes: {{ $n + 1 }}">
      @for ($b = 0; $b <= $n; $b++)
        <button type="button" class="box-tile {{ $b === $n ? 'box-tile-mastered' : '' }}"
                hx-get="/decks/{{ $deck->id }}/box/{{ $b }}?direction={{ $direction->value }}"
                hx-target="#box-sheet-body"
                aria-label="{{ $b === 0 ? 'Neu' : ($b === $n ? 'Kann ich' : 'Fach '.$b) }}: {{ $boxes['boxes'][$b] === 1 ? '1 Karte' : $boxes['boxes'][$b].' Karten' }}">
          <span class="box-tile-count">{{ $boxes['boxes'][$b] }}</span>
          <span class="box-tile-label">{{ $b === 0 ? 'Neu' : ($b === $n ? 'Kann ich' : 'Fach '.$b) }}</span>
        </button>
      @endfor
    </div>
  @endif

  <form method="post" action="/learn/round">
    @csrf
    <input type="hidden" name="deck" value="{{ $deck->id }}">
    <input type="hidden" name="direction" value="{{ $direction->value }}">
    @if ($boxes['due'] + $boxes['new'] > 0)
      <button type="submit" class="btn btn-primary btn-lg btn-block">
        Lernen
        <span class="font-normal opacity-80">
          ({{ collect([$boxes['due'] > 0 ? $boxes['due'].' fällig' : null, $boxes['new'] > 0 ? $boxes['new'].' neu' : null])->filter()->implode(' · ') }})
        </span>
      </button>
    @else
      <button type="button" class="btn btn-secondary btn-lg btn-block" disabled>Heute nichts fällig</button>
      <p class="hint mt-1 text-center">Mit „Freies Üben“ kannst du trotzdem üben.</p>
    @endif
  </form>

  <button type="button" class="btn btn-ghost btn-block" data-open-sheet="practice-sheet">Freies Üben</button>
</section>

<x-sheet id="practice-sheet" title="Freies Üben">
  <p class="mb-4 text-sm text-muted">Zufällige Karten aus diesem Stapel. Es wird nichts gezählt.</p>
  @if ($boxes['started'])
    <p class="mb-4 text-sm text-muted">Nur ein Fach üben: oben auf das Fach tippen.</p>
  @endif
  <div class="stack gap-2">
    @foreach ([['forward', \App\Enums\Direction::Forward->label($deck)], ['backward', \App\Enums\Direction::Backward->label($deck)], ['mixed', 'Gemischt']] as [$mode, $label])
      <form method="post" action="/learn/round">
        @csrf
        <input type="hidden" name="mode" value="practice">
        <input type="hidden" name="deck" value="{{ $deck->id }}">
        <input type="hidden" name="direction" value="{{ $mode }}">
        <button type="submit" class="btn btn-secondary btn-lg btn-block">{{ $label }}</button>
      </form>
    @endforeach
  </div>
</x-sheet>

<x-sheet id="box-sheet" title="Karten im Fach"></x-sheet>
