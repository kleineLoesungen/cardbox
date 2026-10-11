{{--
  Password field(s) with a "suggest password" button.

  Used on /register (with repeat field) and in the admin forms. The button
  replaces this whole block with a fresh copy that carries a memorable
  suggestion; the field then switches to plain text so it can be read and
  passed on.

  @param string|null $value    Suggested password, null for an empty field
  @param bool        $confirm  Also render "Passwort wiederholen"
--}}
@php($value ??= null)
@php($confirm ??= false)
@php($min = \App\Services\UserService::MIN_PASSWORD_LENGTH)

<div class="password-fields stack">
  <div class="field">
    <label class="label" for="password">Passwort</label>
    <input class="input @error('password') input-error @enderror" type="{{ $value ? 'text' : 'password' }}"
           id="password" name="password" value="{{ $value }}" required minlength="{{ $min }}"
           autocomplete="new-password" autocapitalize="none" autocorrect="off" spellcheck="false">
    <x-field-error name="password" />
  </div>

  @if ($confirm)
    <div class="field">
      <label class="label" for="password_confirmation">Passwort wiederholen</label>
      <input class="input" type="{{ $value ? 'text' : 'password' }}"
             id="password_confirmation" name="password_confirmation" value="{{ $value }}" required
             autocomplete="new-password" autocapitalize="none" autocorrect="off" spellcheck="false">
    </div>
  @endif

  @if ($value)
    <p class="hint">Gut merken oder aufschreiben – es wird nur jetzt angezeigt.</p>
  @else
    <p class="hint">Mindestens {{ $min }} Zeichen. Tipp: drei Wörter und eine Zahl.</p>
  @endif

  <button type="button" class="btn btn-secondary btn-sm self-start"
          hx-post="/password-suggestion" hx-vals='{"confirm": "{{ $confirm ? '1' : '0' }}"}'
          hx-target="closest .password-fields" hx-swap="outerHTML">
    Passwort vorschlagen
  </button>
</div>
