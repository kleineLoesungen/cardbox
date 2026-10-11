{{--
  Create / edit a deck. Plain form with redirect (Post/Redirect/Get).

  @param \App\Models\Deck|null $deck
  @param string $action
  @param string $submit
--}}
@php($deck ??= null)
@php($languages = \App\Enums\Language::cases())
<form method="post" action="{{ $action }}" class="stack">
  @csrf

  <div class="field">
    <label class="label" for="{{ $deck ? 'edit' : 'new' }}-name">Name</label>
    <input class="input input-lg" type="text" id="{{ $deck ? 'edit' : 'new' }}-name" name="name" required maxlength="100"
           value="{{ old('name', $deck?->name) }}" placeholder="z. B. Unit 3 – Tiere" autocomplete="off"
           @unless ($deck) autofocus @endunless>
    <x-field-error name="name" />
  </div>

  <div class="grid grid-cols-2 gap-3">
    <div class="field min-w-0">
      <label class="label" for="{{ $deck ? 'edit' : 'new' }}-front">Vorne</label>
      <select class="select" id="{{ $deck ? 'edit' : 'new' }}-front" name="front_language">
        @foreach ($languages as $language)
          <option value="{{ $language->value }}" @selected(old('front_language', $deck?->front_language ?? 'de') === $language->value)>{{ $language->label() }}</option>
        @endforeach
      </select>
    </div>
    <div class="field min-w-0">
      <label class="label" for="{{ $deck ? 'edit' : 'new' }}-back">Hinten</label>
      <select class="select" id="{{ $deck ? 'edit' : 'new' }}-back" name="back_language">
        @foreach ($languages as $language)
          <option value="{{ $language->value }}" @selected(old('back_language', $deck?->back_language ?? 'en') === $language->value)>{{ $language->label() }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <x-field-error name="front_language" />
  <x-field-error name="back_language" />

  <details @if ($deck?->description || old('description')) open @endif>
    <summary class="cursor-pointer text-sm text-brand">Mehr Angaben</summary>
    <div class="field mt-3">
      <label class="label" for="{{ $deck ? 'edit' : 'new' }}-description">Beschreibung</label>
      <textarea class="textarea" id="{{ $deck ? 'edit' : 'new' }}-description" name="description" maxlength="500"
                placeholder="Wofür ist der Stapel? (freiwillig)">{{ old('description', $deck?->description) }}</textarea>
      <x-field-error name="description" />
    </div>
  </details>

  @if ($deck)
    <p class="hint">Sprachen ändern lässt Karten und Lernstand unverändert.</p>
  @else
    <p class="hint">Neue Stapel sind privat. Teilen kannst du später.</p>
  @endif

  <button type="submit" class="btn btn-primary btn-lg btn-block">{{ $submit }}</button>
</form>
