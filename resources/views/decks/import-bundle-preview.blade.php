{{--
  Preview of a ZIP import: per deck what will happen. Nothing is saved yet;
  the ZIP waits on the server until "Importieren" (DeckBundleController).

  @param list<array> $plan  from DeckBundleService::plan
--}}
@extends('layouts.app')
@section('title', 'Vorschau')

@section('main')
@php($todo = collect($plan)->where('action', '!=', 'skip'))
<div class="mx-auto max-w-md">
  <a href="/decks/import" class="back-link">← Andere Datei wählen</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Vorschau</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    {{ $todo->where('action', 'update')->count() }} aktualisieren · {{ $todo->where('action', 'new')->count() }} neu
    @if (count($plan) > $todo->count()) · {{ count($plan) - $todo->count() }} übersprungen @endif
  </p>

  @if ($todo->isNotEmpty())
    <form method="post" action="/decks/import" class="mb-4">
      @csrf
      <button type="submit" class="btn btn-primary btn-lg btn-block">Importieren</button>
      <p class="hint mt-1 text-center">Gelöscht wird nichts. Dein Lernstand bleibt.</p>
    </form>
  @endif

  <ul class="stack gap-3">
    @foreach ($plan as $entry)
      <li class="card">
        <div class="card-body stack gap-1">
          <p class="flex flex-wrap items-center gap-2">
            @if ($entry['action'] === 'update')
              <span class="badge badge-brand">{{ $entry['copy'] ? 'deine Kopie' : 'aktualisieren' }}</span>
            @elseif ($entry['action'] === 'new')
              <span class="badge badge-success">neu</span>
            @else
              <span class="badge badge-neutral">übersprungen</span>
            @endif
            <span class="min-w-0 font-medium break-words">{{ $entry['action'] === 'update' ? $entry['deck']->name : $entry['name'] }}</span>
          </p>
          @if ($entry['rename'])
            <p class="text-sm text-muted">Name: {{ $entry['rename']['from'] }} → {{ $entry['rename']['to'] }}</p>
          @endif
          @if ($entry['action'] === 'skip')
            <p class="text-sm text-danger">{{ $entry['reason'] }}</p>
          @else
            <p class="text-sm text-muted">
              @if ($entry['action'] === 'new')
                {{ $entry['counts']['new'] === 1 ? '1 Karte' : $entry['counts']['new'].' Karten' }}
              @else
                {{ $entry['counts']['new'] }} neu · {{ $entry['counts']['update'] }} geändert · {{ $entry['counts']['same'] }} unverändert
              @endif
              @if ($entry['errors'] > 0) · <span class="text-danger">{{ $entry['errors'] === 1 ? '1 Zeile' : $entry['errors'].' Zeilen' }} nicht übernehmbar</span> @endif
            </p>
          @endif
          <p class="text-xs text-subtle break-all">{{ $entry['file'] }}</p>
        </div>
      </li>
    @endforeach
  </ul>
</div>
@endsection
