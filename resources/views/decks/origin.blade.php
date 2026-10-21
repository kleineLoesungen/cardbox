{{--
  "Änderungen im Original": copied cards whose original changed since the
  copy. Per card: now (yours) and new (original), "Übernehmen" or
  "Behalten"; on top "Alle übernehmen".

  @param \App\Models\Deck $deck
  @param list<array{card: \App\Models\Card, front: list<string>, back: list<string>, hint: string|null}> $entries
--}}
@extends('layouts.app')
@section('title', 'Änderungen im Original')

@section('main')
@php($frontTag = \App\Enums\Language::tryFrom($deck->front_language)?->tag())
@php($backTag = \App\Enums\Language::tryFrom($deck->back_language)?->tag())
<div class="mx-auto max-w-md">
  <a href="/decks/{{ $deck->id }}" class="back-link">← {{ $deck->name }}</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Änderungen im Original</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Diese Karten wurden dort geändert, wo du sie kopiert hast. Dein Lernstand bleibt in jedem Fall.
  </p>

  @if (count($entries) > 1)
    <form method="post" action="/decks/{{ $deck->id }}/origin/take-all" class="mb-4">
      @csrf
      <button type="submit" class="btn btn-primary btn-lg btn-block">Alle {{ count($entries) }} übernehmen</button>
    </form>
  @endif

  <div class="stack">
    @foreach ($entries as $entry)
      @php($card = $entry['card'])
      <div class="card">
        <div class="card-body stack gap-3">
          <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
            <span class="text-subtle">Deine</span>
            <span class="min-w-0 break-words">
              <span lang="{{ $frontTag }}">{{ implode(', ', $card->front) }}</span>
              @if ($card->hint) <span class="text-subtle">{{ $card->hint }}</span>@endif
              – <span class="text-muted" lang="{{ $backTag }}">{{ implode(', ', $card->back) }}</span>
            </span>
            <span class="font-medium text-brand">Neu</span>
            <span class="min-w-0 break-words font-medium">
              <span lang="{{ $frontTag }}">{{ implode(', ', $entry['front']) }}</span>
              @if ($entry['hint']) <span class="text-subtle">{{ $entry['hint'] }}</span>@endif
              – <span lang="{{ $backTag }}">{{ implode(', ', $entry['back']) }}</span>
            </span>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <form method="post" action="/decks/{{ $deck->id }}/cards/{{ $card->id }}/origin/keep">
              @csrf
              <button type="submit" class="btn btn-secondary btn-block">Behalten</button>
            </form>
            <form method="post" action="/decks/{{ $deck->id }}/cards/{{ $card->id }}/origin/take">
              @csrf
              <button type="submit" class="btn btn-primary btn-block">Übernehmen</button>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection
