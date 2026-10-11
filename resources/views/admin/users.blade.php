@extends('layouts.app')
@section('title', 'Benutzer')

@section('main')
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">Benutzer</h1>
  <p class="mt-1 text-sm text-muted">
    Konten anlegen und verwalten. Nur für Administratoren.
  </p>
</div>

<div class="grid gap-6 lg:grid-cols-[22rem_1fr]">

  {{-- --- Anlegen ---------------------------------------------------------- --}}
  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Benutzer anlegen</h2></div>
    <form class="card-body stack" method="post" action="/admin/users">
      @csrf

      <x-form-error />

      <div class="field">
        <label class="label" for="name">Anzeigename</label>
        <input class="input @error('name') input-error @enderror" type="text" id="name" name="name"
               value="{{ old('name') }}" required>
      </div>

      <div class="field">
        <label class="label" for="email">E-Mail</label>
        <input class="input @error('email') input-error @enderror" type="email" id="email" name="email"
               value="{{ old('email') }}" required>
      </div>

      <div class="field">
        <label class="label" for="password">Startpasswort</label>
        <input class="input @error('password') input-error @enderror" type="password" id="password" name="password"
               required minlength="{{ \App\Services\UserService::MIN_PASSWORD_LENGTH }}" autocomplete="new-password">
        <p class="hint">
          Mindestens {{ \App\Services\UserService::MIN_PASSWORD_LENGTH }} Zeichen. Der Benutzer kann es unter
          „Mein Konto" selbst ändern.
        </p>
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
             placeholder="Name oder E-Mail suchen" aria-label="Benutzer suchen">
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
            <tr><th>Name</th><th>E-Mail</th><th>Rolle</th><th>Status</th><th>Angelegt</th><th></th></tr>
          </thead>
          <tbody>
            @foreach ($users as $u)
              <tr>
                <td class="font-medium">
                  {{ $u->name }}
                  @if ($u->is(auth()->user()))
                    <span class="badge badge-neutral ml-1">du</span>
                  @endif
                </td>
                <td class="text-muted">{{ $u->email }}</td>
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
                <td class="text-right">
                  <a href="/admin/users/{{ $u->id }}" class="btn btn-ghost btn-sm">Bearbeiten</a>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
@endsection
