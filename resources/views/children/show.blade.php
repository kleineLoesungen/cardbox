{{--
  A child's progress for a parent (docs/specs/eltern-ansicht.md, B), and —
  for parents of 18 or older — the child's decks to edit and share, decks
  others gave the child, and decks to look through once (eltern-konten.md).
  No single answers.
--}}
@extends('layouts.app')
@section('title', $child->name)

@section('main')
<div class="mx-auto max-w-md">
  @if ($backToList)
    <a href="/children" class="back-link">← Meine Kinder</a>
  @endif
  <h1 @class(['text-2xl font-semibold tracking-tight', 'mt-1' => $backToList])>{{ $child->name }}</h1>

  @if ($overview['blocked'])
    <div class="alert alert-warning mt-6" role="status">Dieses Konto ist gerade gesperrt.</div>
  @else
    @php($day = $overview['day'])
    @if ($overview['streak'] > 0)
      <p class="mt-1 text-sm text-muted">
        <span aria-hidden="true" class="mr-1">🔥</span>
        {{ $overview['streak'] === 1 ? '1 Tag' : $overview['streak'].' Tage' }} in Folge
      </p>
    @endif
    <p class="mt-1">
      @if ($day['goal_met'] || $day['answers'] >= $day['goal'])
        <strong class="text-success">Heute: Tagesziel geschafft</strong>
      @else
        Heute: <strong>{{ $day['answers'] }} von {{ $day['goal'] }}</strong> gewusst
      @endif
    </p>

    @unless ($overview['learned_ever'])
      <p class="empty mt-6">{{ $child->name }} hat noch nicht gelernt.</p>
    @else
      {{-- --- Learning days: four weeks, Monday to Sunday ------------------- --}}
      <section class="card mt-6">
        <div class="card-header"><h2 class="card-title">Lerntage</h2></div>
        <div class="card-body stack">
          {{-- What the tiles are (issue #18). --}}
          <p class="text-sm text-muted">Jede Kachel ist ein Tag, jede Zeile eine Woche von Montag bis Sonntag. Unten steht die aktuelle Woche, heute ist umrandet.</p>
          <div class="day-strip" role="list" aria-label="Lerntage der letzten vier Wochen">
            @foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $weekday)
              <span class="day-strip-head" aria-hidden="true">{{ $weekday }}</span>
            @endforeach
            @foreach ($overview['weeks'] as $week)
              @foreach ($week as $cell)
                @php($label = $cell['date']->locale('de')->isoFormat('dd D.M.').': '.['goal' => 'Ziel geschafft', 'learned' => 'gelernt', 'none' => 'nicht gelernt', 'future' => 'noch nicht'][$cell['state']])
                <span role="listitem" @class(['day', 'day-'.$cell['state'], 'day-today' => $cell['today']])>
                  <span aria-hidden="true">{{ $cell['date']->day }}</span>
                  <span class="sr-only">{{ $label }}</span>
                </span>
              @endforeach
            @endforeach
          </div>
          <p class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-hidden="true">
            <span><span class="day day-goal day-legend"></span> Ziel geschafft</span>
            <span><span class="day day-learned day-legend"></span> gelernt</span>
            <span><span class="day day-none day-legend"></span> nicht gelernt</span>
          </p>
        </div>
      </section>

      {{-- --- Vocabulary ----------------------------------------------------- --}}
      @php($vocabulary = $overview['vocabulary'])
      <section class="card mt-4">
        <div class="card-header"><h2 class="card-title">Wortschatz</h2></div>
        <div class="card-body stack">
          @if ($vocabulary['mastered'] > 0)
            <p>
              Kann schon <strong>{{ $vocabulary['mastered'] === 1 ? '1 Wort' : $vocabulary['mastered'].' Wörter' }}</strong>
              @if ($vocabulary['mastered_week'] > 0)
                <span class="block text-sm text-success">+{{ $vocabulary['mastered_week'] }} diese Woche</span>
              @endif
            </p>
            <div>
              <h3 class="mb-1 text-sm font-semibold">Zuletzt gelernt</h3>
              <ul class="text-sm text-muted">
                @foreach ($overview['recent'] as $word)
                  <li>{{ $word }}</li>
                @endforeach
              </ul>
            </div>
          @else
            <p class="text-sm text-muted">
              {{ $vocabulary['in_box'] === 1 ? '1 Wort' : $vocabulary['in_box'].' Wörter' }} im Karteikasten,
              noch keins in „Kann ich“.
            </p>
          @endif
        </div>
      </section>
    @endunless

    {{-- --- Decks: no links, parents do not see card lists ----------------- --}}
    @if ($overview['decks']->isNotEmpty())
      <h2 class="mt-6 mb-2 text-base font-semibold">Stapel</h2>
      <x-progress-legend :who="$child->name" class="mb-2" />
      <ul class="list">
        @foreach ($overview['decks'] as $deck)
          @php($stats = $overview['stats'][$deck->id] ?? ['due' => 0, 'mastered_percent' => 0])
          @php($progress = $overview['progress'][$deck->id] ?? ['new' => 0, 'learning' => 0, 'almost' => 0, 'mastered' => 0, 'total' => 0, 'mastered_percent' => 0, 'almost_percent' => 0, 'learning_percent' => 0])
          <li class="list-row">
            <span class="min-w-0 flex-1">
              <span class="block break-words font-medium">{{ $deck->name }}</span>
              <span class="block truncate text-sm text-muted">{{ $deck->directionLabel() }}</span>
              {{-- Share of the cards (issues #18, #26). --}}
              <x-progress-split :progress="$progress" :who="$child->name" legend class="mt-2" />
            </span>
            @if ($stats['due'] > 0)
              <span class="badge badge-brand shrink-0">{{ $stats['due'] }} fällig</span>
            @endif
          </li>
        @endforeach
      </ul>
    @endif

    @if ($manages)
      {{-- --- Shared before parents existed: look once (eltern-konten.md, F) --- --}}
      @if ($reviewPending->isNotEmpty())
        <section class="card mt-6 border-warning">
          <div class="card-header"><h2 class="card-title">Bitte einmal durchsehen</h2></div>
          <div class="card-body stack">
            <p class="text-sm">{{ $child->name }} hat diese Stapel geteilt. Teilen dürfen jetzt nur Erwachsene – bitte entscheide, ob sie geteilt bleiben. Achte auf Urheber- und Lizenzrechte.</p>
            <ul class="list">
              @foreach ($reviewPending as $pending)
                <li class="list-row flex-wrap">
                  <a href="/decks/{{ $pending->id }}" class="min-w-0 flex-1">
                    <span class="block break-words font-medium">{{ $pending->name }}</span>
                    <span class="block text-sm text-muted">{{ $pending->is_shared ? 'mit allen geteilt' : 'geteilt mit '.($pending->shares_count === 1 ? '1 Person' : $pending->shares_count.' Personen') }}</span>
                  </a>
                  <span class="flex shrink-0 gap-2">
                    <form method="post" action="/children/{{ $child->id }}/review/{{ $pending->id }}">
                      @csrf
                      <input type="hidden" name="keep" value="0">
                      <button type="submit" class="btn btn-ghost btn-sm">Privat machen</button>
                    </form>
                    <form method="post" action="/children/{{ $child->id }}/review/{{ $pending->id }}">
                      @csrf
                      <input type="hidden" name="keep" value="1">
                      <button type="submit" class="btn btn-secondary btn-sm">So lassen</button>
                    </form>
                  </span>
                </li>
              @endforeach
            </ul>
          </div>
        </section>
      @endif

      {{-- --- The child's decks: edit like the child (eltern-konten.md, D) ---- --}}
      <h2 class="mt-6 mb-2 text-base font-semibold">Stapel von {{ $child->name }} bearbeiten</h2>
      @if ($childDecks->isNotEmpty())
        <ul class="list">
          @foreach ($childDecks as $childDeck)
            <li>
              <a href="/decks/{{ $childDeck->id }}" class="list-row">
                <span class="min-w-0 flex-1">
                  <span class="block break-words font-medium">{{ $childDeck->name }}</span>
                  <span class="block truncate text-sm text-muted">{{ $childDeck->directionLabel() }} · {{ $childDeck->cards_count === 1 ? '1 Karte' : $childDeck->cards_count.' Karten' }}</span>
                </span>
                @if ($childDeck->is_shared)
                  <span class="badge badge-brand shrink-0">geteilt</span>
                @elseif ($childDeck->shares_count > 0)
                  <span class="badge badge-brand shrink-0">geteilt mit {{ $childDeck->shares_count }}</span>
                @else
                  <span class="badge badge-neutral shrink-0">privat</span>
                @endif
                <span aria-hidden="true" class="text-subtle">›</span>
              </a>
            </li>
          @endforeach
        </ul>
      @else
        <p class="text-sm text-muted">{{ $child->name }} hat noch keine eigenen Stapel.</p>
      @endif
      <button type="button" class="btn btn-secondary btn-block mt-3" data-open-sheet="child-deck">Neuer Stapel für {{ $child->name }}</button>

      {{-- --- Decks others gave the child ------------------------------------ --}}
      @if ($sharedWithChild->isNotEmpty())
        <h2 class="mt-6 mb-2 text-base font-semibold">Mit {{ $child->name }} geteilt</h2>
        <ul class="list">
          @foreach ($sharedWithChild as $given)
            <li class="list-row">
              <span class="min-w-0 flex-1">
                <span class="block break-words font-medium">{{ $given->name }}</span>
                <span class="block text-sm text-muted">von {{ $given->user->name }}{{ $given->by_name ? '' : ' · mit allen geteilt' }}</span>
              </span>
              <form method="post" action="/children/{{ $child->id }}/shared/{{ $given->id }}/decline" class="shrink-0"
                    data-confirm-action="{{ $given->by_name ? 'Ablehnen' : 'Entfernen' }}"
                    data-confirm="„{{ $given->name }}“ bei {{ $child->name }} {{ $given->by_name ? 'ablehnen? Er kann danach nicht noch einmal zugeschoben werden.' : 'entfernen? Der Lernstand bleibt.' }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">{{ $given->by_name ? 'Ablehnen' : 'Entfernen' }}</button>
              </form>
            </li>
          @endforeach
        </ul>
      @endif
    @endif

    {{-- --- Tricky words ------------------------------------------------------ --}}
    @if ($overview['learned_ever'] && $overview['hard'] !== [])
      <section class="card mt-6">
        <div class="card-header"><h2 class="card-title">Knifflige Wörter</h2></div>
        <div class="card-body stack">
          <p class="text-sm text-muted">Zum Mitüben: Frag die Wörter einfach mal beim Abendessen ab.</p>
          <ul class="stack gap-1">
            @foreach ($overview['hard'] as $hard)
              <li>{{ $hard['word'] }} <span class="text-xs text-subtle">· {{ $hard['deck'] }}</span></li>
            @endforeach
          </ul>
        </div>
      </section>
    @endif
  @endif

  <p class="mt-6 text-center text-sm text-muted">
    @if ($manages)
      Die Zuordnung als Elternteil hat die Verwaltung eingerichtet.
    @else
      {{-- Linked by a code before eltern-konten.md: overview only. --}}
      Du siehst den Fortschritt. Stapel bearbeiten und teilen kannst du, sobald die Verwaltung dich als Elternteil bestätigt.
    @endif
  </p>
</div>

@if ($manages)
  <x-sheet id="child-deck" title="Neuer Stapel für {{ $child->name }}" :data-open-on-load="$errors->any() ? 'true' : null">
    @include('decks.partials.form', ['deck' => null, 'action' => "/children/{$child->id}/decks", 'submit' => 'Stapel anlegen'])
  </x-sheet>
@endif
@endsection
