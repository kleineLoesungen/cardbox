{{--
  Erscheint, wenn die installierte App ohne Netz geoeffnet wird. Der Service
  Worker haelt genau diese Seite vorraetig — sie darf deshalb nichts Anmelde-
  pflichtiges und nichts Veraenderliches zeigen.
--}}
@extends('layouts.base')
@section('title', 'Keine Verbindung')

@section('body')
<main class="mx-auto flex min-h-dvh max-w-sm flex-col items-center justify-center gap-4 px-6 text-center">
  <img src="/icons/icon-192.png" alt="" class="size-16 rounded-2xl">
  <h1 class="text-xl font-semibold">Keine Verbindung</h1>
  <p class="text-sm text-muted">
    {{ $branding->name }} braucht eine Internetverbindung.
    Sobald sie wieder da ist, geht es weiter.
  </p>
  <button type="button" class="btn btn-primary" onclick="location.reload()">Erneut versuchen</button>
</main>

<script>
  // Kommt die Verbindung zurueck, von selbst neu laden.
  window.addEventListener("online", () => location.reload());
</script>
@endsection
