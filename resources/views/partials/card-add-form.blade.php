{{--
  "Karte hinzufügen" inside the #add-card sheet. Answered by
  partials/card-created with a fresh copy of itself, so many cards can be
  typed in a row without the keyboard closing.

  @param \App\Models\Deck      $deck
  @param \App\Models\Card|null $saved  the card just saved, for the confirmation line
--}}
@php($saved ??= null)
@php($frontTag = \App\Enums\Language::tryFrom($deck->front_language)?->tag())
@php($backTag = \App\Enums\Language::tryFrom($deck->back_language)?->tag())
<form id="add-card-form" class="stack" hx-post="/decks/{{ $deck->id }}/cards" hx-target="this" hx-swap="outerHTML">
  @if ($saved)
    <p class="alert alert-success" role="status">
      Gespeichert: {{ $saved->frontText() }} – {{ $saved->backText() }}
    </p>
  @endif

  <div class="field">
    <label class="label" for="add-front">{{ $deck->frontLabel() }}</label>
    <div class="stack gap-2" id="add-front-group">
      <input class="input input-lg" type="text" id="add-front" name="front[]" required autofocus
             lang="{{ $frontTag }}" enterkeyhint="next" data-enter-next autocomplete="off"
             hx-get="/decks/{{ $deck->id }}/front-check" hx-trigger="input changed delay:400ms"
             hx-target="#front-hint" hx-swap="outerHTML">
    </div>
    <p id="front-hint" hidden></p>
    <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="add-front-group">+ weitere Bedeutung</button>
  </div>

  <div class="field">
    <label class="label" for="add-back">{{ $deck->backLabel() }}</label>
    <div class="stack gap-2" id="add-back-group">
      <input class="input input-lg" type="text" id="add-back" name="back[]" required
             lang="{{ $backTag }}" enterkeyhint="done" autocomplete="off">
    </div>
    <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="add-back-group">+ weitere Bedeutung</button>
  </div>

  @include('partials.card-hint-field', ['id' => 'add-hint', 'value' => null])

  <button type="submit" class="btn btn-primary btn-lg btn-block">Speichern &amp; nächste</button>
  <button type="submit" name="close" value="1" class="btn btn-secondary btn-block -mt-1">Speichern &amp; schließen</button>

  <div class="flex flex-wrap justify-center gap-x-4 gap-y-2 text-sm">
    <a href="/decks/{{ $deck->id }}/import" class="text-brand hover:underline">Liste einfügen</a>
    <button type="button" class="text-brand hover:underline" data-open-sheet="adopt-sheet">Aus anderen Stapeln übernehmen</button>
  </div>
</form>
