{{--
  Detailseite eines Benutzers. Alle Aenderungen laufen als normales Formular
  mit anschliessender Weiterleitung — dadurch loest ein Neuladen keine zweite
  Aenderung aus.
--}}
@extends('layouts.app')
@section('title', $edited->name)

@section('main')
<div class="mb-6">
  <a href="/admin/users" class="back-link">← Alle Benutzer</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $edited->name }}</h1>
  <p class="mt-1 text-sm text-muted">
    {{ $edited->username }} · angelegt am <x-date :value="$edited->created_at" />
  </p>
  <p class="mt-1 text-sm text-muted">
    Zuletzt angemeldet:
    @if ($edited->last_login_at)<x-datetime :value="$edited->last_login_at" />@else unbekannt @endif
    {{-- Sessions last 30 days: active without a new sign-in. --}}
    · zuletzt aktiv:
    @if ($lastActivity)<x-datetime :value="$lastActivity" />@else – @endif
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

{{-- --- Age and parents (docs/specs/eltern-konten.md, B) ------------------ --}}
<section class="card mb-6" id="familie">
  <div class="card-header"><h2 class="card-title">Alter und Eltern</h2></div>
  <div class="card-body stack">
    <x-form-error bag="family" />
    <p>
      {{ $edited->name }} gilt als <strong>{{ $edited->is_adult ? 'ab 18' : 'unter 18' }}</strong>.
      {{ $edited->is_adult ? 'Darf teilen und Elternteil sein.' : 'Teilt nichts selbst; die Eltern bearbeiten und teilen die Stapel.' }}
    </p>

    @if ($edited->is_adult)
      <form method="post" action="/admin/users/{{ $edited->id }}/age" class="stack gap-2">
        @csrf
        <input type="hidden" name="age" value="minor">
        <label class="label" for="new-parents">Auf „unter 18“ stellen – Eltern</label>
        <input class="input" type="text" id="new-parents" name="parents" placeholder="Benutzernamen, durch Komma getrennt"
               autocomplete="off" autocapitalize="none" spellcheck="false" required>
        <button type="submit" class="btn btn-secondary">Auf „unter 18“ stellen</button>
      </form>
    @else
      <form method="post" action="/admin/users/{{ $edited->id }}/age"
            @if ($guardians->isNotEmpty()) data-confirm-action="Auf „ab 18“ stellen" data-confirm="{{ $guardians->pluck('name')->implode(' und ') }} können {{ $edited->name }}s Stapel danach nicht mehr bearbeiten." @endif>
        @csrf
        <input type="hidden" name="age" value="adult">
        <button type="submit" class="btn btn-secondary">Auf „ab 18“ stellen</button>
      </form>

      <h3 class="text-sm font-semibold">Eltern</h3>
      @php($confirmedGuardians = $guardians->filter(fn ($g) => (bool) $g->link_confirmed))
      @if ($confirmedGuardians->isEmpty())
        <p class="alert alert-warning text-sm">Noch kein Elternteil – bis dahin kann niemand die Stapel von {{ $edited->name }} teilen.</p>
      @endif
      @if ($guardians->isNotEmpty())
        <ul class="list">
          @foreach ($guardians as $guardian)
            <li class="list-row flex-wrap">
              <a href="/admin/users/{{ $guardian->id }}" class="min-w-0 flex-1 break-words font-medium">{{ $guardian->name }} <span class="font-normal text-muted">({{ $guardian->username }})</span></a>
              <span class="flex shrink-0 gap-2">
                @unless ($guardian->link_confirmed)
                  {{-- Linked by a code before eltern-konten.md: overview only until confirmed. --}}
                  <span class="badge badge-neutral self-center">nur zuschauen</span>
                  <form method="post" action="/admin/users/{{ $edited->id }}/guardians/{{ $guardian->id }}/confirm"
                        data-confirm-action="Bestätigen" data-confirm="{{ $guardian->name }} kann danach die Stapel von {{ $edited->name }} bearbeiten, löschen und teilen.">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Bestätigen</button>
                  </form>
                @endunless
                <form method="post" action="/admin/users/{{ $edited->id }}/guardians/{{ $guardian->id }}/delete"
                      data-confirm-action="Entfernen" data-confirm="{{ $guardian->name }} ist danach nicht mehr Elternteil von {{ $edited->name }}.">
                  @csrf
                  <button type="submit" class="btn btn-ghost btn-sm" @disabled($guardian->link_confirmed && $confirmedGuardians->count() === 1)>Entfernen</button>
                </form>
              </span>
            </li>
          @endforeach
        </ul>
        @if ($guardians->count() > $confirmedGuardians->count())
          <p class="text-sm text-muted">„Nur zuschauen“: früher vom Kind per Code verknüpft. Erst nach „Bestätigen“ darf diese Person die Stapel bearbeiten und teilen.</p>
        @endif
      @endif
      @if ($guardians->count() < \App\Services\GuardianService::MAX_GUARDIANS)
        <form method="post" action="/admin/users/{{ $edited->id }}/guardians" class="flex gap-2">
          @csrf
          <input class="input min-w-0 flex-1" type="text" name="username" placeholder="Benutzername eines Kontos ab 18"
                 aria-label="Elternteil hinzufügen" autocomplete="off" autocapitalize="none" spellcheck="false" required>
          <button type="submit" class="btn btn-secondary shrink-0">Hinzufügen</button>
        </form>
      @endif

      @if ($reviewPending->isNotEmpty() && $confirmedGuardians->isEmpty())
        {{-- Without parents the admin decides (eltern-konten.md, F). --}}
        <h3 class="text-sm font-semibold">Geteilte Stapel – bitte durchsehen</h3>
        <p class="text-sm text-muted">Achte auf Urheber- und Lizenzrechte.</p>
        <ul class="list">
          @foreach ($reviewPending as $pending)
            <li class="list-row flex-wrap">
              <span class="min-w-0 flex-1 break-words font-medium">{{ $pending->name }}</span>
              <span class="flex shrink-0 gap-2">
                <form method="post" action="/admin/users/{{ $edited->id }}/review/{{ $pending->id }}">
                  @csrf
                  <input type="hidden" name="keep" value="0">
                  <button type="submit" class="btn btn-ghost btn-sm">Privat machen</button>
                </form>
                <form method="post" action="/admin/users/{{ $edited->id }}/review/{{ $pending->id }}">
                  @csrf
                  <input type="hidden" name="keep" value="1">
                  <button type="submit" class="btn btn-secondary btn-sm">So lassen</button>
                </form>
              </span>
            </li>
          @endforeach
        </ul>
      @endif
    @endif

    @if ($children->isNotEmpty())
      <h3 class="text-sm font-semibold">Kinder</h3>
      <ul class="list">
        @foreach ($children as $child)
          <li><a href="/admin/users/{{ $child->id }}" class="list-row break-words font-medium">{{ $child->name }} <span class="font-normal text-muted">({{ $child->username }})</span></a></li>
        @endforeach
      </ul>
    @endif
  </div>
</section>

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
        <label class="label" for="username">Benutzername</label>
        <input class="input" type="text" id="username" name="username"
               value="{{ old('username', $edited->username) }}" required
               maxlength="{{ \App\Services\UserService::USERNAME_MAX_LENGTH }}"
               autocapitalize="none" autocorrect="off" spellcheck="false">
        <p class="hint">Zum Anmelden. Wer umbenannt wird, muss den neuen Namen erfahren.</p>
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
        @include('partials.password-fields')
        <p class="hint">Bestehende Anmeldungen dieses Kontos bleiben aktiv, bis sie ablaufen.</p>
        <button type="submit" class="btn btn-secondary">Passwort setzen</button>
      </form>
    </div>

    {{-- --- Hand decks to another account (issue #12) ----------------------- --}}
    <div class="card">
      <div class="card-header"><h2 class="card-title">Stapel ({{ $decks->count() }})</h2></div>
      @if ($decks->isEmpty())
        <div class="card-body"><p class="text-sm text-muted">Dieses Konto hat keine Stapel.</p></div>
      @else
        <form class="card-body stack" method="post" action="/admin/users/{{ $edited->id }}/decks/transfer"
              data-confirm-action="Übertragen" data-confirm="Die gewählten Stapel an das angegebene Konto übertragen?">
          @csrf
          <label class="row min-h-12 gap-2 text-sm font-medium">
            <input type="checkbox" class="checkbox" data-check-all="decks[]">
            Alle auswählen
          </label>
          <ul class="stack gap-2">
            @foreach ($decks as $deck)
              <li>
                <label class="row min-h-12 min-w-0 items-center gap-2 text-sm">
                  <input type="checkbox" class="checkbox" name="decks[]" value="{{ $deck->id }}"
                         @checked(in_array($deck->id, (array) old('decks', []), true))>
                  <span class="min-w-0">
                    <span class="block break-words font-medium">{{ $deck->name }}</span>
                    <span class="block text-muted">
                      {{ $deck->frontLabel() }} → {{ $deck->backLabel() }} ·
                      {{ $deck->is_shared ? 'geteilt' : 'privat' }} ·
                      {{ $deck->cards_count }} {{ $deck->cards_count === 1 ? 'Karte' : 'Karten' }}@if ($deck->is_shared) · {{ $deck->followers_count }} lernen mit @endif
                    </span>
                  </span>
                </label>
              </li>
            @endforeach
          </ul>
          <div class="field">
            <label class="label" for="to_username">An Konto (Benutzername)</label>
            <input class="input" type="text" id="to_username" name="to_username" value="{{ old('to_username') }}" required
                   autocapitalize="none" autocorrect="off" spellcheck="false">
            <p class="hint">Die neue Besitzerin kann die Stapel danach bearbeiten, teilen und löschen. Geteilte Stapel lernt {{ $edited->name }} danach weiter; private sieht {{ $edited->name }} nicht mehr.</p>
          </div>
          <button type="submit" class="btn btn-secondary">Übertragen</button>
        </form>
      @endif
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
                data-confirm-action="Konto löschen"
                data-confirm="Konto „{{ $edited->name }}“ endgültig löschen?{{ $deckSummary['decks'] > 0 ? ' Dabei werden '.$deckSummary['decks'].' Stapel gelöscht' : '' }}{{ $deckSummary['followers'] > 0 ? ', mit denen insgesamt '.$deckSummary['followers'].' Personen lernen' : '' }}{{ $deckSummary['decks'] > 0 ? '. Stapel lassen sich vorher übertragen.' : '' }}">
            @csrf
            <button type="submit" class="btn btn-danger">Konto löschen</button>
          </form>
        @endif
      </div>
    </div>
  </div>
</div>

@endsection
