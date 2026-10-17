{{--
  Cards in one box (sheet behind the box tiles), with "practise these".

  @param string $deckId
  @param string $runId
  @param \App\Enums\Direction $direction
  @param int $boxCount  number of the "Kann ich" box
  @param int $box
  @param list<array{card: \App\Models\Card, days: int|null}> $entries  days until due, 0 = today
--}}
@php($name = $box === 0 ? 'Neu' : ($box === $boxCount ? 'Kann ich' : 'Fach '.$box))
@if ($entries === [])
  <p class="hint">In „{{ $name }}“ liegt gerade keine Karte.</p>
@else
  {{-- Free practice on just this box: same direction, nothing is saved. --}}
  <form method="post" action="/learn/round" class="mb-4">
    @csrf
    <input type="hidden" name="mode" value="practice">
    <input type="hidden" name="deck" value="{{ $deckId }}">
    <input type="hidden" name="direction" value="{{ $direction->value }}">
    <input type="hidden" name="box" value="{{ $box }}">
    <input type="hidden" name="run" value="{{ $runId }}">
    <button type="submit" class="btn btn-primary btn-lg btn-block">
      {{ count($entries) === 1 ? 'Diese Karte üben' : 'Diese '.count($entries).' Karten üben' }}
    </button>
    <p class="hint mt-1 text-center">
      Freies Üben – zählt nicht.@if (count($entries) > $roundSize) Je Runde {{ $roundSize }} zufällige Karten.@endif
    </p>
  </form>

  <ul class="list">
    @foreach ($entries as $entry)
      @php($days = $entry['days'])
      <li class="list-row">
        <span class="card-row-sides">
          <span>{{ $entry['card']->frontText() }}</span>
          <span class="text-muted">{{ $entry['card']->backText() }}</span>
        </span>
        <span class="badge badge-neutral shrink-0">
          @if ($box === 0)
            neu
          @elseif ($days === null)
            kann ich
          @elseif ($days <= 0)
            heute
          @elseif ($days === 1)
            morgen
          @else
            in {{ $days }} Tagen
          @endif
        </span>
      </li>
    @endforeach
  </ul>
@endif
