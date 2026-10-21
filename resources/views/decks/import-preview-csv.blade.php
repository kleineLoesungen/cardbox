{{--
  Preview of a CSV import (a file with an "ID" header, as exported). Shows
  what would happen per row: new, changed (old → new), unchanged, errors.
  Nothing is saved yet; the text travels in a hidden field and is parsed
  again on import (CardCsvService).

  @param \App\Models\Deck $deck
  @param string $base    where the forms post to (own or admin import)
  @param string $text
  @param array  $result  from CardCsvService::parse
--}}
@extends('layouts.app')
@section('title', 'Vorschau')

@section('main')
@php($counts = $result['counts'])
@php($changes = $counts['new'] + $counts['update'])
@php($side = fn (array $meanings) => implode(', ', $meanings))
<div class="mx-auto max-w-md">
  <a href="{{ $base }}?back=1" class="back-link">← Zurück zur Liste</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Vorschau</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    {{ $counts['new'] }} neu · {{ $counts['update'] }} geändert · {{ $counts['same'] }} unverändert
    @if ($result['errors'] !== []) · {{ count($result['errors']) }} Fehler @endif
  </p>

  @if ($result['swapped'])
    <p class="alert alert-info mb-4">Die Spalten waren andersherum ({{ $deck->backLabel() }} zuerst) und wurden getauscht.</p>
  @endif

  @if ($result['errors'] !== [])
    <div class="mb-4">
      <h2 class="mb-2 text-sm font-semibold text-danger">
        {{ count($result['errors']) === 1 ? '1 Zeile wird nicht übernommen' : count($result['errors']).' Zeilen werden nicht übernommen' }}
      </h2>
      <ul class="list">
        @foreach ($result['errors'] as $error)
          <li class="list-row flex-col items-start gap-1">
            <span class="text-sm break-all">Zeile {{ $error['line'] }}: {{ $error['text'] }}</span>
            <span class="text-xs font-medium text-danger">{{ $error['reason'] }}</span>
          </li>
        @endforeach
      </ul>
    </div>
  @endif

  @if ($changes > 0)
    <form method="post" action="{{ $base }}" class="mb-4">
      @csrf
      {{-- textarea rather than a hidden input: keeps line breaks reliably. --}}
      <textarea name="text" hidden>{{ $text }}</textarea>
      <button type="submit" class="btn btn-primary btn-lg btn-block">Importieren</button>
      <p class="hint mt-1 text-center">Gelöscht wird nichts. Geänderte Karten behalten ihren Lernstand.</p>
    </form>

    <ul class="list">
      @foreach ($result['rows'] as $row)
        @continue($row['action'] === 'same')
        <li class="list-row flex-col items-stretch gap-1">
          <span class="flex items-center gap-2">
            <span class="badge {{ $row['action'] === 'new' ? 'badge-success' : 'badge-brand' }}">{{ $row['action'] === 'new' ? 'neu' : 'geändert' }}</span>
            <span class="text-xs text-subtle">Zeile {{ $row['line'] }}</span>
          </span>
          @if ($row['action'] === 'update')
            {{-- The old version; only what changes is struck through. --}}
            <span class="card-row-sides text-sm text-muted">
              <span @class(['line-through' => $row['card']->front !== $row['front'] || $row['card']->hint !== $row['hint']])>{{ $side($row['card']->front) }}@if ($row['card']->hint) {{ $row['card']->hint }}@endif</span>
              <span @class(['line-through' => $row['card']->back !== $row['back']])>{{ $side($row['card']->back) }}</span>
            </span>
          @endif
          <span class="card-row-sides">
            <span>{{ $side($row['front']) }}@if ($row['hint']) <span class="text-xs text-subtle">{{ $row['hint'] }}</span>@endif</span>
            <span class="text-muted">{{ $side($row['back']) }}</span>
          </span>
          @if ($row['note'])
            <span class="text-xs text-muted">{{ $row['note'] }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @else
    <div class="empty">
      <p class="empty-title">{{ $counts['same'] > 0 ? 'Nichts zu ändern' : 'Keine gültige Zeile gefunden' }}</p>
      <p class="empty-hint">
        {{ $counts['same'] > 0 ? 'Alle Karten in der Datei sind schon genau so im Stapel.' : 'Prüfe die Kopfzeile, z. B. ID;de;en;Hinweis, und die Zeilen darunter.' }}
      </p>
      <a href="/decks/{{ $deck->id }}" class="btn btn-primary">Zum Stapel</a>
    </div>
  @endif
</div>
@endsection
