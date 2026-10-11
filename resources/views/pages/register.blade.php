@extends('layouts.base')
@section('title', 'Ersteinrichtung')

@section('body')
<main class="mx-auto flex min-h-dvh w-full max-w-sm flex-col justify-center gap-6 px-6 py-12">
  <div class="text-center">
    <h1 class="text-xl font-semibold tracking-tight">Ersteinrichtung</h1>
    <p class="mt-1 text-sm text-muted">
      Das erste Konto dieser Installation. Es erhält Administratorrechte und
      legt danach alle weiteren Benutzer an.
    </p>
  </div>

  <div class="card">
    <form class="card-body stack" method="post" action="/register">
      @csrf

      <x-form-error />

      <div class="field">
        <label class="label" for="name">Anzeigename</label>
        <input class="input @error('name') input-error @enderror" type="text" id="name" name="name"
               value="{{ old('name') }}" required autocomplete="name" autofocus>
      </div>

      <div class="field">
        <label class="label" for="email">E-Mail</label>
        <input class="input @error('email') input-error @enderror" type="email" id="email" name="email"
               value="{{ old('email') }}" required autocomplete="email">
      </div>

      <div class="field">
        <label class="label" for="password">Passwort</label>
        <input class="input @error('password') input-error @enderror" type="password" id="password" name="password"
               required autocomplete="new-password" minlength="{{ \App\Services\UserService::MIN_PASSWORD_LENGTH }}">
        <p class="hint">Mindestens {{ \App\Services\UserService::MIN_PASSWORD_LENGTH }} Zeichen.</p>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Konto anlegen</button>
    </form>
  </div>

  <p class="text-center text-sm text-muted">
    Schon eingerichtet? <a href="/login" class="font-medium text-brand hover:underline">Anmelden</a>
  </p>
</main>
@endsection
