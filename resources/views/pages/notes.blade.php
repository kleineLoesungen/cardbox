{{--
  Musterseite fuer eine Ressource: Liste plus Anlegeformular, beides ueber
  htmx ohne Seitenneuladen. Das ist die Vorlage zum Kopieren.
--}}
@extends('layouts.app')
@section('title', 'Notizen')

@section('main')
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">Notizen</h1>
  <p class="mt-1 text-sm text-muted">Beispielressource. Zeigt Anlegen, Auflisten und Löschen.</p>
</div>

<div class="grid gap-6 lg:grid-cols-[20rem_1fr]">

  {{-- --- Anlegen ---------------------------------------------------------- --}}
  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Neue Notiz</h2></div>
    {{-- Formular nach erfolgreichem Absenden leeren; bei Fehler bleibt es stehen. --}}
    <form class="card-body stack"
          hx-post="/notes"
          hx-target="#note-list"
          hx-swap="afterbegin"
          hx-on::after-request="if (event.detail.successful) { this.reset(); document.getElementById('note-empty')?.remove(); }">

      <div class="field">
        <label class="label" for="title">Titel</label>
        <input class="input" type="text" id="title" name="title" required maxlength="200">
      </div>

      <div class="field">
        <label class="label" for="body">Text</label>
        <textarea class="textarea" id="body" name="body" rows="4"></textarea>
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        <span class="htmx-indicator spinner"></span>
        Speichern
      </button>
    </form>
  </div>

  {{-- --- Liste ------------------------------------------------------------ --}}
  <div class="min-w-0">
    <div id="note-list" class="stack">
      @foreach ($notes as $note)
        <x-note-item :note="$note" />
      @endforeach
    </div>

    @if ($notes->isEmpty())
      <div class="empty" id="note-empty">
        <p class="empty-title">Noch keine Notizen</p>
        <p class="empty-hint">Leg mit dem Formular die erste an.</p>
      </div>
    @endif
  </div>
</div>
@endsection
