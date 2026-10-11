{{--
  "Heute" — start page after sign-in. Stage 1 shows the decks; the
  learning overview (due cards, streak) follows in stage 2.
--}}
@extends('layouts.app')
@section('title', 'Heute')

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">Hallo, {{ $user->name }}</h1>
  <p class="mt-1 mb-6 text-sm text-muted">Schön, dass du da bist.</p>

  @if ($decks->isEmpty())
    <div class="empty">
      <p class="empty-title">Los geht's mit deinem ersten Stapel</p>
      <p class="empty-hint">Ein Stapel sammelt Karten mit Wörtern – vorne zum Beispiel Deutsch, hinten Englisch.</p>
      <a href="/decks?new=1" class="btn btn-primary btn-lg">Eigenen Stapel anlegen</a>
      <a href="/decks?tab=discover" class="btn btn-secondary btn-lg">Geteilte Stapel entdecken</a>
    </div>
  @else
    <div class="mb-2 flex items-center justify-between">
      <h2 class="text-base font-semibold">Meine Stapel</h2>
      <a href="/decks" class="text-sm text-brand hover:underline">Alle</a>
    </div>
    <ul class="list">
      @foreach ($decks as $deck)
        <x-deck-row :deck="$deck" />
      @endforeach
    </ul>
  @endif
</div>
@endsection
