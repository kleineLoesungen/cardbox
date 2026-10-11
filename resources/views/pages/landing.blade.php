@extends('layouts.base')
@section('title', 'Start')

@section('body')
<div class="flex min-h-dvh flex-col">
  <header class="border-b border-border">
    <div class="container-app flex h-14 items-center">
      <span class="font-semibold tracking-tight">{{ $branding->name }}</span>
      <div class="ml-auto flex items-center gap-2">
        @auth
          <a href="/dashboard" class="btn btn-primary btn-sm">Zur Übersicht</a>
        @else
          @if ($firstSetup)
            <a href="/register" class="btn btn-primary btn-sm">Einrichten</a>
          @else
            <a href="/login" class="btn btn-primary btn-sm">Anmelden</a>
          @endif
        @endauth
      </div>
    </div>
  </header>

  <main class="container-app flex flex-1 flex-col items-center justify-center gap-6 py-16 text-center">
    <h1 class="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-5xl">
      Vokabeln lernen mit dem Karteikasten
    </h1>
    <p class="max-w-md text-balance text-muted">
      Karten anlegen oder von anderen übernehmen, jeden Tag ein paar Minuten
      üben – der Karteikasten merkt sich, was du schon kannst.
    </p>
    <div class="flex w-full max-w-xs flex-col gap-3">
      @if ($firstSetup)
        <a href="/register" class="btn btn-primary btn-lg btn-block">Ersten Administrator anlegen</a>
      @else
        <a href="/login" class="btn btn-primary btn-lg btn-block">Anmelden</a>
        @guest
          <a href="/register" class="btn btn-secondary btn-lg btn-block">Konto anlegen</a>
        @endguest
      @endif
    </div>
  </main>
</div>
@endsection
