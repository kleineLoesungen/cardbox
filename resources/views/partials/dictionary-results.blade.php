{{--
  Hits of the dictionary search. One hit per word pair: main meanings,
  further ones as "auch: …", hint, read aloud, where it comes from. First
  offer: learn the card in its deck (follow it, or switch it on); second:
  a copy into an own deck with the same languages.

  @param string $term
  @param array{hits: list<array{card: \App\Models\Card, decks: list<\App\Models\Deck>, targets: list<\App\Models\Deck>}>, more: bool} $result
--}}
<div id="dictionary-results" aria-live="polite">
  @if (mb_strlen(trim($term)) < 2)
    <div class="empty">
      <p class="empty-title">Nachschlagen statt lernen</p>
      <p class="empty-hint">Tippe ein Wort in irgendeiner Sprache. Du siehst die Übersetzung, kannst sie anhören und in einen eigenen Stapel übernehmen.</p>
    </div>
  @elseif ($result['hits'] === [])
    <div class="empty">
      <p class="empty-title">Nichts gefunden</p>
      <p class="empty-hint">„{{ $term }}“ steht in keinem Stapel, den du sehen kannst.</p>
    </div>
  @else
    <ul class="stack gap-3">
      @foreach ($result['hits'] as $hit)
        @php($card = $hit['card'])
        @php($deck = $card->deck)
        <li class="card">
          <div class="card-body stack gap-3">
            @foreach ([[$deck->frontLabel(), $deck->front_language, $card->front], [$deck->backLabel(), $deck->back_language, $card->back]] as $i => [$label, $code, $side])
              @php($tag = \App\Enums\Language::tryFrom($code)?->tag())
              <div class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $label }}</p>
                  <p class="{{ $i === 0 ? 'text-xl font-semibold' : 'text-lg' }} break-words" lang="{{ $tag }}">
                    {{ $side[0] ?? '' }}@if ($i === 0 && $card->hint) <span class="text-sm font-normal text-muted">{{ $card->hint }}</span>@endif
                  </p>
                  @if (count($side) > 1)
                    <p class="text-sm text-muted">auch: <span lang="{{ $tag }}">{{ implode(', ', \App\Models\Card::alternatives($side)) }}</span></p>
                  @endif
                </div>
                <button type="button" class="btn btn-ghost btn-icon shrink-0" data-speak hidden
                        data-speak-text="{{ $side[0] ?? '' }}" data-speak-lang="{{ $tag }}" aria-label="{{ $side[0] ?? '' }} vorlesen">
                  <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>
                  </svg>
                </button>
              </div>
            @endforeach

            <p class="text-xs text-muted">
              @if (count($hit['decks']) === 1)
                aus „{{ $deck->name }}“ {{ $deck->user_id === auth()->id() ? '(dein Stapel)' : 'von '.$deck->user->name }}
              @else
                {{-- Names alone can repeat (an original and its copy), so each says whose it is. --}}
                in {{ count($hit['decks']) }} Stapeln: {{ collect($hit['decks'])->map(fn ($d) => '„'.$d->name.'“ '.($d->user_id === auth()->id() ? '(dein Stapel)' : 'von '.$d->user->name))->implode(', ') }}
              @endif
            </p>

            {{-- First offer: learn the card where it is (issue #10); copying comes second. --}}
            @if ($hit['learn'] === 'active')
              <p class="text-sm"><span class="badge badge-success">du lernst sie</span></p>
            @else
              <button type="button" class="btn btn-primary btn-block"
                      hx-post="/dictionary/learn/{{ $card->id }}" hx-target="this" hx-swap="outerHTML">
                {{ $hit['learn'] === 'follow' ? 'Folgen und diese Karte lernen' : 'Diese Karte lernen' }}
              </button>
              @if ($hit['learn'] === 'follow')
                <p class="hint -mt-1">Du folgst „{{ $deck->name }}“, lernst aber erst einmal nur diese Karte. Weitere schaltest du im Stapel dazu.</p>
              @endif
            @endif

            @if ($hit['targets'] !== [])
              <details>
                <summary class="btn btn-ghost btn-sm cursor-pointer list-none">Als Kopie in eigenen Stapel</summary>
                <div class="mt-2 stack gap-2">
                  @foreach ($hit['targets'] as $target)
                    <button type="button" class="btn btn-secondary btn-block justify-start"
                            hx-post="/dictionary/adopt/{{ $card->id }}" hx-vals="{{ json_encode(['deck' => $target->id]) }}"
                            hx-target="this" hx-swap="outerHTML">
                      <span class="truncate">„{{ $target->name }}“</span>
                    </button>
                  @endforeach
                </div>
              </details>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
    @if ($result['more'])
      <p class="hint mt-3 text-center">Es gibt noch mehr Treffer – such genauer.</p>
    @endif
  @endif
</div>
