{{--
  Moderation: groups of duplicate cards in shared decks, most cards first.

  @param list<array{cards: \Illuminate\Support\Collection<int, \App\Models\Card>, decks: int}> $groups
  @param bool $more
  @param int  $page
--}}
@extends('layouts.app')
@section('title', 'Doppelte Karten')

@section('main')
<div class="mx-auto max-w-md">
  <a href="/moderation" class="text-sm text-muted hover:text-content">← Moderation</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Doppelte Karten</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Gleiches Wortpaar in geteilten Stapeln. Du entscheidest, welche Karte bleibt.
  </p>

  @if ($groups === [])
    <div class="empty">
      <p class="empty-title">Alles aufgeräumt</p>
      <p class="empty-hint">In den geteilten Stapeln gibt es gerade keine doppelten Karten.</p>
    </div>
  @else
    <ul class="list">
      @foreach ($groups as $group)
        @php($card = $group['cards']->first())
        <li>
          <a href="/moderation/duplicates/{{ $card->id }}" class="list-row">
            <span class="card-row-sides">
              <span>{{ $card->frontText() }}</span>
              <span class="text-muted">{{ $card->backText() }}</span>
            </span>
            <span class="badge badge-neutral shrink-0">
              {{ $group['cards']->count() }} in {{ $group['decks'] === 1 ? '1 Stapel' : $group['decks'].' Stapeln' }}
            </span>
          </a>
        </li>
      @endforeach
    </ul>
    <div class="mt-4 flex justify-between">
      @if ($page > 1)
        <a href="/moderation/duplicates?page={{ $page - 1 }}" class="btn btn-ghost">← Zurück</a>
      @else
        <span></span>
      @endif
      @if ($more)
        <a href="/moderation/duplicates?page={{ $page + 1 }}" class="btn btn-ghost">Weiter →</a>
      @endif
    </div>
  @endif
</div>
@endsection
