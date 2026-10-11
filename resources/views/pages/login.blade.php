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
        <label class="label" for="email">E-Mail</label>
        <input class="input @error('email') input-error @enderror" type="email" id="email" name="email"
               value="{{ old('email') }}" required autocomplete="email" autofocus>
      </div>

      <div class="field">
        <label class="label" for="password">Passwort</label>
        <input class="input" type="password" id="password" name="password"
               required autocomplete="current-password">
      </div>

      <button type="submit" class="btn btn-primary btn-block">Anmelden</button>
    </form>
  </div>

  @if ($firstSetup)
    <p class="text-center text-sm text-muted">
      Noch kein Konto vorhanden.
      <a href="/register" class="font-medium text-brand hover:underline">Ersteinrichtung starten</a>
    </p>
  @else
    <p class="text-center text-sm text-subtle">
      Zugang bekommst du von einer Administratorin oder einem Administrator.
    </p>
  @endif
</main>
@endsection
