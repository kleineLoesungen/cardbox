@extends('layouts.base')
@section('title', 'Anmelden')

@section('body')
<main class="mx-auto flex min-h-dvh w-full max-w-sm flex-col justify-center gap-6 px-6 py-12">
  <div class="text-center">
    <h1 class="text-xl font-semibold tracking-tight">Anmelden</h1>
    <p class="mt-1 text-sm text-muted">Willkommen zurück bei {{ $branding->name }}.</p>
  </div>

  <div class="card">
    <form class="card-body stack" method="post" action="/login">
      @csrf

      <x-form-error />

      <div class="field">
        <label class="label" for="username">Benutzername</label>
        <input class="input @error('username') input-error @enderror" type="text" id="username" name="username"
               value="{{ old('username') }}" required autofocus
               autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false">
      </div>

      <div class="field">
        <label class="label" for="password">Passwort</label>
        <input class="input" type="password" id="password" name="password"
               required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">Anmelden</button>
    </form>
  </div>

  <p class="text-center text-sm text-muted">
    @if ($firstSetup)
      Noch kein Konto vorhanden.
      <a href="/register" class="font-medium text-brand hover:underline">Ersteinrichtung starten</a>
    @else
      Noch kein Konto?
      <a href="/register" class="font-medium text-brand hover:underline">Konto anlegen</a>
    @endif
  </p>
  <p class="text-center text-sm text-subtle">
    Passwort vergessen? Eine Administratorin oder ein Administrator kann es neu setzen.
  </p>
</main>
@endsection
