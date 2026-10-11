{{--
  Results of "Aus anderen Stapeln übernehmen", prepared by
  CardService::searchToAdopt. "+" copies the card into this deck; it is a
  copy, not a link, so later changes to the original do not arrive here.
--}}
<div id="adopt-results">
  @if (mb_strlen($term) < 2)
    <p class="hint">Mindestens zwei Buchstaben eingeben.</p>
  @elseif ($results === [])
    <p class="hint">Nichts gefunden. Gesucht wird in deinen Stapeln und in geteilten Stapeln mit denselben Sprachen.</p>
  @else
    <ul class="list">
      @foreach ($results as $result)
        <li class="list-row">
          <span class="min-w-0 flex-1">
            <span class="card-row-sides">
              <span>{{ $result['front'] }}</span>
              <span class="text-muted">{{ $result['back'] }}</span>
            </span>
            <span class="block truncate text-xs text-subtle">aus „{{ $result['deck'] }}“</span>
          </span>
          @if ($result['exists'])
            <span class="badge badge-neutral shrink-0">schon im Stapel</span>
          @else
            <button type="button" class="btn btn-secondary btn-icon shrink-0"
                    hx-post="/decks/{{ $deck->id }}/adopt/{{ $result['id'] }}"
                    hx-target="closest li" hx-swap="outerHTML"
                    aria-label="{{ $result['front'] }} übernehmen">+</button>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</div>
