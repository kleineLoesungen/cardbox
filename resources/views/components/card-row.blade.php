{{--
  One card in a deck's list: main meanings front (with the hint) | back,
  further meanings as "+n".

  Own deck: tapping opens the edit sheet (#card-sheet). Other people's decks:
  read only; tapping unfolds all meanings and, in shared decks, "Fehler
  melden" (#suggest-sheet) or the status of the viewer's own suggestion.
  For moderation also "Bearbeiten (Moderation)" (#card-sheet); showing it
  is display only, the role:moderator routes decide.
--}}
@props(['card', 'deck', 'editable' => false, 'canSuggest' => false, 'canModerate' => false, 'suggestion' => null])
@php($more = count($card->front) + count($card->back) - 2)
<li id="card-{{ $card->id }}">
  @if ($editable)
    <button type="button" class="list-row"
            hx-get="/decks/{{ $deck->id }}/cards/{{ $card->id }}/edit"
            hx-target="#card-sheet-body"
            aria-label="Karte {{ $card->frontText() }} bearbeiten">
      <span class="card-row-sides">
        <span lang="{{ \App\Enums\Language::tryFrom($deck->front_language)?->tag() }}">{{ $card->frontText() }}@if ($card->hint) <span class="text-xs text-subtle">{{ $card->hint }}</span>@endif</span>
        <span class="text-muted" lang="{{ \App\Enums\Language::tryFrom($deck->back_language)?->tag() }}">
          {{ $card->backText() }}
          @if ($more > 0)<span class="badge badge-neutral ml-1">+{{ $more }}</span>@endif
        </span>
      </span>
    </button>
  @else
    <details class="group">
      <summary class="list-row cursor-pointer list-none">
        <span class="card-row-sides">
          <span lang="{{ \App\Enums\Language::tryFrom($deck->front_language)?->tag() }}">{{ $card->frontText() }}@if ($card->hint) <span class="text-xs text-subtle">{{ $card->hint }}</span>@endif</span>
          <span class="text-muted" lang="{{ \App\Enums\Language::tryFrom($deck->back_language)?->tag() }}">
            {{ $card->backText() }}
            @if ($more > 0)<span class="badge badge-neutral ml-1">+{{ $more }}</span>@endif
          </span>
        </span>
      </summary>
      @if ($more > 0)
        <div class="card-row-sides px-4 pb-3 text-sm">
          <span>{{ implode(', ', $card->front) }}</span>
          <span class="text-muted">{{ implode(', ', $card->back) }}</span>
        </div>
      @endif
      @if ($canSuggest || $canModerate)
        <div class="flex flex-wrap items-center gap-2 px-4 pb-3">
          @if ($canModerate)
            <button type="button" class="btn btn-ghost btn-sm"
                    hx-get="/moderation/decks/{{ $deck->id }}/cards/{{ $card->id }}/edit" hx-target="#card-sheet-body">
              Bearbeiten (Moderation)
            </button>
          @endif
          @if ($canSuggest)
          @if ($suggestion)
            <span class="badge {{ $suggestion->status === \App\Enums\SuggestionStatus::Declined ? 'badge-neutral' : 'badge-brand' }}">{{ $suggestion->status->label() }}</span>
          @endif
          @if ($suggestion?->status !== \App\Enums\SuggestionStatus::Open)
            <button type="button" class="btn btn-ghost btn-sm"
                    hx-get="/decks/{{ $deck->id }}/cards/{{ $card->id }}/suggest" hx-target="#suggest-sheet-body">
              Fehler melden
            </button>
          @endif
          @endif
        </div>
      @endif
    </details>
  @endif
</li>
