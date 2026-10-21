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

  {{-- --- Learning plan (moved here from the menu) -------------------------- --}}
  <a href="/learn/settings" class="card h-fit block hover:border-border-strong">
    <div class="card-body flex items-center justify-between gap-3">
      <span class="min-w-0">
        <span class="block font-semibold">Lernplan</span>
        <span class="block text-sm text-muted">
          @if ($plan['preset'])
            {{ $plan['preset']->label() }} – {{ $plan['preset']->description() }}
          @else
            Eigener Plan mit {{ $plan['box_count'] }} Fächern
          @endif
          · Tagesziel {{ $plan['daily_goal'] }} Karten
        </span>
      </span>
      <span class="shrink-0 font-medium text-brand">Ändern ›</span>
    </div>
  </a>

  {{-- --- Cards per round (docs/specs/runden-und-knifflige-woerter.md, A) -- --}}
  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Karten je Runde</h2></div>
    <form class="card-body stack" method="post" action="/account/round-size">
      @csrf
      <x-form-error bag="round" />
      <fieldset class="field">
        <legend class="sr-only">Karten je Runde</legend>
        <div class="grid grid-cols-3 gap-2">
          @foreach (config('learning.round_sizes') as $size)
            <label class="choice-card min-h-12 items-center justify-center">
              <input type="radio" name="round_size" value="{{ $size }}" @checked($roundSize === (int) $size) class="mt-0">
              <span class="font-semibold">{{ $size }}</span>
            </label>
          @endforeach
        </div>
        <p class="hint">Kürzere Runden sind schneller geschafft. Danach geht es mit der nächsten weiter.</p>
      </fieldset>
      <button type="submit" class="btn btn-secondary">Speichern</button>
    </form>
  </div>

  {{-- --- Daily reminder (docs/specs/schreiben-und-erinnern.md, B) --------- --}}
  @if ($reminder)
    <div class="card h-fit" data-reminder data-public-key="{{ $reminder['publicKey'] }}">
      <div class="card-header"><h2 class="card-title">Erinnerung</h2></div>
      <div class="card-body stack">
        <x-form-error bag="reminder" />
        @if ($reminder['time'] !== null && $reminder['devices'] > 0)
          <form method="post" action="/account/reminder" class="stack">
            @csrf
            <div class="field">
              <label class="label" for="reminder_time">Jeden Tag um</label>
              <select class="select" id="reminder_time" name="reminder_time">
                @foreach ($reminder['times'] as $time)
                  <option value="{{ $time }}" @selected($time === $reminder['time'])>{{ $time }} Uhr</option>
                @endforeach
              </select>
              <p class="hint">
                Nur wenn noch Karten warten und dein Tagesziel offen ist.
                {{ $reminder['devices'] === 1 ? 'Ein Gerät' : $reminder['devices'].' Geräte' }} eingerichtet.
              </p>
            </div>
            <button type="submit" class="btn btn-primary">Speichern</button>
          </form>
          <form method="post" action="/account/reminder">
            @csrf
            <input type="hidden" name="off" value="1">
            <button type="submit" class="btn btn-ghost btn-block">Ausschalten</button>
          </form>
          <button type="button" class="btn btn-ghost btn-sm self-start" data-reminder-enable>Auch dieses Gerät erinnern</button>
        @else
          <p class="text-sm text-muted">Dein Handy erinnert dich jeden Tag ans Lernen – nur, wenn noch Karten warten.</p>
          <button type="button" class="btn btn-primary" data-reminder-enable>Erinnerung einschalten</button>
        @endif
        <p class="hint" data-reminder-message hidden></p>
        {{-- For the script below: the CSRF token of this session. --}}
        <form hidden>@csrf</form>
      </div>
    </div>
  @endif

  {{-- --- Parents see the progress: the child's side (docs/specs/eltern-ansicht.md, A) --}}
  <div class="card h-fit scroll-mt-20" id="eltern">
    <div class="card-header"><h2 class="card-title">Eltern</h2></div>
    <div class="card-body stack">
      <p class="text-sm text-muted">
        Deine Eltern können sehen, an welchen Tagen du lernst, wie viele Wörter
        du kannst, welche Stapel du lernst und welche Wörter knifflig sind.
        Deine einzelnen Antworten sehen sie nicht.
      </p>

      @if ($guardianCode)
        <div class="rounded-ui border border-border bg-surface-sunken p-4 text-center" role="status">
          <p class="text-sm text-muted">Code für deine Eltern</p>
          <p class="mt-1 font-mono text-3xl font-semibold tracking-widest select-all">{{ $guardianCode }}</p>
          <p class="mt-1 text-sm text-muted">Gilt 24 Stunden und nur einmal.</p>
        </div>
      @elseif ($codeExpiry)
        <p class="hint">Ein Code ist noch gültig bis <x-datetime :value="$codeExpiry" />. Ein neuer ersetzt ihn.</p>
      @endif

      @if ($guardians->count() < \App\Services\GuardianService::MAX_GUARDIANS)
        <form method="post" action="/account/guardian-code">
          @csrf
          @php($hasCode = $guardianCode || $codeExpiry)
          <button type="submit" @class(['btn btn-block', 'btn-secondary' => $hasCode || $guardians->isNotEmpty(), 'btn-primary' => ! $hasCode && $guardians->isEmpty()])>
            {{ $hasCode ? 'Neuen Code erzeugen' : 'Code für Eltern erzeugen' }}
          </button>
        </form>
      @endif

      @if ($guardians->isNotEmpty())
        <ul class="list">
          @foreach ($guardians as $guardian)
            <li class="list-row">
              <span class="min-w-0 flex-1"><strong>{{ $guardian->name }}</strong> sieht deinen Fortschritt</span>
              <form method="post" action="/account/guardians/{{ $guardian->id }}/delete" class="shrink-0"
                    data-confirm-action="Nicht mehr zeigen"
                    data-confirm="{{ $guardian->name }} soll deinen Fortschritt nicht mehr sehen?">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Nicht mehr zeigen</button>
              </form>
            </li>
          @endforeach
        </ul>
      @endif
    </div>
  </div>

  {{-- --- Parents see the progress: the guardian's side ------------------- --}}
  <div class="card h-fit scroll-mt-20" id="kinder">
    <div class="card-header"><h2 class="card-title">Kind hinzufügen</h2></div>
    <div class="card-body stack">
      @if ($children->isNotEmpty())
        <div>
          <h3 class="mb-2 text-sm font-semibold">Meine Kinder</h3>
          <ul class="list">
            @foreach ($children as $child)
              <li class="list-row">
                <a href="/children/{{ $child->id }}" class="min-w-0 flex-1 truncate py-3.5 font-medium text-brand hover:underline">{{ $child->name }}</a>
                <form method="post" action="/account/children/{{ $child->id }}/delete" class="shrink-0"
                      data-confirm-action="Entfernen"
                      data-confirm="{{ $child->name }} aus deiner Liste entfernen? Du siehst den Fortschritt dann nicht mehr.">
                  @csrf
                  <button type="submit" class="btn btn-ghost btn-sm">Entfernen</button>
                </form>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

      @if ($children->count() < \App\Services\GuardianService::MAX_CHILDREN)
        <form method="post" action="/account/children" class="stack">
          @csrf
          <x-form-error bag="children" />
          <div class="field">
            <label class="label" for="guardian_code">Code vom Kind</label>
            <input class="input font-mono uppercase tracking-widest" type="text" id="guardian_code" name="code"
                   value="{{ old('code') }}" required maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false"
                   placeholder="XXXX-XXXX">
            <p class="hint">Das Kind erzeugt den Code in seinem Konto unter „Eltern“.</p>
          </div>
          <button type="submit" class="btn btn-secondary">Hinzufügen</button>
        </form>
      @endif
    </div>
  </div>

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
        <span class="label">Benutzername</span>
        <p class="font-medium">{{ $user->username }}</p>
        <p class="hint">Damit meldest du dich an. Ändern kann ihn eine Administratorin oder ein Administrator.</p>
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

@if ($reminder)
@push('scripts')
<script>
  // "Erinnerung einschalten": ask for permission, subscribe this browser to
  // push messages and hand the subscription to the server. Hints instead of
  // a dead button where the browser cannot do it.
  (() => {
    const box = document.querySelector("[data-reminder]");
    if (!box) return;
    const message = box.querySelector("[data-reminder-message]");
    const say = (text) => { message.textContent = text; message.hidden = false; };
    const supported = "serviceWorker" in navigator && "PushManager" in window && "Notification" in window;
    // iPads report themselves as "Macintosh"; touch points give them away.
    const iOS = /iP(hone|ad|od)/.test(navigator.userAgent) || (/Macintosh/.test(navigator.userAgent) && navigator.maxTouchPoints > 1);
    const standalone = window.matchMedia("(display-mode: standalone)").matches || navigator.standalone === true;

    box.querySelectorAll("[data-reminder-enable]").forEach((button) => {
      if (!supported) {
        button.hidden = true;
        if (iOS && !standalone) {
          message.innerHTML = "";
          message.append("Auf dem iPhone und iPad geht das nur, wenn Cardbox auf dem Home-Bildschirm liegt. ");
          const link = Object.assign(document.createElement("a"), { href: "/help#app", textContent: "So geht's", className: "text-brand hover:underline" });
          message.append(link);
          message.hidden = false;
        } else {
          say("Dieser Browser kann keine Mitteilungen anzeigen.");
        }
        return;
      }
      if (Notification.permission === "denied") {
        say("Mitteilungen sind für diese Seite blockiert – in den Einstellungen des Browsers erlauben.");
      }
      button.addEventListener("click", async () => {
        button.disabled = true;
        try {
          if (await Notification.requestPermission() !== "granted") {
            say("Mitteilungen sind für diese Seite blockiert – in den Einstellungen des Browsers erlauben.");
            return;
          }
          const key = box.dataset.publicKey.replace(/-/g, "+").replace(/_/g, "/");
          const raw = atob(key + "=".repeat((4 - key.length % 4) % 4));
          const registration = await navigator.serviceWorker.ready;
          // After a key change the browser still holds the old subscription; drop it first.
          const old = await registration.pushManager.getSubscription();
          const oldKey = old?.options?.applicationServerKey;
          if (old && oldKey && btoa(String.fromCharCode(...new Uint8Array(oldKey))) !== btoa(raw)) {
            await old.unsubscribe();
          }
          const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: Uint8Array.from(raw, (c) => c.charCodeAt(0)),
          });
          const encodings = PushManager.supportedContentEncodings || ["aesgcm"];
          const response = await fetch("/account/push-subscriptions", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "Accept": "application/json",
              "X-CSRF-TOKEN": box.querySelector("input[name=_token]").value,
            },
            body: JSON.stringify({
              ...subscription.toJSON(),
              contentEncoding: encodings.includes("aes128gcm") ? "aes128gcm" : "aesgcm",
            }),
          });
          if (!response.ok) throw new Error(String(response.status));
          location.reload();
        } catch (_) {
          say("Das hat nicht geklappt. Bitte später noch einmal versuchen.");
        } finally {
          button.disabled = false;
        }
      });
    });
  })();
</script>
@endpush
@endif
@endsection
