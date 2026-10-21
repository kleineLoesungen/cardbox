{{--
  "Stapel importieren": choose a ZIP from "Stapel exportieren", then preview.

  @param bool $available  PHP can read ZIP files
--}}
@extends('layouts.app')
@section('title', 'Stapel importieren')

@section('main')
<div class="mx-auto max-w-md">
  <a href="/decks" class="back-link">← Stapel</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Stapel importieren</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Eine ZIP-Datei aus „Stapel exportieren“. Deine Stapel werden aktualisiert, alle anderen kommen
    als neue private Stapel dazu. Gelöscht wird nichts, dein Lernstand bleibt.
    <a href="/help#csv" class="text-brand hover:underline">Format erklärt</a>
  </p>

  <x-form-error />

  @if (! $available)
    <p class="alert alert-warning">ZIP-Dateien sind auf diesem Server nicht verfügbar (PHP-Erweiterung „zip“ fehlt).</p>
  @else
    <form method="post" action="/decks/import/preview" enctype="multipart/form-data" class="stack">
      @csrf
      <div class="field">
        <label class="label" for="bundle-file">ZIP-Datei</label>
        <input class="file-input" type="file" id="bundle-file" name="file" accept=".zip,application/zip" required>
        <p class="hint">Höchstens {{ intdiv((int) config('learning.bundle_max_kilobytes'), 1024) }} MB und {{ config('learning.bundle_max_decks') }} Stapel.</p>
      </div>
      <button type="submit" class="btn btn-primary btn-lg btn-block">Vorschau</button>
    </form>
  @endif
</div>
@endsection
