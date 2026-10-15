{{--
  "Fehler melden" inside #suggest-sheet: what it is about, optionally new
  text for each side (prefilled with the card), optionally a comment.
  Answered by partials/suggestion-sent, which replaces the card row.

  @param \App\Models\Deck $deck
  @param \App\Models\Card $card
  @param \App\Models\CardSuggestion|null $open  the viewer's open suggestion, if any
  @param bool $fromDictionary  opened in the dictionary (issue #12): no card
                               row to replace, the answer is only a toast
--}}
@php($fromDictionary ??= false)
@php($frontTag = \App\Enums\Language::tryFrom($deck->front_language)?->tag())
@php($backTag = \App\Enums\Language::tryFrom($deck->back_language)?->tag())
@if ($open)
  <p class="text-sm">Du hast schon einen Vorschlag zu „{{ $card->frontText() }}“ geschickt. {{ $deck->user->name }} hat noch nicht entschieden.</p>
@else
  <form class="stack" hx-post="/decks/{{ $deck->id }}/cards/{{ $card->id }}/suggest"
        @if ($fromDictionary) hx-swap="none" @else hx-target="#card-{{ $card->id }}" hx-swap="outerHTML" @endif>
    @if ($fromDictionary)
      <input type="hidden" name="context" value="dictionary">
    @endif
    <p class="text-sm text-muted">
      Dein Vorschlag geht an {{ $deck->user->name }}. Nur {{ $deck->user->name }} kann die Karte ändern.
    </p>

    <fieldset class="field">
      <legend class="label mb-1.5">Worum geht es?</legend>
      <div class="grid grid-cols-2 gap-2">
        @foreach (\App\Enums\SuggestionType::cases() as $type)
          <label class="choice-card min-h-12 items-center">
            <input type="radio" name="type" value="{{ $type->value }}" @checked($loop->first) class="mt-0">
            <span class="min-w-0 text-sm font-medium">{{ $type->label() }}</span>
          </label>
        @endforeach
      </div>
    </fieldset>

    <div class="field">
      <span class="label">{{ $deck->frontLabel() }} <span class="font-normal text-muted">(so wäre es richtig)</span></span>
      <div class="stack gap-2" id="suggest-front-group">
        @foreach ($card->front as $i => $meaning)
          <input class="input" type="text" name="front[]" value="{{ $meaning }}" lang="{{ $frontTag }}"
                 aria-label="{{ $deck->frontLabel() }} {{ $i + 1 }}" autocomplete="off">
        @endforeach
      </div>
      <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="suggest-front-group">+ weitere Bedeutung</button>
    </div>

    <div class="field">
      <span class="label">{{ $deck->backLabel() }} <span class="font-normal text-muted">(so wäre es richtig)</span></span>
      <div class="stack gap-2" id="suggest-back-group">
        @foreach ($card->back as $i => $meaning)
          <input class="input" type="text" name="back[]" value="{{ $meaning }}" lang="{{ $backTag }}"
                 aria-label="{{ $deck->backLabel() }} {{ $i + 1 }}" autocomplete="off">
        @endforeach
      </div>
      <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="suggest-back-group">+ weitere Bedeutung</button>
    </div>

    <div class="field">
      <label class="label" for="suggest-comment">Kommentar <span class="font-normal text-muted">(freiwillig)</span></label>
      <textarea class="textarea" id="suggest-comment" name="comment" rows="2"
                maxlength="{{ config('learning.max_suggestion_comment') }}"></textarea>
      <p class="hint">Ändere oben den Text oder schreib kurz, was nicht stimmt.</p>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">Vorschlag schicken</button>
  </form>
@endif
