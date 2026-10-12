{{--
  Learning on the deck page: plan (run) switch, direction switch, box
  tiles, "Lernen" and "Freies Üben". Only shown when the deck has cards.

  @param \App\Models\Deck $deck
  @param \App\Enums\Direction $direction
  @param \Illuminate\Support\Collection<int, \App\Models\DeckRun> $runs  the viewer's runs of this deck
  @param \App\Models\DeckRun|null $run  the selected run; null = not started yet
  @param string $standardKey  key of the viewer's standard plan
  @param list<\App\Enums\StudyPlan> $presets
  @param array $boxes  from LearningOverviewService::boxes
--}}
@php($n = $boxes['box_count'])
@php($runParam = $run ? '&run='.$run->id : '')
@php($standardLabel = \App\Enums\StudyPlan::tryFrom($standardKey)?->label() ?? 'Mein Plan')
@php($running = $runs->pluck('plan')->all())
<section class="mb-4 stack" aria-label="Lernen">
  @if ($runs->count() > 1)
    {{-- Several runs: switch between them; everything below belongs to the chosen one. --}}
    <div class="stack gap-1">
    <p class="text-xs font-medium text-subtle" aria-hidden="true">Lernplan</p>
    <nav class="segmented" aria-label="Lernplan">
      @foreach ($runs as $r)
        <a href="/decks/{{ $deck->id }}?run={{ $r->id }}&direction={{ $direction->value }}"
           class="{{ $r->is($run) ? 'is-active' : '' }}" @if ($r->is($run)) aria-current="true" @endif>
          {{ $r->label() }}
        </a>
      @endforeach
    </nav>
    </div>
  @else
    <p class="text-sm text-muted">Lernplan: <span class="font-medium text-content">{{ $run?->label() ?? $standardLabel }}</span></p>
  @endif

  <nav class="segmented" aria-label="Richtung">
    @foreach (\App\Enums\Direction::cases() as $d)
      <a href="/decks/{{ $deck->id }}?direction={{ $d->value }}{{ $runParam }}"
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
                hx-get="/decks/{{ $deck->id }}/box/{{ $b }}?direction={{ $direction->value }}{{ $runParam }}"
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
    @if ($run)
      <input type="hidden" name="run" value="{{ $run->id }}">
    @endif
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
  @if (collect($presets)->contains(fn ($p) => ! in_array($p->value, $running, true)))
    <button type="button" class="btn btn-ghost btn-block" data-open-sheet="start-sheet">Mit anderem Lernplan starten</button>
  @endif
</section>

{{-- Start the deck with one of the presets; plans already running are greyed out. --}}
<x-sheet id="start-sheet" title="Mit anderem Lernplan starten" :data-open-on-load="$errors->has('plan') ? 'true' : null">
  <p class="mb-4 text-sm text-muted">
    @if ($runs->isEmpty())
      Statt mit deinem Standard lernst du diesen Stapel dann mit diesem Plan. Deine anderen Stapel bleiben, wie sie sind.
    @else
      Du lernst diesen Stapel dann zusätzlich mit diesem Plan. Jeder Plan hat seinen eigenen Lernstand.
    @endif
  </p>
  @php($preselect = collect($presets)->first(fn ($p) => $p->value === $standardKey && ! in_array($p->value, $running, true))
      ?? collect($presets)->first(fn ($p) => ! in_array($p->value, $running, true)))
  <form method="post" action="/decks/{{ $deck->id }}/runs" class="stack">
    @csrf
    <fieldset class="stack gap-2">
      <legend class="sr-only">Lernplan wählen</legend>
      @foreach ($presets as $plan)
        @php($isRunning = in_array($plan->value, $running, true))
        <label class="choice-card {{ $isRunning ? 'opacity-60' : '' }}">
          <input type="radio" name="plan" value="{{ $plan->value }}" @checked($plan === $preselect) @disabled($isRunning)>
          <span class="min-w-0">
            <span class="block font-semibold">
              {{ $plan->label() }}
              @if ($plan->value === $standardKey)
                <span class="badge badge-neutral ml-1">Standard</span>
              @endif
              @if ($isRunning)
                <span class="badge badge-success ml-1">läuft schon</span>
              @endif
            </span>
            <span class="block text-sm text-muted">{{ $plan->description() }}</span>
          </span>
        </label>
      @endforeach
    </fieldset>
    @error('plan')
      <p class="error">{{ $message }}</p>
    @enderror
    @if ($preselect)
      <button type="submit" class="btn btn-primary btn-lg btn-block">Starten</button>
    @else
      <p class="hint text-center">Dieser Stapel läuft schon mit allen drei Plänen.</p>
    @endif
  </form>
</x-sheet>

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
