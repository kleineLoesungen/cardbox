{{--
  The part of the duel page that changes while others play: what the duel
  waits for, the rankings, the code. While the duel runs it asks the server
  every 3 seconds for itself again (DuelController::live) — so who waits
  sees when a round is over, when the next one starts and who won. Not while
  the tab is hidden or a sheet is open (a confirmation would lose its form).
  It sends its state along; unchanged, the server answers 204 and nothing is
  swapped (no lost focus, no reading position lost).

  Same parameters as duels/show.blade.php.
--}}
@php
    $time = fn (int $ms) => sprintf('%d:%02d min', intdiv((int) round($ms / 1000), 60), (int) round($ms / 1000) % 60);
    $winners = array_values(array_filter($standings, fn (array $r) => $r['winner']));
    // Others still playing; the own turn is the button above.
    $waiting = array_values(array_filter($standings, fn (array $r) => ! $r['finished'] && ($me === null || ! $r['player']->is($me))));
@endphp
<div id="duel-live"
     @unless ($duel->isFinished() || $base === '')
       data-poll
       hx-get="{{ $base }}/live?state={{ $state }}"
       hx-trigger="every 3s [!document.hidden && !document.querySelector('dialog[open]')], visibilitychange[!document.hidden] from:document"
       hx-swap="outerHTML"
     @endunless>
  <p class="mt-1 text-sm text-muted">
    @if ($duel->rounds > 1)Runde {{ $duel->round }} von {{ $duel->rounds }} · @endif{{ $duel->mode === 'choice' ? 'Auswahl aus 4' : 'Eintippen' }} · {{ $cards }} Wörter{{ $duel->rounds > 1 ? ' je Runde' : '' }} · {{ $duel->player_count }} Spieler
  </p>
  @if ($duel->repeat_from_round !== null && $duel->round >= $duel->repeat_from_round)
    {{-- The deck ran out (DuelService::drawRounds): from this round on words come again. --}}
    <p class="mt-1 text-sm text-muted">Alle Wörter des Stapels waren schon dran – ab Runde {{ $duel->repeat_from_round }} kommen sie wieder.</p>
  @endif

  @if ($errors->any())
    <div class="mt-4"><x-form-error /></div>
  @endif

  {{-- The headline: who won, what the duel waits for, or the next round. --}}
  <div class="card mt-6">
    <div class="card-body stack text-center">
      @if ($duel->isFinished() && $winners !== [])
        <p class="text-2xl font-semibold">🏆 {{ count($winners) === 1 ? $winners[0]['name'].' gewinnt!' : 'Unentschieden: '.implode(' und ', array_column($winners, 'name')) }}</p>
      @elseif ($duel->isFinished())
        <p class="text-lg font-semibold">Niemand hat mitgespielt.</p>
      @elseif ($duel->isBetweenRounds())
        <p class="text-lg font-semibold">Runde {{ $duel->round }} ist fertig!</p>
        @if ($canStartNextRound)
          <form method="post" action="{{ $base }}/next-round">
            @csrf
            <button type="submit" class="btn btn-primary btn-lg btn-block">Runde {{ $duel->round + 1 }} starten</button>
          </form>
          <p class="text-sm text-muted">Bis <x-datetime :value="$nextRoundUntil" />, sonst endet das Duell hier.</p>
        @else
          <p class="text-sm text-muted">{{ $creatorName }} startet gleich Runde {{ $duel->round + 1 }}.</p>
        @endif
      @else
        @if ($me !== null && $me->finished_at === null)
          @php
              $done = $me->position - $roundStart;
          @endphp
          <a href="{{ $base }}/play" class="btn btn-primary btn-lg btn-block">
            {{ $done <= 0 ? "Los geht's".($duel->rounds > 1 ? ' – Runde '.$duel->round : '') : 'Weiterspielen ('.$done.' / '.$cards.')' }}
          </a>
        @endif
        @if ($waiting !== [])
          <p class="text-sm text-muted">Warten auf: {{ implode(', ', array_column($waiting, 'name')) }}</p>
        @endif
        @if ($free > 0)
          <p class="text-sm text-muted">{{ $free === 1 ? 'Noch 1 Platz frei' : 'Noch '.$free.' Plätze frei' }}</p>
        @endif
      @endif
    </div>
  </div>

  {{-- The ranking of the round that just ended (several rounds only). --}}
  @if ($roundStandings)
    <h2 class="mt-6 mb-2 text-base font-semibold">Runde {{ $duel->round }}</h2>
    <ol class="list">
      @foreach ($roundStandings as $i => $row)
        <li class="list-row">
          <span class="w-6 shrink-0 text-center font-semibold tabular-nums">{{ $row['finished'] ? $i + 1 : '–' }}</span>
          <span class="min-w-0 flex-1">
            <span class="block truncate font-medium">{{ $row['name'] }}@if ($me !== null && $row['player']->is($me)) <span class="font-normal text-muted">(du)</span>@endif</span>
            <span class="block text-sm text-muted tabular-nums">{{ $row['finished'] ? $row['correct'].' / '.$cards.' richtig · '.$time($row['time_ms']) : 'nicht fertig' }}</span>
          </span>
        </li>
      @endforeach
    </ol>
  @endif

  {{-- The code: to join while places are free, to see the result after the end. --}}
  @if ($code && ! $duel->isFinished())
    <div class="mt-4 rounded-ui border border-border bg-surface-sunken p-4 text-center" role="status">
      <p class="text-sm text-muted">Code zum Mitmachen</p>
      <p class="mt-1 font-mono text-3xl font-semibold tracking-widest select-all">{{ $code }}</p>
      <p class="mt-1 text-sm text-muted">Gilt bis <x-datetime :value="$duel->expires_at" /></p>
      <p class="text-sm text-muted">Mitmachen unter {{ url('/duell') }} – auch ohne Konto</p>
      {{-- The code rides behind "#": it fills the code field, but never reaches a server log. --}}
      <button type="button" class="btn btn-secondary btn-block mt-3" data-share-invite
              data-share-title="Duell: {{ $duel->deck_name }}"
              data-share-text="Machst du mit beim Duell „{{ $duel->deck_name }}“? Code: {{ $code }}"
              data-share-url="{{ url('/duell') }}#{{ $code }}">Einladung teilen</button>
    </div>
  @elseif ($resultUntil)
    <p class="mt-4 text-center text-sm text-muted">
      Das Ergebnis ist bis <x-datetime :value="$resultUntil" /> zu sehen
      @if ($code) – auch mit dem Code <span class="font-mono font-semibold text-content">{{ $code }}</span>@endif.
      Danach wird das Duell gelöscht.
    </p>
  @endif

  {{-- A guest's only way back to this duel: above the ranking, so it stays in sight. --}}
  @if ($guestLink)
    <div class="card mt-4">
      <div class="card-body stack">
        <p class="text-sm">Heb dir diesen Link auf – damit siehst du später, wer gewonnen hat.</p>
        <input class="input font-mono text-sm" type="text" value="{{ $guestLink }}" readonly aria-label="Dein Link zum Duell" data-guest-link>
        <button type="button" class="btn btn-secondary btn-block" data-copy-link>Link kopieren</button>
      </div>
    </div>
  @endif

  <h2 class="mt-6 mb-2 text-base font-semibold">{{ $duel->rounds > 1 ? ($duel->isFinished() ? 'Gesamt' : 'Zwischenstand') : 'Spieler' }}</h2>
  <ol class="list">
    @foreach ($standings as $i => $row)
      <li class="list-row">
        <span class="w-6 shrink-0 text-center font-semibold tabular-nums">{{ $row['winner'] ? '🏆' : ($row['played'] ? $i + 1 : '–') }}</span>
        <span class="min-w-0 flex-1">
          <span class="block truncate font-medium">{{ $row['name'] }}@if ($me !== null && $row['player']->is($me)) <span class="font-normal text-muted">(du)</span>@endif</span>
          <span class="block text-sm text-muted tabular-nums">
            @if ($row['played'] && $duel->rounds > 1)
              {{ $row['correct'] }} richtig · {{ $time($row['time_ms']) }}
            @elseif ($row['played'])
              {{ $row['correct'] }} / {{ $cards }} richtig · {{ $time($row['time_ms']) }}
            @elseif ($duel->isFinished())
              nicht fertig
            @else
              spielt noch
            @endif
          </span>
        </span>
        @if ($canManage && $me !== null && ! $row['player']->is($me) && ! $duel->isFinished())
          <form method="post" action="{{ $base }}/players/{{ $row['player']->id }}/delete" class="shrink-0"
                data-confirm-action="Entfernen" data-confirm="{{ $row['name'] }} aus dem Duell entfernen?">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">Entfernen</button>
          </form>
        @endif
      </li>
    @endforeach
  </ol>

</div>
