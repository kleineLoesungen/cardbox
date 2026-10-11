{{--
  Zeigt, was gerade gilt. Geaendert wird ueber Umgebungsvariablen — deshalb
  steht neben jedem Wert der Name der Variablen, die ihn setzt.
--}}
@extends('layouts.app')
@section('title', 'System')

@section('main')
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">System</h1>
  <p class="mt-1 max-w-2xl text-sm text-muted">
    Die wirksame Konfiguration. Geändert wird sie über Umgebungsvariablen
    (<code class="code">.env</code> bzw. <code class="code">compose.yaml</code>),
    danach den Server neu starten.
  </p>
</div>

{{-- --- Version ------------------------------------------------------------ --}}
<section class="card mb-6">
  <div class="card-header">
    <h2 class="card-title">Version</h2>
    @if ($update->enabled)
      <form method="post" action="/admin/system/update-check">
        @csrf
        <button type="submit" class="btn btn-ghost btn-sm">Jetzt prüfen</button>
      </form>
    @endif
  </div>

  <div class="card-body stack">
    @if (session('checked') === 'ok')
      <div class="alert alert-success" role="status">Geprüft.</div>
    @elseif (session('checked') === 'failed')
      <div class="alert alert-warning" role="status">
        Die Prüfung war nicht möglich. Der zuletzt bekannte Stand bleibt stehen.
      </div>
    @endif

    <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm">
      <dt class="text-muted">Installiert</dt>
      <dd class="min-w-0 font-medium">
        {{ $update->installedLabel() ?? '' }}
        @unless ($update->installedLabel())
          <span class="text-subtle">unbekannt</span>
        @endunless
      </dd>

      @if ($update->enabled)
        <dt class="text-muted">Neueste</dt>
        <dd class="min-w-0 font-medium">
          {{ $update->latestLabel() ?? '' }}
          @unless ($update->latestLabel())
            <span class="text-subtle">–</span>
          @endunless
        </dd>

        <dt class="text-muted">Zuletzt geprüft</dt>
        <dd class="min-w-0 text-muted">
          @if ($update->checkedAt)
            <x-datetime :value="$update->checkedAt" />
            @unless ($update->ok)
              <span class="text-warning"> · nicht erreichbar</span>
            @endunless
          @else
            <span class="text-subtle">noch nie</span>
          @endif
        </dd>
      @else
        <dt class="text-muted">Prüfung</dt>
        <dd class="min-w-0">
          abgeschaltet — kein <code class="code">UPDATE_CHECK_URL</code> gesetzt
        </dd>
      @endif
    </dl>

    @if ($update->updateAvailable())
      <div class="alert alert-info">
        <div>
          <p class="font-medium">Es gibt eine neuere Version</p>
          <p class="mt-1">Die Anwendung aktualisiert sich nicht selbst.</p>
        </div>
      </div>

      @foreach ($update->newer as $eintrag)
        <div>
          <p class="mb-2 text-sm font-semibold">{{ $eintrag->label() }}</p>
          <x-changelog-items :entry="$eintrag" />
        </div>
      @endforeach
    @endif

    @if ($changelog !== [])
      <div class="border-t border-border pt-4">
        <p class="mb-3 text-sm font-semibold">Änderungen dieser Installation</p>
        @foreach ($changelog as $eintrag)
          <p class="mt-3 text-sm font-medium">{{ $eintrag->label() }}</p>
          <x-changelog-items :entry="$eintrag" />
        @endforeach
      </div>
    @endif
  </div>
</section>

<div class="grid gap-6 lg:grid-cols-2">

  {{-- --- App und PWA ------------------------------------------------------ --}}
  <section class="card h-fit">
    <div class="card-header"><h2 class="card-title">App und Installation</h2></div>
    <div class="card-body">
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm">
        <dt class="text-muted">Name</dt>
        <dd class="min-w-0">{{ $branding->name }} <code class="code ml-1">APP_NAME</code></dd>

        <dt class="text-muted">Kurzname</dt>
        <dd class="min-w-0">{{ $branding->shortName }} <code class="code ml-1">APP_SHORT_NAME</code></dd>

        <dt class="text-muted">Leistenfarbe</dt>
        <dd class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
          <span class="size-4 rounded border border-border" style="background: {{ $branding->themeColor }}"></span>
          {{ $branding->themeColor }} <code class="code">PWA_THEME_COLOR</code>
        </dd>

        <dt class="text-muted">Startbildschirm</dt>
        <dd class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
          <span class="size-4 rounded border border-border" style="background: {{ $branding->backgroundColor }}"></span>
          {{ $branding->backgroundColor }} <code class="code">PWA_BACKGROUND_COLOR</code>
        </dd>

        <dt class="text-muted">Adresse</dt>
        <dd class="min-w-0 wrap-anywhere">{{ $appUrl }} <code class="code ml-1">APP_URL</code></dd>
      </dl>

      <div class="mt-5 row gap-3">
        <img src="/icons/icon-192.png" alt="App-Icon" class="size-12 rounded-xl">
        <p class="text-xs text-muted">
          Icons liegen unter <code class="code">public/icons/</code>.
          Quelle ändern in <code class="code">resources/icons/</code>, dann
          <code class="code">./scripts/icons.sh</code>.
        </p>
      </div>

      <p class="mt-4 text-xs text-subtle">
        <a href="/manifest.webmanifest" class="hover:text-content">Manifest ansehen</a>
      </p>
    </div>
  </section>

  {{-- --- E-Mail ----------------------------------------------------------- --}}
  <section class="card h-fit">
    <div class="card-header"><h2 class="card-title">E-Mail</h2></div>
    <div class="card-body stack">

      @if (session('mail_sent'))
        <div class="alert alert-success" role="status">Testmail wurde vom Mailserver angenommen.</div>
      @endif
      @if (session('mail_error'))
        <div class="alert alert-danger" role="alert">
          <div>
            <p class="font-medium">Versand fehlgeschlagen</p>
            <p class="mt-1 break-words font-mono text-xs">{{ session('mail_error') }}</p>
          </div>
        </div>
      @endif
      <x-form-error />

      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm">
        <dt class="text-muted">Versandweg</dt>
        <dd class="min-w-0">{{ $mail['mailer'] }} <code class="code ml-1">MAIL_MAILER</code></dd>

        @if ($mail['mailer'] === 'smtp')
          @if ($mail['uses_url'])
            <dt class="text-muted">Server</dt>
            <dd class="min-w-0">aus <code class="code">MAIL_URL</code></dd>
          @else
            <dt class="text-muted">Server</dt>
            <dd class="min-w-0 wrap-anywhere">
              {{ $mail['host'] }}:{{ $mail['port'] }}
              <code class="code ml-1">MAIL_HOST</code> <code class="code">MAIL_PORT</code>
            </dd>
          @endif

          @if ($mail['scheme'])
            <dt class="text-muted">Verschlüsselung</dt>
            <dd>{{ $mail['scheme'] }}</dd>
          @endif

          @if ($mail['username'])
            <dt class="text-muted">Anmeldung</dt>
            <dd class="min-w-0 wrap-anywhere">
              {{ $mail['username'] }}
              @if ($mail['has_password'])<span class="text-subtle">· Passwort gesetzt</span>@endif
            </dd>
          @endif
        @endif

        <dt class="text-muted">Absender</dt>
        <dd class="min-w-0 wrap-anywhere">{{ $mail['from'] }} <code class="code ml-1">MAIL_FROM_ADDRESS</code></dd>
      </dl>

      @if (in_array($mail['mailer'], ['log', 'array'], true))
        <div class="alert alert-warning">
          Mit <code class="code">MAIL_MAILER={{ $mail['mailer'] }}</code> wird nichts
          verschickt — Mails erscheinen nur im Server-Log.
        </div>
      @endif

      <form method="post" action="/admin/system/testmail" class="stack border-t border-border pt-4">
        @csrf
        <div class="field">
          <label class="label" for="to">Testmail senden an</label>
          <input class="input" type="email" id="to" name="to" value="{{ $testMailTo }}" required>
        </div>
        <button type="submit" class="btn btn-secondary">Testmail senden</button>
      </form>
    </div>
  </section>

  {{-- --- Sicherheit -------------------------------------------------------- --}}
  <section class="card h-fit">
    <div class="card-header"><h2 class="card-title">Sicherheit</h2></div>
    <div class="card-body">
      <dl class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-3 text-sm">
        <dt class="text-muted">Cookie nur über HTTPS</dt>
        <dd>
          <span class="badge {{ $secureCookie ? 'badge-success' : 'badge-warning' }}">{{ $secureCookie ? 'an' : 'aus' }}</span>
          <code class="code ml-1">SESSION_SECURE_COOKIE</code>
        </dd>

        <dt class="text-muted">Reverse-Proxy vertrauen</dt>
        <dd>
          <span class="badge badge-neutral">{{ $trustProxy ? 'an' : 'aus' }}</span>
          <code class="code ml-1">TRUST_PROXY</code>
        </dd>

        <dt class="text-muted">Fehlerdetails anzeigen</dt>
        <dd>
          <span class="badge {{ $debug ? 'badge-warning' : 'badge-success' }}">{{ $debug ? 'an' : 'aus' }}</span>
          <code class="code ml-1">APP_DEBUG</code>
        </dd>
      </dl>
      @if (! $secureCookie || $debug)
        <p class="mt-4 text-xs text-muted">
          In Produktion <code class="code">SESSION_SECURE_COOKIE=true</code> und
          <code class="code">APP_DEBUG=false</code> setzen. Lokal ist es so richtig.
        </p>
      @endif
    </div>
  </section>
</div>
@endsection
