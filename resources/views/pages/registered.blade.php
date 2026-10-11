@extends('layouts.base')
@section('title', 'Konto angelegt')

@section('body')
<main class="mx-auto flex min-h-dvh w-full max-w-sm flex-col justify-center gap-6 px-6 py-12">
  <div class="card">
    <div class="card-body stack text-center">
      <h1 class="text-xl font-semibold tracking-tight">Fertig!</h1>
      <p class="text-muted">
        Sobald eine Administratorin oder ein Administrator dein Konto freigibt,
        kannst du dich anmelden.
      </p>
      <div class="rounded-ui bg-surface-sunken px-4 py-3">
        <p class="text-sm text-muted">Merk dir deinen Benutzernamen:</p>
        <p class="mt-1 text-lg font-semibold break-all">{{ $username }}</p>
      </div>
      <a href="/login" class="btn btn-primary btn-block btn-lg">Zur Anmeldung</a>
    </div>
  </div>
</main>
@endsection
