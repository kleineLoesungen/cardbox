{{--
  "Stapel exportieren": tick own and followed decks, download them as one ZIP
  (DeckBundleService). Plain form, the download needs no JavaScript; "Alle
  auswählen" is a small script.

  @param bool $available  PHP can write ZIP files
  @param \Illuminate\Support\Collection<int, \App\Models\Deck> $decks
--}}
@extends('layouts.app')
@section('title', 'Stapel exportieren')

@section('main')
<div class="mx-auto max-w-md">
  <a href="/decks" class="back-link">← Stapel</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Stapel exportieren</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Die gewählten Stapel kommen als eine ZIP-Datei: je Stapel eine Tabelle und eine Übersicht.
    Über „Stapel importieren“ kommen sie wieder zurück. <a href="/help#csv" class="text-brand hover:underline">Format erklärt</a>
  </p>

  <x-form-error />

  @if (! $available)
    <p class="alert alert-warning">ZIP-Dateien sind auf diesem Server nicht verfügbar (PHP-Erweiterung „zip“ fehlt).</p>
  @elseif ($decks->isEmpty())
    <div class="empty"><p class="empty-title">Du hast noch keine Stapel</p></div>
  @else
    <form method="post" action="/decks/export" class="stack" data-export-form>
      @csrf
      <label class="flex min-h-12 items-center gap-3 px-1 text-sm font-medium">
        <input type="checkbox" class="checkbox" data-check-all="decks[]"> Alle auswählen
      </label>
      <ul class="list">
        @foreach ($decks as $deck)
          <li>
            <label class="list-row cursor-pointer">
              <input type="checkbox" class="checkbox shrink-0" name="decks[]" value="{{ $deck->id }}" @checked(in_array($deck->id, (array) old('decks', []), true))>
              <span class="min-w-0 flex-1">
                <span class="block truncate font-medium">{{ $deck->name }}</span>
                <span class="block truncate text-sm text-muted">
                  {{ $deck->directionLabel() }} · {{ $deck->cards_count === 1 ? '1 Karte' : ($deck->cards_count ?? 0).' Karten' }}
                  @unless ($deck->user_id === auth()->id()) · von {{ $deck->user->name }} @endunless
                </span>
              </span>
            </label>
          </li>
        @endforeach
      </ul>
      <button type="submit" class="btn btn-primary btn-lg btn-block">Als ZIP herunterladen</button>
    </form>
  @endif
</div>
@endsection
