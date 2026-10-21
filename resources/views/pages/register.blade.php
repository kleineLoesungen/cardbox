@extends('layouts.base')
@section('title', $firstSetup ? 'Ersteinrichtung' : 'Konto anlegen')

@section('body')
<main class="mx-auto flex min-h-dvh w-full max-w-sm flex-col justify-center gap-6 px-6 py-12">
  <div class="text-center">
    @if ($firstSetup)
      <h1 class="text-xl font-semibold tracking-tight">Ersteinrichtung</h1>
      <p class="mt-1 text-sm text-muted">
        Das erste Konto dieser Installation. Es erhält Administratorrechte und
        gibt danach neue Konten frei.
      </p>
    @else
      <h1 class="text-xl font-semibold tracking-tight">Konto anlegen</h1>
      <p class="mt-1 text-sm text-muted">
        Danach muss eine Administratorin oder ein Administrator dein Konto
        noch freigeben.
      </p>
    @endif
  </div>

  <div class="card">
    <form class="card-body stack" method="post" action="/register">
      @csrf

      <div class="field">
        <label class="label" for="name">Name</label>
        <input class="input @error('name') input-error @enderror" type="text" id="name" name="name"
               value="{{ old('name') }}" required autocomplete="name" autofocus>
        <x-field-error name="name" />
        <p class="hint">So sehen dich andere, z. B. „Lena M."</p>
      </div>

      <div class="field">
        <label class="label" for="username">Benutzername</label>
        <input class="input @error('username') input-error @enderror" type="text" id="username" name="username"
               value="{{ old('username') }}" required maxlength="{{ \App\Services\UserService::USERNAME_MAX_LENGTH }}"
               autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false">
        <x-field-error name="username" />
        <p class="hint">Damit meldest du dich an. Kleinbuchstaben, Ziffern und Punkt, z. B. lena.m</p>
      </div>

      @include('partials.password-fields', ['confirm' => true])

      <button type="submit" class="btn btn-primary btn-block btn-lg">Konto anlegen</button>
    </form>
  </div>

  <p class="text-center text-sm text-muted">
    Schon ein Konto? <a href="/login" class="link-action">Anmelden</a>
  </p>
</main>
@endsection
