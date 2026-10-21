{{-- "Heute" — start page: daily goal, what is due, the streak, the vocabulary, the decks marked "Lerne ich". --}}
@extends('layouts.app')
@section('title', 'Heute')

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">Hallo, {{ $user->name }}</h1>

  @if ($today['streak'] > 0)
    <p class="mt-1 text-sm text-muted">
      <span aria-hidden="true" class="mr-1">🔥</span>
      {{ $today['streak'] === 1 ? '1 Tag' : $today['streak'].' Tage' }} in Folge gelernt
    </p>
  @endif

  @if ($hasOtherDecks)
    <div class="empty mt-6">
      <p class="empty-title">Du lernst gerade keinen Stapel</p>
      <p class="empty-hint">Öffne einen Stapel und tippe auf „Lernen“ oder „Wieder lernen“, dann kommt er hier jeden Tag dran.</p>
      <a href="/decks" class="btn btn-primary btn-lg">Zu meinen Stapeln</a>
    </div>
  @elseif ($decks->isEmpty())
    <div class="empty mt-6">
      <p class="empty-title">Los geht's mit deinem ersten Stapel</p>
      <p class="empty-hint">Ein Stapel sammelt Karten mit Wörtern – vorne zum Beispiel Deutsch, hinten Englisch.</p>
      {{-- Most children learn decks others shared, so finding one comes first. --}}
      <a href="/decks?tab=discover" class="btn btn-primary btn-lg">Stapel finden</a>
      <a href="/decks?new=1" class="btn btn-secondary btn-lg">Eigenen Stapel anlegen</a>
    </div>
  @else
    {{-- The last 7 days: green = daily goal met (with how many times), grey = not (or not yet today). --}}
    <ol class="week-dots mt-3" aria-label="Tagesziel der letzten 7 Tage">
      @foreach ($week as $day)
        @php
            $label = $day['date']->locale('de')->isoFormat('dd D.M.').': '.match (true) {
                $day['times'] > 1 => "Ziel {$day['times']}-mal erreicht",
                $day['met'] => 'Ziel erreicht',
                $day['today'] => 'Ziel noch offen',
                default => 'Ziel nicht erreicht',
            };
        @endphp
        <li class="week-dots-day">
          {{-- The number: how many times over the goal was reached (20 → 1, 40 → 2, …). --}}
          <span @class(['week-dot', 'week-dot-met' => $day['met'], 'week-dot-today' => $day['today']]) aria-hidden="true">{{ $day['met'] ? $day['times'] : '' }}</span>
          <span aria-hidden="true">{{ $day['today'] ? 'Heute' : $day['date']->locale('de')->isoFormat('dd') }}</span>
          <span class="sr-only">{{ $label }}</span>
        </li>
      @endforeach
    </ol>
    <div class="card mt-6">
      <div class="card-body stack text-center">
        {{-- Due and new cards from every deck (docs/specs/einfacher-lernen.md, A). --}}
        @php
            $waiting = $today['due'] + $today['new'];
            // Daily goal (docs/specs/motivierender-start.md, B): full when reached or nothing is left.
            // Full only when the day counts: goal reached, or done with nothing left after learning.
            $reached = $today['answers'] >= $today['goal'];
            $full = $reached || $today['goal_met'];
            $percent = $full ? 100 : (int) floor(100 * $today['answers'] / max(1, $today['goal']));
        @endphp
        <div>
          <div @class(['goal-ring', 'goal-ring-done' => $full]) style="--goal: {{ $percent }}"
               role="progressbar" aria-valuemin="0" aria-valuemax="{{ $today['goal'] }}" aria-valuenow="{{ min($today['answers'], $today['goal']) }}"
               aria-label="Tagesziel: {{ $today['answers'] }} von {{ $today['goal'] }} Karten gewusst{{ $full ? ', geschafft' : '' }}">
            <span class="text-2xl font-semibold tabular-nums">{{ $today['answers'] }}<span class="text-base font-normal text-muted"> / {{ $today['goal'] }}</span></span>
          </div>
          <p class="mt-1 text-sm text-muted">heute gewusst</p>
        </div>

        @if ($reached)
          <p class="text-lg font-semibold text-success">Tagesziel geschafft!</p>
        @elseif ($waiting === 0 && $today['goal_met'])
          <p class="text-lg font-medium">Für heute alles geschafft.</p>
        @elseif ($waiting === 0)
          {{-- Nothing due and nothing learned yet: a little practice keeps the streak. --}}
          <p class="text-lg font-medium">Heute ist nichts dran.</p>
          <p class="-mt-2 text-sm text-muted">Üb ein paar Karten in einem Stapel, dann zählt der Tag für deine Lernserie.</p>
        @endif

        @if ($waiting > 0)
          @unless ($reached)
            <p class="text-lg">
              <strong class="text-2xl">{{ $waiting === 1 ? '1 Karte' : $waiting.' Karten' }}</strong><br>
              {{ $waiting === 1 ? 'wartet' : 'warten' }} auf dich
            </p>
          @endunless
          @if ($today['new'] > 0)
            <p class="-mt-2 text-sm text-muted">{{ $reached ? ($waiting === 1 ? '1 Karte wartet noch' : $waiting.' Karten warten noch').', davon '.$today['new'].' neu' : 'davon '.$today['new'].' neu' }}</p>
          @elseif ($reached)
            <p class="-mt-2 text-sm text-muted">{{ $waiting === 1 ? '1 Karte wartet noch' : $waiting.' Karten warten noch' }}</p>
          @endif
          <form method="post" action="/learn/round">
            @csrf
            {{-- After the goal learning on is welcome, but no longer the one main action. --}}
            <button type="submit" @class(['btn btn-lg btn-block', 'btn-primary' => ! $reached, 'btn-secondary' => $reached])>{{ $reached ? 'Weiterlernen' : "Los geht's" }}</button>
          </form>
        @elseif ($today['more_new'] > 0)
          <form method="post" action="/learn/round">
            @csrf
            <input type="hidden" name="more" value="1">
            <button type="submit" class="btn btn-secondary btn-block">Noch {{ min($today['more_new'], $today['round_size']) }} neue Karten</button>
          </form>
        @elseif ($today['tomorrow'] > 0)
          <p class="text-sm text-muted">Morgen {{ $today['tomorrow'] === 1 ? 'ist 1 Karte' : 'sind '.$today['tomorrow'].' Karten' }} dran.</p>
        @endif
      </div>
    </div>

    {{-- The growing vocabulary (docs/specs/motivierender-start.md, C). --}}
    @if ($vocabulary['mastered'] > 0)
      <p class="mt-4 text-center">
        Du kannst schon <strong>{{ $vocabulary['mastered'] === 1 ? '1 Wort' : $vocabulary['mastered'].' Wörter' }}</strong>
        @if ($vocabulary['mastered_week'] > 0)
          <span class="block text-sm text-success">+{{ $vocabulary['mastered_week'] }} diese Woche</span>
        @endif
      </p>
    @elseif ($vocabulary['in_box'] > 0)
      <p class="mt-4 text-center text-sm text-muted">
        <strong class="text-content">{{ $vocabulary['in_box'] === 1 ? '1 Wort' : $vocabulary['in_box'].' Wörter' }}</strong> im Karteikasten.
        Nach ein paar Wiederholungen kannst du {{ $vocabulary['in_box'] === 1 ? 'es' : 'sie' }}.
      </p>
    @endif

    <div class="mt-6 mb-2 flex items-center justify-between">
      <h2 class="text-base font-semibold">Lerne ich</h2>
      <a href="/decks" class="link-action -mr-3 px-3 text-sm">Alle</a>
    </div>
    <ul class="list">
      @foreach ($decks as $deck)
        <x-deck-row :deck="$deck" :stats="$stats[$deck->id] ?? null" />
      @endforeach
    </ul>
  @endif
</div>
@endsection
