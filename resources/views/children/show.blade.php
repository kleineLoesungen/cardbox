{{--
  A child's progress for a parent (docs/specs/eltern-ansicht.md, B). Read-only:
  no links into the child's decks, no card lists, no single answers.
--}}
@extends('layouts.app')
@section('title', $child->name)

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">{{ $child->name }}</h1>

  @if ($overview['blocked'])
    <div class="alert alert-warning mt-6" role="status">Dieses Konto ist gerade gesperrt.</div>
  @else
    @php($day = $overview['day'])
    @if ($overview['streak'] > 0)
      <p class="mt-1 text-sm text-muted">
        <span aria-hidden="true" class="mr-1">🔥</span>
        {{ $overview['streak'] === 1 ? '1 Tag' : $overview['streak'].' Tage' }} in Folge
      </p>
    @endif
    <p class="mt-1">
      @if ($day['goal_met'] || $day['answers'] >= $day['goal'])
        <strong class="text-success">Heute: Tagesziel geschafft</strong>
      @else
        Heute: <strong>{{ $day['answers'] }} von {{ $day['goal'] }}</strong> gewusst
      @endif
    </p>

    @unless ($overview['learned_ever'])
      <p class="empty mt-6">{{ $child->name }} hat noch nicht gelernt.</p>
    @else
      {{-- --- Learning days: four weeks, Monday to Sunday ------------------- --}}
      <section class="card mt-6">
        <div class="card-header"><h2 class="card-title">Lerntage</h2></div>
        <div class="card-body stack">
          {{-- What the tiles are (issue #18). --}}
          <p class="text-sm text-muted">Jede Kachel ist ein Tag, jede Zeile eine Woche von Montag bis Sonntag. Unten steht die aktuelle Woche, heute ist umrandet.</p>
          <div class="day-strip" role="list" aria-label="Lerntage der letzten vier Wochen">
            @foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $weekday)
              <span class="day-strip-head" aria-hidden="true">{{ $weekday }}</span>
            @endforeach
            @foreach ($overview['weeks'] as $week)
              @foreach ($week as $cell)
                @php($label = $cell['date']->locale('de')->isoFormat('dd D.M.').': '.['goal' => 'Ziel geschafft', 'learned' => 'gelernt', 'none' => 'nicht gelernt', 'future' => 'noch nicht'][$cell['state']])
                <span role="listitem" @class(['day', 'day-'.$cell['state'], 'day-today' => $cell['today']])>
                  <span aria-hidden="true">{{ $cell['date']->day }}</span>
                  <span class="sr-only">{{ $label }}</span>
                </span>
              @endforeach
            @endforeach
          </div>
          <p class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-hidden="true">
            <span><span class="day day-goal day-legend"></span> Ziel geschafft</span>
            <span><span class="day day-learned day-legend"></span> gelernt</span>
            <span><span class="day day-none day-legend"></span> nicht gelernt</span>
          </p>
        </div>
      </section>

      {{-- --- Vocabulary ----------------------------------------------------- --}}
      @php($vocabulary = $overview['vocabulary'])
      <section class="card mt-4">
        <div class="card-header"><h2 class="card-title">Wortschatz</h2></div>
        <div class="card-body stack">
          @if ($vocabulary['mastered'] > 0)
            <p>
              Kann schon <strong>{{ $vocabulary['mastered'] === 1 ? '1 Wort' : $vocabulary['mastered'].' Wörter' }}</strong>
              @if ($vocabulary['mastered_week'] > 0)
                <span class="block text-sm text-success">+{{ $vocabulary['mastered_week'] }} diese Woche</span>
              @endif
            </p>
            <div>
              <h3 class="mb-1 text-sm font-semibold">Zuletzt gelernt</h3>
              <ul class="text-sm text-muted">
                @foreach ($overview['recent'] as $word)
                  <li>{{ $word }}</li>
                @endforeach
              </ul>
            </div>
          @else
            <p class="text-sm text-muted">
              {{ $vocabulary['in_box'] === 1 ? '1 Wort' : $vocabulary['in_box'].' Wörter' }} im Karteikasten,
              noch keins in „Kann ich“.
            </p>
          @endif
        </div>
      </section>
    @endunless

    {{-- --- Decks: no links, parents do not see card lists ----------------- --}}
    @if ($overview['decks']->isNotEmpty())
      <h2 class="mt-6 mb-2 text-base font-semibold">Stapel</h2>
      <p class="mb-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-hidden="true">
        <span><span class="legend-swatch progress-split-mastered"></span> kann {{ $child->name }}</span>
        <span><span class="legend-swatch progress-split-learning"></span> im Karteikasten</span>
        <span><span class="legend-swatch bg-surface-sunken"></span> noch nicht gelernt</span>
      </p>
      <ul class="list">
        @foreach ($overview['decks'] as $deck)
          @php($stats = $overview['stats'][$deck->id] ?? ['due' => 0, 'mastered_percent' => 0])
          @php($progress = $overview['progress'][$deck->id] ?? ['new' => 0, 'learning' => 0, 'mastered' => 0, 'total' => 0, 'mastered_percent' => 0, 'learning_percent' => 0])
          <li class="list-row">
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ $deck->name }}</span>
              <span class="block truncate text-sm text-muted">{{ $deck->directionLabel() }}</span>
              {{-- Share of the cards: "Kann ich" · in the box · not learned yet (issue #18). --}}
              <span class="progress-split mt-2" aria-hidden="true">
                <span class="progress-split-mastered" style="width: {{ $progress['mastered_percent'] }}%"></span>
                <span class="progress-split-learning" style="width: {{ $progress['learning_percent'] }}%"></span>
              </span>
              <span class="mt-1 block text-xs text-muted">{{ $progress['mastered'] }} kann {{ $child->name }} · {{ $progress['learning'] }} im Karteikasten · {{ $progress['new'] }} noch nicht gelernt</span>
            </span>
            @if ($stats['due'] > 0)
              <span class="badge badge-brand shrink-0">{{ $stats['due'] }} fällig</span>
            @endif
          </li>
        @endforeach
      </ul>
    @endif

    {{-- --- Tricky words ------------------------------------------------------ --}}
    @if ($overview['learned_ever'] && $overview['hard'] !== [])
      <section class="card mt-6">
        <div class="card-header"><h2 class="card-title">Knifflige Wörter</h2></div>
        <div class="card-body stack">
          <p class="text-sm text-muted">Zum Mitüben: Frag die Wörter einfach mal beim Abendessen ab.</p>
          <ul class="stack gap-1">
            @foreach ($overview['hard'] as $hard)
              <li>{{ $hard['word'] }} <span class="text-xs text-subtle">· {{ $hard['deck'] }}</span></li>
            @endforeach
          </ul>
        </div>
      </section>
    @endif
  @endif

  <p class="mt-6 text-center text-sm text-muted">
    {{ $child->name }} hat dir das freigegeben und kann es jederzeit beenden.
  </p>
</div>
@endsection
