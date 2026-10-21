{{--
  Learning on the deck page (docs/specs/einfacher-lernen.md, C): the box
  tiles, "Nur diesen Stapel lernen" and "Für den Test üben". The main way to
  learn is "Heute"; there is no plan choice and no direction switch here.
  Only shown when the deck has cards.

  @param \App\Models\Deck $deck
  @param \App\Enums\Direction $direction  direction of the box tiles
  @param bool $withReverse  the viewer learns back → front too
  @param string|null $learningState  'active' ("Lerne ich"), 'paused', 'idle'; null = somebody else's deck not learned
  @param int $learnable  due and new cards for "Nur diesen Stapel lernen"
  @param \Illuminate\Support\Collection<int, \App\Models\DeckRun> $runs  the viewer's runs of this deck
  @param \App\Models\DeckRun|null $run  the selected run; null = not started yet
  @param list<string>|null $activeIds  active cards when only part is learned (issue #10)
  @param \Illuminate\Support\Collection $cards  all cards of the deck
  @param array $boxes  from LearningOverviewService::boxes
  @param list<string> $hardIds  cards often answered wrong ("Knifflige Wörter üben")
--}}
@php($n = $boxes['box_count'])
@php($canDuel = $learningState !== null && ($activeIds === null ? $cards->count() : count($activeIds)) >= \App\Services\DuelService::MIN_CARDS)
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

  @if ($activeIds !== null)
    {{-- Learning only part of the deck (issue #10). --}}
    <p class="text-sm text-muted">
      Du lernst <span class="font-medium text-content">{{ count($activeIds) }} von {{ $cards->count() }}</span> Karten ·
      <a href="/decks/{{ $deck->id }}/selection" class="link-action">Auswahl ändern</a>
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

  @if ($learningState === 'active')
    {{-- Learning with the boxes is for "Lerne ich" decks; practising below is for any. --}}
    <form method="post" action="/learn/round">
      @csrf
      <input type="hidden" name="deck" value="{{ $deck->id }}">
      @if ($run)
        <input type="hidden" name="run" value="{{ $run->id }}">
      @endif
      {{-- The main action of the deck page (issue #20): bigger and in the brand colour. --}}
      <button type="submit" class="btn btn-primary btn-lg btn-block" @disabled($learnable === 0)>
        <x-icon name="learn" />
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
    <button type="submit" class="btn btn-secondary btn-block"><x-icon name="test" />Für den Test üben</button>
    <p class="hint text-center">Alle Karten, bis du jede einmal gewusst hast. Zählt nicht für den Karteikasten.</p>
  </form>
  @if ($hardIds !== [])
    {{-- Cards often answered wrong (runden-und-knifflige-woerter.md, B). --}}
    <form method="post" action="/learn/round">
      @csrf
      <input type="hidden" name="mode" value="test">
      <input type="hidden" name="only" value="hard">
      <input type="hidden" name="deck" value="{{ $deck->id }}">
      <button type="submit" class="btn btn-secondary btn-block"><x-icon name="hard" />Knifflige Wörter üben ({{ count($hardIds) }})</button>
    </form>
  @endif
  @if ($canDuel)
    {{-- Duels (docs/specs/duell.md): the same words for everybody with the code. --}}
    <button type="button" class="btn btn-secondary btn-block" data-open-sheet="duel-sheet"><x-icon name="duel" />Duell starten</button>
  @endif
</section>

<x-sheet id="box-sheet" title="Karten im Fach"></x-sheet>

@if ($canDuel)
  <x-sheet id="duel-sheet" title="Duell starten" :data-open-on-load="$errors->hasAny(['players', 'rounds', 'mode', 'deck']) ? 'true' : null">
    <form method="post" action="/decks/{{ $deck->id }}/duels" class="stack">
      @csrf
      @if ($errors->hasAny(['players', 'rounds', 'mode', 'deck']))
        <div class="alert alert-danger" role="alert">{{ $errors->first('players') ?: $errors->first('rounds') ?: $errors->first('mode') ?: $errors->first('deck') }}</div>
      @endif
      <p class="text-sm text-muted">
        Alle bekommen je Runde dieselben {{ \App\Services\DuelService::CARDS }} Wörter
        {{-- Issue #21: only the creator's selection (issue #10) comes up. --}}
        @if ($activeIds !== null)
          aus deiner Auswahl ({{ count($activeIds) }} von {{ $cards->count() }} Karten).
        @else
          aus diesem Stapel.
        @endif
        Wer mehr weiß, gewinnt – bei Gleichstand die schnellere Zeit.
      </p>
      <div class="field">
        <label class="label" for="duel-players">Wie viele spielen mit? (du eingerechnet)</label>
        <select class="select" id="duel-players" name="players">
          @for ($players = \App\Services\DuelService::MIN_PLAYERS; $players <= \App\Services\DuelService::MAX_PLAYERS; $players++)
            <option value="{{ $players }}" @selected((int) old('players', 2) === $players)>{{ $players }} Spieler</option>
          @endfor
        </select>
      </div>
      <div class="field">
        <label class="label" for="duel-rounds">Wie viele Runden? (je {{ \App\Services\DuelService::CARDS }} Wörter)</label>
        <select class="select" id="duel-rounds" name="rounds">
          @for ($rounds = 1; $rounds <= \App\Services\DuelService::MAX_ROUNDS; $rounds++)
            <option value="{{ $rounds }}" @selected((int) old('rounds', 1) === $rounds)>{{ $rounds === 1 ? '1 Runde' : $rounds.' Runden' }}</option>
          @endfor
        </select>
        <p class="hint">Nach jeder Runde gibt es die Rangliste, dann startest du die nächste. Wörter wiederholen sich erst, wenn der Stapel durch ist.</p>
      </div>
      <fieldset class="field">
        <legend class="label">Wie wird gefragt?</legend>
        <div class="grid gap-2">
          <label class="choice-card">
            <input type="radio" name="mode" value="choice" @checked(old('mode', 'choice') === 'choice')>
            <span><span class="font-semibold">Auswahl aus 4</span><span class="block text-sm text-muted">Schnell, ohne Tippfehler</span></span>
          </label>
          <label class="choice-card">
            <input type="radio" name="mode" value="typed" @checked(old('mode') === 'typed')>
            <span><span class="font-semibold">Eintippen</span><span class="block text-sm text-muted">Schwerer – kleine Tippfehler zählen trotzdem</span></span>
          </label>
        </div>
      </fieldset>
      <button type="submit" class="btn btn-primary btn-lg btn-block">Code erzeugen</button>
    </form>
  </x-sheet>
@endif
