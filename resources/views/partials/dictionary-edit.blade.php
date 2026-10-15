{{--
  Sheet "Karte" in the dictionary (issue #12). With one card in the hit the
  form comes at once; with several, first a choice: one deck or all the
  person may change. Decks they may not change offer "Fehler melden".

  Which cards are offered for editing is display only (as `canModerate` in
  decks/show): own decks always, shared decks of others for moderation. The
  routes decide (/dictionary for own cards, /moderation/dictionary behind
  role:moderator).

  @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\Card> $group  from DictionaryService::group, the shown card first
  @param string|null $selected  card id, "all" or null
--}}
@php
    $me = auth()->user();
    $moderator = $me->hasRole(\App\Enums\Role::Moderator);
    $canEdit = fn (\App\Models\Card $c) => $c->deck->user_id === $me->id || $moderator;
    $base = $group->first();
    $editable = $group->filter($canEdit)->values();
    $chosen = match (true) {
        $selected === 'all' => $editable,
        $selected !== null => $editable->where('id', $selected)->values(),
        $group->count() === 1 => $editable,
        default => collect(),
    };
@endphp

@if ($chosen->isNotEmpty())
  @php
      $card = $chosen->first();
      $deck = $card->deck;
      $count = $chosen->count();
      $decks = $chosen->pluck('deck')->unique('id');
      $foreign = $chosen->filter(fn ($c) => $c->deck->user_id !== $me->id)->count();
      $prefix = $foreign > 0 ? '/moderation' : '';
      $followers = $decks->where('is_shared', true)->sum('followers_count');
      $question = ($count > 1 ? "{$count} Karten löschen?" : 'Karte löschen?').match (true) {
          $followers === 1 => ' Eine Person, die damit lernt, verliert sie samt Lernstand.',
          $followers > 1 => " {$followers} Personen, die damit lernen, verlieren sie samt Lernstand.",
          default => '',
      };
  @endphp
  <div class="stack">
    @if ($count > 1)
      <p class="alert alert-info">
        Du änderst {{ $count }} Karten in {{ $decks->count() }} Stapeln. Alle bekommen diesen Inhalt.
        @if ($foreign > 0)
          Davon {{ $foreign === 1 ? 'eine' : $foreign }} von anderen – alle, die damit lernen, sehen die Änderung.
        @endif
      </p>
    @elseif ($foreign > 0)
      <p class="alert alert-warning">Moderation: Du änderst eine Karte von {{ $deck->user->name }}. Alle, die mit dem Stapel lernen, sehen die Änderung.</p>
    @else
      <p class="text-sm text-muted">aus „{{ $deck->name }}“ (dein Stapel)</p>
    @endif

    <form class="stack" hx-post="{{ $prefix }}/dictionary/cards" hx-swap="none">
      @foreach ($chosen as $c)
        <input type="hidden" name="cards[]" value="{{ $c->id }}">
      @endforeach
      <input type="hidden" name="base" value="{{ $card->id }}">

      @include('partials.card-fields', ['idPrefix' => 'dict'])

      <button type="submit" class="btn btn-primary btn-lg btn-block">{{ $count > 1 ? "Alle {$count} speichern" : 'Speichern' }}</button>
    </form>

    <form hx-post="{{ $prefix }}/dictionary/cards/delete" hx-swap="none"
          data-confirm-action="{{ $count > 1 ? 'Karten löschen' : 'Karte löschen' }}" hx-confirm="{{ $question }}">
      @foreach ($chosen as $c)
        <input type="hidden" name="cards[]" value="{{ $c->id }}">
      @endforeach
      <button type="submit" class="btn btn-ghost btn-block text-danger">{{ $count > 1 ? "Alle {$count} löschen" : 'Karte löschen' }}</button>
    </form>

    @if ($group->count() > 1)
      <button type="button" class="btn btn-ghost btn-sm self-start"
              hx-get="/dictionary/cards/{{ $base->id }}/edit" hx-target="#dictionary-sheet-body">← Andere Stapel wählen</button>
    @endif
  </div>
@else
  @php
      $deckCount = $group->unique('deck_id')->count();
  @endphp
  <div class="stack gap-3">
    <p class="text-sm text-muted">„{{ $base->frontText() }}“ steht in {{ $deckCount === 1 ? 'einem Stapel' : "{$deckCount} Stapeln" }}. Welche Karte meinst du?</p>

    @if ($editable->count() > 1)
      <button type="button" class="btn btn-secondary btn-block"
              hx-get="/dictionary/cards/{{ $base->id }}/edit?selected=all" hx-target="#dictionary-sheet-body">
        Alle ({{ $editable->count() }}) bearbeiten
      </button>
    @endif

    <ul class="stack gap-2">
      @foreach ($group as $card)
        {{-- Content in the orientation of the shown card, to spot differences. --}}
        @php
            $deck = $card->deck;
            $swap = $deck->front_language !== $base->deck->front_language;
            $front = $swap ? $card->back : $card->front;
            $back = $swap ? $card->front : $card->back;
        @endphp
        <li class="card">
          <div class="card-body stack gap-2">
            <p class="min-w-0 break-words font-medium">
              „{{ $deck->name }}“
              <span class="text-sm font-normal text-muted">{{ $deck->user_id === $me->id ? 'dein Stapel' : 'von '.$deck->user->name }}</span>
            </p>
            @if ($front !== $base->front || $back !== $base->back || $card->hint !== $base->hint)
              <p class="break-words text-sm text-muted">Abweichend: {{ implode(', ', $front) }} – {{ implode(', ', $back) }}@if ($card->hint) ({{ $card->hint }})@endif</p>
            @endif
            @if ($canEdit($card))
              <button type="button" class="btn btn-ghost btn-sm self-start"
                      hx-get="/dictionary/cards/{{ $base->id }}/edit?selected={{ $card->id }}" hx-target="#dictionary-sheet-body">
                {{ $deck->user_id === $me->id ? 'Bearbeiten' : 'Bearbeiten (Moderation)' }}
              </button>
            @else
              <button type="button" class="btn btn-ghost btn-sm self-start"
                      hx-get="/decks/{{ $deck->id }}/cards/{{ $card->id }}/suggest?context=dictionary" hx-target="#dictionary-sheet-body">
                Fehler melden
              </button>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
  </div>
@endif
