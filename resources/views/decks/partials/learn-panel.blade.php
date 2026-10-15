{{--
  Learning on the deck page (docs/specs/einfacher-lernen.md, C): the box
  tiles, "Nur diesen Stapel lernen" and "Für den Test üben". The main way to
  learn is "Heute"; there is no plan choice and no direction switch here.
  Only shown when the deck has cards.

  @param \App\Models\Deck $deck
  @param \App\Enums\Direction $direction  direction of the box tiles
  @param bool $withReverse  the viewer learns back → front too
  @param bool $learns  own or followed deck
  @param int $learnable  due and new cards for "Nur diesen Stapel lernen"
  @param \Illuminate\Support\Collection<int, \App\Models\DeckRun> $runs  the viewer's runs of this deck
  @param \App\Models\DeckRun|null $run  the selected run; null = not started yet
  @param list<string>|null $activeIds  active cards when only part is learned (issue #10)
  @param \Illuminate\Support\Collection $cards  all cards of the deck
  @param array $boxes  from LearningOverviewService::boxes
--}}
@php($n = $boxes['box_count'])
@php($runParam = $run ? '&run='.$run->id : '')
<section class="mb-4 stack" aria-label="Lernen">
  @if ($runs->count() > 1)
    {{-- Runs started before plans moved to the account: switch between them; everything below belongs to the chosen one. --}}
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
  @endif
  @if ($learns)
    <p class="text-sm text-muted">Kommt jeden Tag unter <a href="/learn" class="font-medium text-brand hover:underline">Heute</a> dran.</p>
  @endif

  @if ($activeIds !== null)
    {{-- Learning only part of the deck (issue #10). --}}
    <p class="text-sm text-muted">
      Du lernst <span class="font-medium text-content">{{ count($activeIds) }} von {{ $cards->count() }}</span> Karten ·
      <a href="/decks/{{ $deck->id }}/selection" class="text-brand hover:underline">Auswahl ändern</a>
    </p>
  @endif

  @if ($withReverse)
    {{-- Only switches the tiles; learning takes both directions. --}}
    <nav class="segmented" aria-label="Richtung der Fächer">
      @foreach (\App\Enums\Direction::cases() as $d)
        <a href="/decks/{{ $deck->id }}?direction={{ $d->value }}{{ $runParam }}"
           class="{{ $d === $direction ? 'is-active' : '' }}" @if ($d === $direction) aria-current="true" @endif>
          {{ $d->label($deck) }}
        </a>
      @endforeach
    </nav>
  @endif

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

  @if ($learns)
    <form method="post" action="/learn/round">
      @csrf
      <input type="hidden" name="deck" value="{{ $deck->id }}">
      @if ($run)
        <input type="hidden" name="run" value="{{ $run->id }}">
      @endif
      <button type="submit" class="btn btn-secondary btn-lg btn-block" @disabled($learnable === 0)>
        Nur diesen Stapel lernen
        @if ($learnable > 0)
          <span class="font-normal opacity-80">({{ $learnable }})</span>
        @endif
      </button>
      @if ($learnable === 0)
        <p class="hint mt-1 text-center">Gerade ist hier nichts dran. Üben kannst du trotzdem.</p>
      @endif
    </form>
  @endif
  <form method="post" action="/learn/round">
    @csrf
    <input type="hidden" name="mode" value="test">
    <input type="hidden" name="deck" value="{{ $deck->id }}">
    <button type="submit" class="btn btn-ghost btn-block">Für den Test üben</button>
    <p class="hint text-center">Alle Karten, bis du jede einmal gewusst hast. Zählt nicht für den Karteikasten.</p>
  </form>
</section>

<x-sheet id="box-sheet" title="Karten im Fach"></x-sheet>
