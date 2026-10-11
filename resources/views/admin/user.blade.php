{{--
  Detailseite eines Benutzers. Alle Aenderungen laufen als normales Formular
  mit anschliessender Weiterleitung — dadurch loest ein Neuladen keine zweite
  Aenderung aus.
--}}
@extends('layouts.app')
@section('title', $edited->name)

@section('main')
<div class="mb-6">
  <a href="/admin/users" class="text-sm text-muted hover:text-content">← Alle Benutzer</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $edited->name }}</h1>
  <p class="mt-1 text-sm text-muted">
    {{ $edited->email }} · angelegt am <x-date :value="$edited->created_at" />
  </p>
</div>

@if (session('saved'))
  <div class="alert alert-success mb-6" role="status">Änderungen gespeichert.</div>
@endif

@if ($errors->any())
  <div class="mb-6"><x-form-error /></div>
@endif

@if ($isLastAdmin)
  <div class="alert alert-info mb-6">
    <div>
      <p class="font-medium">Letzter aktiver Administrator</p>
      <p class="mt-1">
        Solange es keinen zweiten gibt, lässt sich dieses Konto weder herabstufen
        noch sperren oder löschen — sonst käme niemand mehr an die Verwaltung.
      </p>
    </div>
  </div>
@endif

<div class="grid gap-6 lg:grid-cols-2">

  {{-- --- Stammdaten, Rolle, Status ---------------------------------------- --}}
  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Stammdaten</h2></div>
    <form class="card-body stack" method="post" action="/admin/users/{{ $edited->id }}">
      @csrf

      <div class="field">
        <label class="label" for="name">Anzeigename</label>
        <input class="input" type="text" id="name" name="name"
               value="{{ old('name', $edited->name) }}" required>
      </div>

      <div class="field">
        <label class="label" for="email">E-Mail</label>
        <input class="input" type="email" id="email" name="email"
               value="{{ old('email', $edited->email) }}" required>
      </div>

      <div class="field">
        <label class="label" for="role">Rolle</label>
        <select class="select" id="role" name="role" @disabled($isSelf)>
          @foreach (\App\Enums\Role::cases() as $role)
            <option value="{{ $role->value }}" @selected($edited->role === $role)>{{ $role->label() }}</option>
          @endforeach
        </select>
        @if ($isSelf)
          {{-- Gesperrte Felder senden nichts — Wert trotzdem mitschicken. --}}
          <input type="hidden" name="role" value="{{ $edited->role->value }}">
          <p class="hint">Die eigene Rolle lässt sich hier nicht ändern.</p>
        @endif
      </div>

      <label class="row gap-2 text-sm">
        <input type="checkbox" class="checkbox" name="is_active" value="1"
               @checked($edited->is_active) @disabled($isSelf)>
        Konto ist aktiv
      </label>
      @if ($isSelf)
        <input type="hidden" name="is_active" value="1">
        <p class="hint">Das eigene Konto lässt sich nicht sperren.</p>
      @endif

      <button type="submit" class="btn btn-primary">Speichern</button>
    </form>
  </div>

  <div class="stack">

    {{-- --- Passwort zuruecksetzen ----------------------------------------- --}}
    <div class="card">
      <div class="card-header"><h2 class="card-title">Passwort zurücksetzen</h2></div>
      <form class="card-body stack" method="post" action="/admin/users/{{ $edited->id }}/password">
        @csrf
        <div class="field">
          <label class="label" for="password">Neues Passwort</label>
          <input class="input" type="password" id="password" name="password"
                 required minlength="{{ \App\Services\UserService::MIN_PASSWORD_LENGTH }}" autocomplete="new-password">
          <p class="hint">
            Mindestens {{ \App\Services\UserService::MIN_PASSWORD_LENGTH }} Zeichen. Bestehende Anmeldungen
            dieses Kontos bleiben aktiv, bis sie ablaufen.
          </p>
        </div>
        <button type="submit" class="btn btn-secondary">Passwort setzen</button>
      </form>
    </div>

    {{-- --- Loeschen -------------------------------------------------------- --}}
    <div class="card">
      <div class="card-header"><h2 class="card-title">Konto löschen</h2></div>
      <div class="card-body stack">
        <p class="text-sm text-muted">
          Löscht das Konto und alle zugehörigen Daten unwiderruflich.
          Ausgestellte App-Tokens bleiben bestehen — sie gehören der Installation,
          nicht der Person.
        </p>
        @if ($isSelf)
          <p class="hint">Das eigene Konto lässt sich nicht löschen.</p>
        @else
          <form method="post" action="/admin/users/{{ $edited->id }}/delete"
                data-confirm="Konto „{{ $edited->name }}“ endgültig löschen?">
            @csrf
            <button type="submit" class="btn btn-danger">Konto löschen</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

<script>
  // Rueckfrage vor dem Loeschen. Der Text steht im data-Attribut und nicht in
  // einem onsubmit="…": Dort muesste er zusaetzlich fuer JavaScript maskiert
  // werden, und ein Apostroph im Namen braeche das Skript.
  document.querySelectorAll("form[data-confirm]").forEach((form) => {
    form.addEventListener("submit", (e) => {
      if (!confirm(form.dataset.confirm)) e.preventDefault();
    });
  });
</script>
@endsection
