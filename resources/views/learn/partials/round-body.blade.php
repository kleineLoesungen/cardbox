{{--
  One screen of a learning round: the current card, or the end.
  Swapped as a whole by htmx after each answer (target #round).

  @param array      $round     state from StudyRoundService
  @param array|null $current   ['card' => Card, 'direction' => Direction, 'repeat' => bool]
  @param int        $position  number of the card that is up
  @param int        $streak    days in a row (end screen only)
--}}
@php($practice = $round['mode'] === 'practice')
<div id="round" class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-4 pt-3"
     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px))">

@if ($current)
  @php($card = $current['card'])
  @php($deck = $card->deck)
  @php($direction = $current['direction'])
  @php($question = $direction === \App\Enums\Direction::Forward ? $card->front : $card->back)
  @php($answer = $direction === \App\Enums\Direction::Forward ? $card->back : $card->front)
  @php($qLang = \App\Enums\Language::tryFrom($direction->questionLanguage($deck)))
  @php($aLang = \App\Enums\Language::tryFrom($direction->answerLanguage($deck)))
  @php($size = mb_strlen($question[0] ?? '') <= 14 ? 'text-4xl' : (mb_strlen($question[0] ?? '') <= 40 ? 'text-2xl' : 'text-lg'))

  {{--
    The flipped state sits on this element, not on #round. Its id changes
    with every step, so htmx does not carry the old "is-flipped" class over
    to the next card while settling — otherwise the new card would start
    flipped and turn back, showing its answer for a moment.
  --}}
  <div class="contents" id="round-step-{{ $round['step'] }}" data-card-screen>

  {{-- Top: leave, progress --}}
  <div class="flex items-center gap-3">
    <a href="/learn" class="btn btn-ghost btn-icon shrink-0" aria-label="Runde beenden">✕</a>
    <div class="meter flex-1" role="progressbar" aria-valuemin="1" aria-valuemax="{{ $round['total'] }}" aria-valuenow="{{ $position }}">
      <span style="width: {{ $round['total'] > 0 ? round(100 * ($position - 1) / $round['total']) : 0 }}%"></span>
    </div>
    <span class="shrink-0 text-sm tabular-nums text-muted">{{ $position }} / {{ $round['total'] }}</span>
  </div>

  <div class="mt-3 flex min-h-6 flex-wrap justify-center gap-2">
    @if ($practice)
      <span class="badge badge-warning">Freies Üben – zählt nicht</span>
    @endif
    @if ($current['repeat'])
      <span class="badge badge-neutral">Wiederholung</span>
    @endif
  </div>

  {{-- The card: tap to flip --}}
  <div class="flashcard my-4 flex flex-1 flex-col justify-center">
    <div class="flashcard-inner cursor-pointer" data-flip role="button" tabindex="0"
         aria-label="Karte umdrehen">
      <div class="flashcard-face">
        <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $qLang?->label() }}</p>
        <p class="flashcard-text {{ $size }}" lang="{{ $qLang?->tag() }}">{{ $question[0] ?? '' }}</p>
      </div>
      {{-- Hidden from screen readers until flipped (flip() removes aria-hidden). --}}
      <div class="flashcard-face flashcard-back" aria-hidden="true" data-back>
        <p class="text-sm text-muted" lang="{{ $qLang?->tag() }}">{{ $question[0] ?? '' }}</p>
        <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $aLang?->label() }}</p>
        @foreach ($answer as $i => $meaning)
          <p class="flashcard-text {{ $i === 0 ? $size : 'text-xl' }}" lang="{{ $aLang?->tag() }}">{{ $meaning }}</p>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Under the thumbs: flip first, then rate --}}
  <div data-before-flip>
    <button type="button" class="btn btn-primary btn-lg btn-block" data-flip>Umdrehen</button>
  </div>

  <form data-after-flip hidden class="grid grid-cols-2 gap-3"
        hx-post="/learn/round/answer" hx-target="#round" hx-swap="outerHTML" hx-disabled-elt="find button">
    <input type="hidden" name="card" value="{{ $card->id }}">
    <input type="hidden" name="step" value="{{ $round['step'] }}">
    <button type="submit" name="known" value="0" class="btn btn-secondary btn-lg">Nochmal</button>
    <button type="submit" name="known" value="1" class="btn btn-primary btn-lg">Gewusst</button>
  </form>
  </div>

@else
  {{-- End of the round --}}
  <div class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
    @if ($round['answered'] === 0)
      <h1 class="text-2xl font-semibold">Gerade ist nichts zu lernen</h1>
      <p class="text-muted">Alle Karten sind erledigt oder noch nicht dran.</p>
    @else
      <h1 class="text-3xl font-semibold">Geschafft!</h1>
      <p class="text-lg">
        <strong>{{ $round['known'] }} von {{ $round['answered'] }}</strong> gewusst
      </p>
      @if (! $practice && $round['promoted'] > 0)
        <p class="text-muted">
          {{ $round['promoted'] === 1 ? '1 Karte ist' : $round['promoted'].' Karten sind' }} ein Fach weiter.
        </p>
      @endif
      @if ($practice)
        <p class="text-sm text-muted">Freies Üben zählt nicht für den Karteikasten.</p>
      @elseif ($streak > 0)
        <p class="badge badge-brand text-sm">{{ $streak === 1 ? '1 Tag' : $streak.' Tage' }} in Folge gelernt</p>
      @endif
    @endif
  </div>

  <div class="stack">
    <a href="/learn" class="btn btn-primary btn-lg btn-block">Fertig</a>
    @if ($round['deck'])
      <a href="/decks/{{ $round['deck'] }}" class="btn btn-ghost btn-block">Zum Stapel</a>
    @endif
  </div>
@endif
</div>
