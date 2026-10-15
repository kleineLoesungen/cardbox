{{-- "Heute" — start page: what is due, the streak, "Meine Stapel". --}}
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

  @if ($decks->isEmpty())
    <div class="empty mt-6">
      <p class="empty-title">Los geht's mit deinem ersten Stapel</p>
      <p class="empty-hint">Ein Stapel sammelt Karten mit Wörtern – vorne zum Beispiel Deutsch, hinten Englisch.</p>
      {{-- Most children learn decks others shared, so finding one comes first. --}}
      <a href="/decks?tab=discover" class="btn btn-primary btn-lg">Stapel finden</a>
      <a href="/decks?new=1" class="btn btn-secondary btn-lg">Eigenen Stapel anlegen</a>
    </div>
  @else
    <div class="card mt-6">
      <div class="card-body stack text-center">
        {{-- Due and new cards from every deck (docs/specs/einfacher-lernen.md, A). --}}
        @php($waiting = $today['due'] + $today['new'])
        @if ($waiting > 0)
          <p class="text-lg">
            <strong class="text-2xl">{{ $waiting === 1 ? '1 Karte' : $waiting.' Karten' }}</strong><br>
            {{ $waiting === 1 ? 'wartet' : 'warten' }} auf dich
          </p>
          @if ($today['new'] > 0)
            <p class="-mt-2 text-sm text-muted">davon {{ $today['new'] }} neu</p>
          @endif
          <form method="post" action="/learn/round">
            @csrf
            <button type="submit" class="btn btn-primary btn-lg btn-block">Los geht's</button>
          </form>
        @else
          <p class="text-lg font-medium">Für heute alles geschafft.</p>
          @if ($today['more_new'] > 0)
            <form method="post" action="/learn/round">
              @csrf
              <input type="hidden" name="more" value="1">
              <button type="submit" class="btn btn-secondary btn-block">Noch {{ min($today['more_new'], (int) config('learning.round_size')) }} neue Karten</button>
            </form>
          @elseif ($today['tomorrow'] > 0)
            <p class="text-sm text-muted">Morgen {{ $today['tomorrow'] === 1 ? 'ist 1 Karte' : 'sind '.$today['tomorrow'].' Karten' }} dran.</p>
          @endif
        @endif
      </div>
    </div>

    <div class="mt-6 mb-2 flex items-center justify-between">
      <h2 class="text-base font-semibold">Meine Stapel</h2>
      <a href="/decks" class="text-sm text-brand hover:underline">Alle</a>
    </div>
    <ul class="list">
      @foreach ($decks as $deck)
        <x-deck-row :deck="$deck" :stats="$stats[$deck->id] ?? null" />
      @endforeach
    </ul>
  @endif
</div>
@endsection
