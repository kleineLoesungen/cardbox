@extends('layouts.app')
@section('title', 'Benutzer')

@section('main')
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">Benutzer</h1>
  <p class="mt-1 text-sm text-muted">
    Konten anlegen und verwalten. Nur für Administratoren.
  </p>
</div>

@if ($minorsWithoutGuardian > 0)
  {{-- After the switch to parent accounts (docs/specs/eltern-konten.md). --}}
  <div class="alert alert-warning mb-6" role="status" data-tour="admin-eltern-fehlen">
    {{ $minorsWithoutGuardian === 1 ? '1 Konto unter 18 hat' : $minorsWithoutGuardian.' Konten unter 18 haben' }} kein Elternteil.
    Erwachsene auf „ab 18“ stellen, für Kinder Eltern eintragen – bis dahin teilen sie nichts.
  </div>
@endif

{{-- Waiting registrations first: on a phone they would otherwise sit below the create form. --}}
@if ($pending->isNotEmpty())
  <section class="card mb-6" aria-labelledby="pending-title" data-tour="admin-freigabe">
    <div class="card-header">
      <h2 class="card-title" id="pending-title">Warten auf Freigabe ({{ $pending->count() }})</h2>
    </div>
    <ul class="divide-y divide-border">
      @foreach ($pending as $p)
        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
          <div class="min-w-0 flex-1">
            <p class="font-medium">{{ $p->name }}</p>
            <p class="text-sm text-muted break-all">{{ $p->username }}</p>
            <p class="text-xs text-subtle">registriert am <x-date :value="$p->created_at" /></p>
          </div>
          {{-- Age and, under 18, parents are part of the approval (eltern-konten.md, A). --}}
          <form method="post" action="/admin/users/{{ $p->id }}/approve" class="stack w-full gap-2">
            @csrf
            <x-form-error :bag="'approve-'.$p->id" />
            <div class="grid grid-cols-2 gap-2">
              <label class="choice-card min-h-12 items-center"><input type="radio" name="age" value="minor" class="mt-0" required> unter 18</label>
              <label class="choice-card min-h-12 items-center"><input type="radio" name="age" value="adult" class="mt-0" required> ab 18</label>
            </div>
            <input class="input" type="text" name="parents" placeholder="Eltern (Benutzernamen), nur bei unter 18"
                   aria-label="Eltern für {{ $p->name }}" autocomplete="off" autocapitalize="none" spellcheck="false">
            <button type="submit" class="btn btn-primary btn-sm">Freigeben</button>
          </form>
          <form method="post" action="/admin/users/{{ $p->id }}/reject"
                data-confirm-action="Ablehnen"
                data-confirm="Registrierung von „{{ $p->name }}“ ablehnen? Das Konto wird gelöscht.">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">Ablehnen</button>
          </form>
        </li>
      @endforeach
    </ul>
    <p class="card-footer text-xs text-muted">
      Nicht freigegebene Konten werden nach {{ \App\Services\UserService::PENDING_RETENTION_DAYS }} Tagen automatisch gelöscht.
    </p>
  </section>
@endif

<div class="grid gap-6 lg:grid-cols-[22rem_1fr]">

  {{-- --- Anlegen ---------------------------------------------------------- --}}
  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Benutzer anlegen</h2></div>
    <form class="card-body stack" method="post" action="/admin/users">
      @csrf

      <x-form-error />

      <div class="field">
        <label class="label" for="name">Anzeigename</label>
        {{-- Proposes a username from the name as soon as the field is left. --}}
        <input class="input @error('name') input-error @enderror" type="text" id="name" name="name"
               value="{{ old('name') }}" required
               hx-get="/admin/users/suggest-username" hx-trigger="change"
               hx-include="#name, #username" hx-target="#username-field" hx-swap="outerHTML">
      </div>

      @include('partials.username-field', ['value' => old('username', '')])

      @include('partials.password-fields')

      <fieldset class="field">
        <legend class="label">Alter</legend>
        <div class="grid grid-cols-2 gap-2">
          <label class="choice-card min-h-12 items-center"><input type="radio" name="age" value="minor" class="mt-0" @checked(old('age') === 'minor') required> unter 18</label>
          <label class="choice-card min-h-12 items-center"><input type="radio" name="age" value="adult" class="mt-0" @checked(old('age') === 'adult') required> ab 18</label>
        </div>
        <x-field-error name="age" />
      </fieldset>

      <div class="field">
        <label class="label" for="parents">Eltern</label>
        <input class="input @error('parents') input-error @enderror" type="text" id="parents" name="parents" value="{{ old('parents') }}"
               autocomplete="off" autocapitalize="none" spellcheck="false" placeholder="z. B. anna, ben">
        <p class="hint">Nur bei unter 18: Benutzernamen von Konten ab 18, durch Komma getrennt, höchstens {{ \App\Services\GuardianService::MAX_GUARDIANS }}. Eltern können die Stapel des Kindes bearbeiten und teilen.</p>
        <x-field-error name="parents" />
      </div>

      <div class="field">
        <label class="label" for="role">Rolle</label>
        <select class="select" id="role" name="role">
          @foreach (\App\Enums\Role::cases() as $role)
            <option value="{{ $role->value }}" @selected(old('role', 'user') === $role->value)>{{ $role->label() }}</option>
          @endforeach
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Anlegen</button>
    </form>
  </div>

  {{-- --- Liste ------------------------------------------------------------ --}}
  <div class="min-w-0">


    {{--
      Einfaches GET-Formular: Der Suchbegriff landet in der Adresszeile und
      laesst sich damit als Lesezeichen speichern und weitergeben.
    --}}
    <form method="get" action="/admin/users" class="mb-4 flex gap-2">
      <input class="input" type="search" name="q" value="{{ $search }}"
             placeholder="Name oder Benutzername suchen" aria-label="Benutzer suchen">
      <button type="submit" class="btn btn-secondary shrink-0">Suchen</button>
      @if ($search !== '')
        <a href="/admin/users" class="btn btn-ghost shrink-0">Zurücksetzen</a>
      @endif
    </form>

    @if ($search !== '')
      <p class="mb-3 text-sm text-muted">
        {{ $users->count() }} von {{ $total }} Konten passen zu „{{ $search }}“.
      </p>
    @endif

    @if ($users->isEmpty())
      <div class="empty">
        <p class="empty-title">Kein Treffer</p>
        <p class="empty-hint">Für „{{ $search }}“ gibt es kein Konto.</p>
      </div>
    @else
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th>Name</th><th>Benutzername</th><th>Rolle</th><th>Status</th><th>Angelegt</th><th>Letzte Anmeldung</th><th></th></tr>
          </thead>
          <tbody>
            @foreach ($users as $u)
              <tr>
                <td class="font-medium">
                  {{ $u->name }}
                  @if ($u->is(auth()->user()))
                    <span class="badge badge-neutral ml-1">du</span>
                  @endif
                  @if (! $u->is_adult)
                    <span class="badge badge-neutral ml-1">unter 18</span>
                    @if ($u->guardians_count === 0)
                      <span class="badge badge-warning ml-1">Elternteil fehlt</span>
                    @endif
                  @endif
                </td>
                <td class="text-muted">{{ $u->username }}</td>
                <td>
                  <span class="badge {{ $u->isAdmin() ? 'badge-brand' : 'badge-neutral' }}">{{ $u->role->label() }}</span>
                </td>
                <td>
                  @if ($u->is_active)
                    <span class="badge badge-success">aktiv</span>
                  @else
                    <span class="badge badge-danger">gesperrt</span>
                  @endif
                </td>
                <td class="text-muted"><x-date :value="$u->created_at" /></td>
                <td class="text-muted">
                  @if ($u->last_login_at)<x-datetime :value="$u->last_login_at" />@else unbekannt @endif
                </td>
                <td class="text-right">
                  <a href="/admin/users/{{ $u->id }}" class="btn btn-ghost btn-sm" @if ($loop->first) data-tour="admin-konto" @endif>Bearbeiten</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
<x-tour :pages="['admin']" />
@endsection
