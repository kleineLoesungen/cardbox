{{--
  One card in a deck's list: front | back.

  Own deck: tapping opens the edit sheet (#card-sheet). Other people's decks:
  read only; tapping unfolds all meanings.
--}}
@props(['card', 'deck', 'editable' => false])
@php($more = count($card->front) + count($card->back) - 2)
<li id="card-{{ $card->id }}">
  @if ($editable)
    <button type="button" class="list-row"
            hx-get="/decks/{{ $deck->id }}/cards/{{ $card->id }}/edit"
            hx-target="#card-sheet-body"
            aria-label="Karte {{ $card->frontText() }} bearbeiten">
      <span class="card-row-sides">
        <span lang="{{ \App\Enums\Language::tryFrom($deck->front_language)?->tag() }}">{{ $card->frontText() }}</span>
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
          <span lang="{{ \App\Enums\Language::tryFrom($deck->front_language)?->tag() }}">{{ $card->frontText() }}</span>
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
    </details>
  @endif
</li>
