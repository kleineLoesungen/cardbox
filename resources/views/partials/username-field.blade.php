{{--
  Username field of the admin "create account" form. Re-rendered via htmx
  when the name field changes, to propose a username derived from the name
  (see UserController::suggestUsername).

  @param string $value
--}}
<div class="field" id="username-field">
  <label class="label" for="username">Benutzername</label>
  <input class="input @error('username') input-error @enderror" type="text" id="username" name="username"
         value="{{ $value }}" required maxlength="{{ \App\Services\UserService::USERNAME_MAX_LENGTH }}"
         autocapitalize="none" autocorrect="off" spellcheck="false">
  <x-field-error name="username" />
  <p class="hint">Zum Anmelden. Kleinbuchstaben, Ziffern, Punkt, Bindestrich, z. B. lena.m</p>
</div>
