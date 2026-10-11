@extends('layouts.app')
@section('title', 'Mein Konto')

@section('main')
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">Mein Konto</h1>
  <p class="mt-1 text-sm text-muted">
    Angemeldet als <span class="badge badge-brand">{{ $user->role->label() }}</span>
  </p>
</div>

<div class="grid gap-6 lg:grid-cols-2">

  {{-- --- Profil ----------------------------------------------------------- --}}
  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Profil</h2></div>
    <form class="card-body stack" method="post" action="/account">
      @csrf

      @if (session('saved') === 'profile')
        <div class="alert alert-success" role="status">Profil gespeichert.</div>
      @endif
      <x-form-error bag="profile" />

      <div class="field">
        <label class="label" for="name">Anzeigename</label>
        <input class="input" type="text" id="name" name="name"
               value="{{ old('name', $user->name) }}" required autocomplete="name">
      </div>

      <div class="field">
        <label class="label" for="email">E-Mail</label>
        <input class="input" type="email" id="email" name="email"
               value="{{ old('email', $user->email) }}" required autocomplete="email">
        <p class="hint">Wird auch zum Anmelden benutzt.</p>
      </div>

      <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
  </div>

  {{-- --- Passwort ---------------------------------------------------------- --}}
  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Passwort ändern</h2></div>
    <form class="card-body stack" method="post" action="/account/password">
      @csrf

      @if (session('saved') === 'password')
        <div class="alert alert-success" role="status">Passwort geändert.</div>
      @endif
      <x-form-error bag="password" />

      <div class="field">
        <label class="label" for="current_password">Aktuelles Passwort</label>
        <input class="input" type="password" id="current_password" name="current_password"
               required autocomplete="current-password">
      </div>

      <div class="field">
        <label class="label" for="new_password">Neues Passwort</label>
        <input class="input" type="password" id="new_password" name="new_password"
               required minlength="{{ \App\Services\UserService::MIN_PASSWORD_LENGTH }}" autocomplete="new-password">
        <p class="hint">Mindestens {{ \App\Services\UserService::MIN_PASSWORD_LENGTH }} Zeichen.</p>
      </div>

      <div class="field">
        <label class="label" for="new_password_repeat">Neues Passwort wiederholen</label>
        <input class="input" type="password" id="new_password_repeat" name="new_password_repeat"
               required minlength="{{ \App\Services\UserService::MIN_PASSWORD_LENGTH }}" autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-primary">Passwort ändern</button>
    </form>
  </div>
</div>
@endsection
