{{-- "Duelle": enter a code, the own duels (docs/specs/duell.md). --}}
@extends('layouts.app')
@section('title', 'Duelle')

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">Duelle</h1>
  <p class="mt-1 text-sm text-muted">Alle bekommen dieselben Wörter – wer mehr weiß, gewinnt. Ein Duell startest du auf der Seite eines Stapels.</p>

  <form method="post" action="/duels/join" class="card mt-6">
    @csrf
    <div class="card-body stack">
      <x-form-error bag="duel" />
      <div class="field">
        <label class="label" for="duel-code">Code eingeben</label>
        <input class="input input-lg font-mono uppercase tracking-widest" type="text" id="duel-code" name="code"
               value="{{ old('code') }}" required maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false"
               placeholder="XXXX-XXXX">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Mitmachen</button>
    </div>
  </form>

  @if ($duels->isEmpty())
    <p class="mt-6 text-center text-sm text-muted">Noch keine Duelle.</p>
  @else
    <h2 class="mt-6 mb-2 text-base font-semibold">Meine Duelle</h2>
    <ul class="list">
      @foreach ($duels as $duel)
        @php
            $rows = $standings[$duel->id];
            $winners = array_values(array_filter($rows, fn (array $r) => $r['winner']));
            $done = count(array_filter($rows, fn (array $r) => $r['finished']));
        @endphp
        <li>
          <a href="/duels/{{ $duel->id }}" class="list-row">
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ $duel->deck_name }}</span>
              <span class="block truncate text-sm text-muted">
                @if (! $duel->isFinished())
                  läuft · {{ $done }} von {{ $duel->player_count }} fertig
                @elseif ($winners !== [])
                  🏆 {{ implode(' und ', array_column($winners, 'name')) }}
                @else
                  beendet
                @endif
              </span>
            </span>
            @unless ($duel->isFinished())
              <span class="badge badge-brand shrink-0">läuft</span>
            @endunless
            <span aria-hidden="true" class="text-subtle">›</span>
          </a>
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endsection
