{{-- "Liste einfügen": paste text or choose a file, then preview. --}}
@extends('layouts.app')
@section('title', 'Liste einfügen')

@section('main')
<div class="mx-auto max-w-md">
  <a href="/decks/{{ $deck->id }}" class="text-sm text-muted hover:text-content">← {{ $deck->name }}</a>
  <h1 class="mt-1 mb-1 text-2xl font-semibold tracking-tight">Liste einfügen</h1>
  <p class="mb-4 text-sm text-muted">Viele Karten auf einmal in „{{ $deck->name }}“.</p>

  {{-- Admin import into somebody else's deck (Admin\DeckImportController). --}}
  @if (str_starts_with($base, '/admin/'))
    <p class="alert alert-warning mb-4">Verwaltung: Du änderst Karten von {{ $deck->user->name }}. Alle, die mit dem Stapel lernen, sehen die Änderungen.</p>
  @endif

  <x-form-error />

  <form method="post" action="{{ $base }}/preview" enctype="multipart/form-data" class="stack">
    @csrf

    <div class="field">
      <label class="label" for="text">Eine Karte pro Zeile: vorne = hinten</label>
      @php($example = match ($deck->front_language.'-'.$deck->back_language) {
          'de-en' => "Haus = house\nBaum = tree",
          'en-de' => "house = Haus\ntree = Baum",
          'formula-result' => "3 · 4 = 12\n7 + 8 = 15",
          default => $deck->frontLabel().' = '.$deck->backLabel()."\n".$deck->frontLabel().' = '.$deck->backLabel(),
      })
      <pre class="code-block">{{ $example }}</pre>
      <textarea class="textarea font-mono" id="text" name="text" rows="10"
                placeholder="{{ $deck->frontLabel() }} = {{ $deck->backLabel() }}"
                autocapitalize="off" autocorrect="off" spellcheck="false">{{ old('text', $draft) }}</textarea>
      <p class="hint">Du kannst auch zwei Spalten aus Excel, Word oder Numbers kopieren und hier einfügen.</p>
    </div>

    <details>
      <summary class="cursor-pointer text-sm text-brand">Oder eine Datei wählen</summary>
      <div class="field mt-3">
        <input class="block w-full text-sm" type="file" name="file" accept=".txt,.csv,.tsv,text/plain,text/csv">
        <p class="hint">Textdatei (.txt, .csv), höchstens {{ config('learning.import_max_kilobytes') }} KB.</p>
        <p class="hint">Eine Datei aus „Als CSV exportieren“ (Kopfzeile <code>ID;…</code>) ändert bekannte Karten, statt sie doppelt anzulegen. Gelöscht wird nichts, der Lernstand bleibt. <a href="/help#csv" class="text-brand hover:underline">Format erklärt</a></p>
      </div>
    </details>

    <button type="submit" class="btn btn-primary btn-lg btn-block">Vorschau</button>
  </form>
</div>
@endsection
