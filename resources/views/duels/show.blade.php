{{--
  One duel (docs/specs/duell.md): code while places are free, the own
  "Los geht's", the ranking, the winner. Members see it in the app layout,
  guests (with $guestLink) in the plain one.

  @param \App\Models\Duel $duel
  @param \App\Models\DuelPlayer|null $me  null: only the result, seen by the code
  @param string $base  /duels/{id} or /duell/{token}
  @param list<array> $standings  DuelService::standings
  @param int $cards  number of words
  @param int $free  free places
  @param string|null $code  shown while places are free and while the result is shown
  @param \Illuminate\Support\Carbon|null $resultUntil  until when the result is shown (then deleted)
  @param bool $guest  plain layout for people without account
  @param bool $canManage  creator: may remove players
  @param bool $canRematch
  @param string|null $guestLink  the guest's secret link
--}}
@extends($guest ? 'layouts.guest' : 'layouts.app')
@section('title', 'Duell')

@section('main')
@php
    $time = fn (int $ms) => sprintf('%d:%02d min', intdiv((int) round($ms / 1000), 60), (int) round($ms / 1000) % 60);
    $winners = array_values(array_filter($standings, fn (array $r) => $r['winner']));
    // Others still playing; the own turn is the button above.
    $waiting = array_values(array_filter($standings, fn (array $r) => ! $r['finished'] && ($me === null || ! $r['player']->is($me))));
@endphp
<div class="mx-auto max-w-md">
  @unless ($guest)
    <a href="/duels" class="text-sm text-muted hover:text-content">← Duelle</a>
  @endunless
  <h1 @class(['text-2xl font-semibold tracking-tight break-words', 'mt-1' => ! $guest])>Duell: {{ $duel->deck_name }}</h1>
  <p class="mt-1 text-sm text-muted">
    {{ $duel->mode === 'choice' ? 'Auswahl aus 4' : 'Eintippen' }} · {{ $cards }} Wörter · {{ $duel->player_count }} Spieler
  </p>

  @if ($errors->any())
    <div class="mt-4"><x-form-error /></div>
  @endif

  {{-- The headline: who won, or what the duel waits for. --}}
  <div class="card mt-6">
    <div class="card-body stack text-center">
      @if ($duel->isFinished() && $winners !== [])
        <p class="text-2xl font-semibold">🏆 {{ count($winners) === 1 ? $winners[0]['name'].' gewinnt!' : 'Unentschieden: '.implode(' und ', array_column($winners, 'name')) }}</p>
      @elseif ($duel->isFinished())
        <p class="text-lg font-semibold">Niemand hat mitgespielt.</p>
      @else
        @if ($me !== null && $me->finished_at === null)
          <a href="{{ $base }}/play" class="btn btn-primary btn-lg btn-block">
            {{ $me->position === 0 ? "Los geht's" : 'Weiterspielen ('.$me->position.' / '.$cards.')' }}
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

  {{-- The code: to join while places are free, to see the result after the end. --}}
  @if ($code && ! $duel->isFinished())
    <div class="mt-4 rounded-ui border border-border bg-surface-sunken p-4 text-center" role="status">
      <p class="text-sm text-muted">Code zum Mitmachen</p>
      <p class="mt-1 font-mono text-3xl font-semibold tracking-widest select-all">{{ $code }}</p>
      <p class="mt-1 text-sm text-muted">Gilt bis <x-datetime :value="$duel->expires_at" /></p>
      <p class="text-sm text-muted">Mitmachen unter {{ url('/duell') }} – auch ohne Konto</p>
    </div>
  @elseif ($resultUntil)
    <p class="mt-4 text-center text-sm text-muted">
      Das Ergebnis ist bis <x-datetime :value="$resultUntil" /> zu sehen
      @if ($code) – auch mit dem Code <span class="font-mono font-semibold text-content">{{ $code }}</span>@endif.
      Danach wird das Duell gelöscht.
    </p>
  @endif

  {{-- A guest's only way back to this duel. --}}
  @if ($guestLink)
    <div class="card mt-4">
      <div class="card-body stack">
        <p class="text-sm">Heb dir diesen Link auf – damit siehst du später, wer gewonnen hat.</p>
        <input class="input font-mono text-sm" type="text" value="{{ $guestLink }}" readonly aria-label="Dein Link zum Duell" data-guest-link>
        <button type="button" class="btn btn-secondary btn-block" data-copy-link>Link kopieren</button>
      </div>
    </div>
  @endif

  <h2 class="mt-6 mb-2 text-base font-semibold">Spieler</h2>
  <ol class="list">
    @foreach ($standings as $i => $row)
      <li class="list-row">
        <span class="w-6 shrink-0 text-center font-semibold tabular-nums">{{ $row['winner'] ? '🏆' : ($row['finished'] ? $i + 1 : '–') }}</span>
        <span class="min-w-0 flex-1">
          <span class="block truncate font-medium">{{ $row['name'] }}@if ($me !== null && $row['player']->is($me)) <span class="font-normal text-muted">(du)</span>@endif</span>
          <span class="block text-sm text-muted tabular-nums">
            @if ($row['finished'])
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

  @if ($canRematch)
    <form method="post" action="{{ $base }}/rematch" class="mt-4">
      @csrf
      <button type="submit" class="btn btn-secondary btn-lg btn-block">Revanche</button>
      <p class="hint mt-1 text-center">Neue Wörter aus demselben Stapel, neuer Code.</p>
    </form>
  @endif
</div>

@if ($guestLink)
<script>
  // "Link kopieren": the guest's only way back, so make keeping it easy.
  document.querySelector("[data-copy-link]")?.addEventListener("click", async (e) => {
    const input = document.querySelector("[data-guest-link]");
    try {
      await navigator.clipboard.writeText(input.value);
      e.target.textContent = "Kopiert!";
    } catch (_) {
      input.select();
    }
  });
</script>
@endif
@endsection
