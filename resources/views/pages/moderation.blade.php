{{--
  Moderation: all decks other people share. Moderators can end sharing or
  delete from the deck page ("⋯" menu), correct single cards there, and
  merge duplicate cards (/moderation/duplicates).
--}}
@extends('layouts.app')
@section('title', 'Moderation')

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">Moderation</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Alle geteilten Stapel. Unpassende Inhalte kannst du im Stapel über „⋯“
    nicht mehr teilen oder löschen, einzelne Karten dort korrigieren. Im
    Wörterbuch kannst du Karten auch bearbeiten oder löschen.
  </p>

  <a href="/moderation/duplicates" class="list-row mb-4 rounded-ui border border-border bg-surface-raised">
    <span class="min-w-0 flex-1">
      <span class="block font-medium">Doppelte Karten</span>
      <span class="block text-sm text-muted">Gleiche Wörter in mehreren geteilten Stapeln zusammenführen</span>
    </span>
    <span aria-hidden="true" class="text-subtle">›</span>
  </a>

  <form method="get" action="/moderation" class="mb-4">
    <input class="input" type="search" name="q" value="{{ $search }}" placeholder="Stapel suchen …" aria-label="Stapel suchen">
  </form>

  @if ($decks->isEmpty())
    <div class="empty">
      <p class="empty-title">Keine geteilten Stapel</p>
    </div>
  @else
    <ul class="list">
      @foreach ($decks as $deck)
        <li>
          <a href="/decks/{{ $deck->id }}" class="list-row">
            <span class="min-w-0 flex-1">
              <span class="block truncate font-medium">{{ $deck->name }}</span>
              <span class="block truncate text-sm text-muted">
                von {{ $deck->user->name }} · {{ $deck->cards_count }} Karten ·
                {{ $deck->followers_count === 1 ? '1 folgt' : $deck->followers_count.' folgen' }}
              </span>
            </span>
            <span aria-hidden="true" class="text-subtle">›</span>
          </a>
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endsection
