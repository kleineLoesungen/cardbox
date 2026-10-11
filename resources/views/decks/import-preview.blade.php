{{--
  Preview of "Liste einfügen". Nothing is saved yet; the text travels in a
  hidden field and is parsed again on import.
--}}
@extends('layouts.app')
@section('title', 'Vorschau')

@section('main')
@php($count = count($result['rows']))
<div class="mx-auto max-w-md">
  <a href="/decks/{{ $deck->id }}/import?back=1" class="text-sm text-muted hover:text-content">← Zurück zur Liste</a>
  <h1 class="mt-1 mb-4 text-2xl font-semibold tracking-tight">Vorschau</h1>

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

  @if ($count > 0)
    <form method="post" action="/decks/{{ $deck->id }}/import" class="mb-4">
      @csrf
      {{-- textarea rather than a hidden input: keeps line breaks reliably. --}}
      <textarea name="text" hidden>{{ $text }}</textarea>
      <button type="submit" class="btn btn-primary btn-lg btn-block">
        {{ $count === 1 ? '1 Karte hinzufügen' : $count.' Karten hinzufügen' }}
      </button>
    </form>

    <ul class="list">
      <li class="list-row text-xs font-medium text-subtle">
        <span class="card-row-sides"><span>{{ $deck->frontLabel() }}</span><span>{{ $deck->backLabel() }}</span></span>
      </li>
      @foreach ($result['rows'] as $row)
        <li class="list-row">
          <span class="card-row-sides">
            <span>{{ $row['front'] }}</span>
            <span class="text-muted">{{ $row['back'] }}</span>
          </span>
        </li>
      @endforeach
    </ul>
  @else
    <div class="empty">
      <p class="empty-title">Keine gültige Zeile gefunden</p>
      <p class="empty-hint">Schreib jede Karte in eine eigene Zeile, mit „=“ zwischen vorne und hinten.</p>
      <a href="/decks/{{ $deck->id }}/import?back=1" class="btn btn-primary">Zurück zur Liste</a>
    </div>
  @endif
</div>
@endsection
