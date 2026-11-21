{{--
  One deck. Same layout for own, followed and merely viewed decks; whatever
  the viewer may not do is left out, not greyed out.
--}}
@extends('layouts.app')
@section('title', $deck->name)

@section('main')
{{-- Display only: the moderation routes check the role themselves. --}}
@php($canModerate = ! $isOwner && $deck->is_shared && auth()->user()->hasRole(\App\Enums\Role::Moderator))
<div class="mx-auto max-w-md">
  <div class="mb-4 flex items-start gap-2">
    <div class="min-w-0 flex-1">
      <x-back-link default="decks" />
      <h1 class="mt-1 text-2xl font-semibold tracking-tight break-words">{{ $deck->name }}</h1>
      <p class="mt-1 text-sm text-muted">
        {{ $deck->directionLabel() }} · @include('partials.card-count', ['count' => $cards->count(), 'oob' => false])
      </p>
      <p class="mt-1 text-sm text-muted">
        @if ($isOwner)
          @if ($deck->is_shared)
            <span class="badge badge-brand">geteilt</span>
            {{ $followerCount === 1 ? '1 Person lernt mit' : $followerCount.' Personen lernen mit' }}
          @elseif ($shares->isNotEmpty())
            <span class="badge badge-brand">geteilt mit {{ $shares->count() === 1 ? '1 Person' : $shares->count().' Personen' }}</span>
            @if ($followerCount > 0){{ $followerCount === 1 ? '1 lernt mit' : $followerCount.' lernen mit' }}@endif
          @else
            <span class="badge badge-neutral">privat</span> nur für dich sichtbar
          @endif
          @if ($deck->copied_from_name)
            · Kopie von „{{ $deck->copied_from_name }}“
          @endif
        @else
          von {{ $deck->user->name }}@if ($sharedWithMe && ! $deck->is_shared) · mit dir geteilt @endif
        @endif
      </p>
      @if ($deck->description)
        <p class="mt-2 text-sm break-words">{{ $deck->description }}</p>
      @endif
    </div>
    <button type="button" class="btn btn-ghost btn-icon mt-11 shrink-0" data-open-sheet="deck-menu" aria-label="Weitere Aktionen">
      <span aria-hidden="true" class="text-xl leading-none">⋯</span>
    </button>
  </div>

  <x-form-error />

  {{--
    "Lerne ich", "pausiert" or neither, switched right here (no menu):
    only "Lerne ich" decks are on "Heute" (docs/specs/aktive-stapel-und-freie-karten.md, A).
  --}}
  @if ($learningState !== null)
    <div class="card mb-4">
      <div class="card-body flex items-center gap-3">
        <p class="min-w-0 flex-1 text-sm">
          @if ($learningState === 'active')
            <span class="badge badge-success">lerne ich</span>
            <span class="text-muted">Kommt jeden Tag unter <a href="/learn" class="font-medium text-brand hover:underline">Heute</a> dran.</span>
          @elseif ($learningState === 'paused')
            <span class="badge badge-warning">pausiert</span>
            <span class="text-muted">Kommt gerade nicht unter „Heute“.</span>
          @else
            <span class="text-muted">Du lernst diesen Stapel gerade nicht.</span>
          @endif
        </p>
        <form method="post" action="/decks/{{ $deck->id }}/learning" class="shrink-0">
          @csrf
          <input type="hidden" name="on" value="{{ $learningState === 'active' ? '0' : '1' }}">
          <button type="submit" @class(['btn', 'btn-ghost btn-sm' => $learningState === 'active', 'btn-primary' => $learningState !== 'active'])>{{ match ($learningState) { 'active' => 'Pausieren', 'paused' => 'Wieder lernen', default => 'Lernen' } }}</button>
        </form>
      </div>
    </div>
  @endif

  @if ($openSuggestions > 0)
    <a href="/decks/{{ $deck->id }}/suggestions" class="alert alert-info mb-4 flex items-center justify-between gap-3">
      <span class="min-w-0">
        {{ $openSuggestions === 1 ? '1 Vorschlag zu deinen Karten.' : $openSuggestions.' Vorschläge zu deinen Karten.' }}
      </span>
      <span class="shrink-0 font-medium text-brand">Ansehen ›</span>
    </a>
  @endif

  @if ($changedOriginals > 0)
    <a href="/decks/{{ $deck->id }}/origin" class="alert alert-info mb-4 flex items-center justify-between gap-3">
      <span class="min-w-0">
        {{ $changedOriginals === 1 ? '1 Karte wurde im Original geändert.' : $changedOriginals.' Karten wurden im Original geändert.' }}
      </span>
      <span class="shrink-0 font-medium text-brand">Ansehen ›</span>
    </a>
  @endif

  {{-- Somebody else's deck the viewer does not learn yet: one way in ("Lernen" = follow). Copying is in "⋯". --}}
  @if (! $isOwner && ! $isFollowing)
    <div class="card mb-4">
      <div class="card-body stack">
        <p class="text-sm">Diesen Stapel hat {{ $deck->user->name }} geteilt.</p>
        <form method="post" action="/decks/{{ $deck->id }}/follow">
          @csrf
          <button type="submit" class="btn btn-primary btn-lg btn-block">Lernen</button>
          <p class="hint mt-1 text-center">Die Karten kommen ab jetzt unter Heute dran.</p>
        </form>
      </div>
    </div>
  @endif

  @if ($boxes !== null)
    @include('decks.partials.learn-panel')
  @endif

  <ul id="card-list" class="list">
    @foreach ($cards as $card)
      <x-card-row :card="$card" :deck="$deck" :editable="$isOwner"
                  :can-suggest="! $isOwner" :can-moderate="$canModerate"
                  :suggestion="$mySuggestions[$card->id] ?? null"
                  :paused="$activeIds !== null && ! in_array($card->id, $activeIds, true)"
                  :hard="in_array($card->id, $hardIds, true)" />
    @endforeach
  </ul>

  @if ($cards->isEmpty())
    @include('decks.partials.cards-empty', ['isOwner' => $isOwner])
  @endif
</div>

@if ($isOwner)
  <button type="button" class="fab" data-open-sheet="add-card">
    <span aria-hidden="true" class="text-xl leading-none">+</span> Karte
  </button>

  <x-sheet id="add-card" title="Karte hinzufügen" :data-open-on-load="$openAddSheet ? 'true' : null">
    @include('partials.card-add-form', ['deck' => $deck])
  </x-sheet>

  <x-sheet id="card-sheet" title="Karte bearbeiten"></x-sheet>

  <x-sheet id="adopt-sheet" title="Aus anderen Stapeln übernehmen">
    <div class="stack">
      <input class="input" type="search" name="q" placeholder="Wort suchen …" aria-label="Wort suchen"
             autocomplete="off"
             hx-get="/decks/{{ $deck->id }}/adopt" hx-trigger="input changed delay:400ms, search"
             hx-target="#adopt-results" hx-swap="outerHTML">
      <div id="adopt-results">
        <p class="hint">Gesucht wird in deinen Stapeln und in geteilten Stapeln mit {{ $deck->frontLabel() }} und {{ $deck->backLabel() }}. Übernommen wird eine Kopie.</p>
      </div>
    </div>
  </x-sheet>

  <x-sheet id="deck-edit" title="Stapel bearbeiten" :data-open-on-load="$errors->has('name') || $errors->has('back_language') || $errors->has('front_language') ? 'true' : null">
    @include('decks.partials.form', ['deck' => $deck, 'action' => "/decks/{$deck->id}", 'submit' => 'Speichern'])
  </x-sheet>
@endif

@if ($isOwner)
  {{-- Sharing (docs/specs/stapel-mit-einzelnen-teilen.md). --}}
  <x-sheet id="share-sheet" title="Teilen" :data-open-on-load="session('open_share') || $errors->share->any() ? 'true' : null">
    <div class="stack">
      <p class="text-sm text-muted">
        @if ($deck->is_shared)
          Mit allen geteilt: Alle finden den Stapel unter „Entdecken“.
        @elseif ($shares->isNotEmpty())
          Nur die Personen unten sehen den Stapel.
        @else
          Privat: nur du siehst den Stapel.
        @endif
      </p>

      <form method="post" action="/decks/{{ $deck->id }}/visibility">
        @csrf
        <input type="hidden" name="visibility" value="{{ $deck->is_shared ? 'people' : 'all' }}">
        <button type="submit" class="btn btn-secondary btn-block">
          {{ $deck->is_shared ? 'Nicht mehr mit allen teilen' : 'Mit allen teilen' }}
        </button>
      </form>

      <h3 class="text-sm font-semibold">Mit bestimmten Personen</h3>
      @if ($deck->is_shared && $shares->isNotEmpty())
        <p class="hint">Solange der Stapel mit allen geteilt ist, sehen ihn ohnehin alle. Die Liste gilt wieder, wenn du nicht mehr mit allen teilst.</p>
      @endif
      @if ($shares->isNotEmpty())
        <ul class="list">
          @foreach ($shares as $person)
            <li class="list-row">
              <span class="min-w-0 flex-1">
                <span class="block break-words font-medium">{{ $person->name }} <span class="font-normal text-muted">({{ $person->username }})</span></span>
                <span class="block text-sm text-muted">{{ $person->learns ? 'lernt mit' : 'noch nicht angenommen' }}</span>
              </span>
              <form method="post" action="/decks/{{ $deck->id }}/shares/{{ $person->id }}/delete" class="shrink-0"
                    data-confirm-action="Entfernen"
                    data-confirm="{{ $person->name }} verliert den Stapel. Teilst du ihn wieder, ist der Lernstand noch da.">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Entfernen</button>
              </form>
            </li>
          @endforeach
        </ul>
      @endif
      <form method="post" action="/decks/{{ $deck->id }}/shares" class="stack gap-2">
        @csrf
        <x-form-error bag="share" />
        <label class="label" for="share-username">Benutzername</label>
        <div class="flex gap-2">
          <input class="input min-w-0 flex-1" type="text" id="share-username" name="username" value="{{ old('username') }}"
                 autocomplete="off" autocapitalize="none" spellcheck="false" required placeholder="z. B. mia">
          <button type="submit" class="btn btn-primary shrink-0">Hinzufügen</button>
        </div>
        <p class="hint">Die Person findet den Stapel unter „Stapel → Mit dir geteilt“ und kann ihn lernen oder ablehnen.</p>
      </form>

      @if ($deck->is_shared || $shares->isNotEmpty())
        <form method="post" action="/decks/{{ $deck->id }}/visibility"
              @if ($shares->isNotEmpty()) data-confirm-action="Privat machen" data-confirm="{{ $shares->count() === 1 ? '1 Person verliert' : $shares->count().' Personen verlieren' }} den Stapel. Teilst du ihn wieder, ist ihr Lernstand noch da." @endif>
          @csrf
          <input type="hidden" name="visibility" value="private">
          <button type="submit" class="btn btn-ghost btn-block">Privat machen</button>
        </form>
      @endif
    </div>
  </x-sheet>
@endif

@if (! $isOwner)
  <x-sheet id="suggest-sheet" title="Fehler melden"></x-sheet>
@endif

@if ($canModerate)
  <x-sheet id="card-sheet" title="Karte korrigieren"></x-sheet>
@endif

{{-- "⋯" menu: what the viewer may do with this deck. --}}
<x-sheet id="deck-menu" :title="$deck->name">
  <div class="stack gap-1">
    @if ($cards->isNotEmpty() && ($isOwner || $isFollowing))
      {{-- Per person and deck (docs/specs/einfacher-lernen.md, C). --}}
      <form method="post" action="/decks/{{ $deck->id }}/reverse">
        @csrf
        <input type="hidden" name="on" value="{{ $withReverse ? '0' : '1' }}">
        <button type="submit" class="sheet-action flex items-center justify-between gap-3">
          <span class="min-w-0">
            Auch rückwärts lernen
            <span class="block text-xs font-normal text-muted">Dann fragt die App auch {{ \App\Enums\Direction::Backward->label($deck) }}.</span>
          </span>
          <span class="badge {{ $withReverse ? 'badge-success' : 'badge-neutral' }} shrink-0">{{ $withReverse ? 'an' : 'aus' }}</span>
        </button>
      </form>
    @endif
    @if ($cards->isNotEmpty() && ($isOwner || $isFollowing))
      {{-- Per person and deck (docs/specs/schreiben-und-erinnern.md, A1). --}}
      <form method="post" action="/decks/{{ $deck->id }}/typing">
        @csrf
        <input type="hidden" name="on" value="{{ $typeAnswers ? '0' : '1' }}">
        <button type="submit" class="sheet-action flex items-center justify-between gap-3">
          <span class="min-w-0">
            Antworten eintippen
            <span class="block text-xs font-normal text-muted">Statt umzudrehen schreibst du die Antwort.</span>
          </span>
          <span class="badge {{ $typeAnswers ? 'badge-success' : 'badge-neutral' }} shrink-0">{{ $typeAnswers ? 'an' : 'aus' }}</span>
        </button>
      </form>
    @endif
    @if ($cards->isNotEmpty())
      <a href="/decks/{{ $deck->id }}/selection" class="sheet-action">Karten auswählen (nur einen Teil lernen)</a>
    @endif
    @if ($isOwner)
      <button type="button" class="sheet-action" data-open-sheet="deck-edit">Umbenennen oder Sprachen ändern</button>
      <button type="button" class="sheet-action flex items-center justify-between gap-3" data-open-sheet="share-sheet">
        <span>Teilen</span>
        <span class="badge {{ $deck->is_shared || $shares->isNotEmpty() ? 'badge-brand' : 'badge-neutral' }} shrink-0">
          {{ $deck->is_shared ? 'mit allen' : ($shares->isNotEmpty() ? 'mit '.$shares->count() : 'privat') }}
        </span>
      </button>
      <a href="/decks/{{ $deck->id }}/import" class="sheet-action">Liste einfügen</a>
      <button type="button" class="sheet-action" data-open-sheet="adopt-sheet">Aus anderen Stapeln übernehmen</button>
      <form method="post" action="/decks/{{ $deck->id }}/copy">
        @csrf
        <button type="submit" class="sheet-action">Kopie anlegen</button>
      </form>
      <a href="/decks/{{ $deck->id }}/export" class="sheet-action" download>Als CSV exportieren</a>
      @include('decks.partials.reset-action')
      <form method="post" action="/decks/{{ $deck->id }}/delete"
            data-confirm-action="Stapel löschen"
            data-confirm="Stapel „{{ $deck->name }}“ mit {{ $cards->count() === 1 ? '1 Karte' : $cards->count().' Karten' }} löschen?{{ $followerCount > 0 ? ' '.($followerCount === 1 ? '1 Person lernt' : $followerCount.' Personen lernen').' mit und verliert ihn auch.' : '' }}">
        @csrf
        <button type="submit" class="sheet-action sheet-action-danger">Stapel löschen</button>
      </form>
    @else
      @if ($sharedWithMe && ! $deck->is_shared)
        {{-- Shared by name: out of the own decks; progress stays should it come again. --}}
        <form method="post" action="/decks/{{ $deck->id }}/decline"
              data-confirm-action="Stapel entfernen"
              data-confirm="„{{ $deck->name }}“ aus deinen Stapeln entfernen? Teilt {{ $deck->user->name }} ihn wieder mit dir, ist dein Lernstand noch da.">
          @csrf
          <button type="submit" class="sheet-action">Stapel entfernen</button>
        </form>
      @endif
      @if ($isFollowing)
        <form method="post" action="/decks/{{ $deck->id }}/unfollow"
              data-confirm-action="Nicht mehr lernen"
              data-confirm="Nicht mehr lernen? Dein Lernstand bleibt, falls du ihn wieder lernst.">
          @csrf
          <button type="submit" class="sheet-action">Nicht mehr lernen</button>
        </form>
      @else
        <form method="post" action="/decks/{{ $deck->id }}/follow">
          @csrf
          <button type="submit" class="sheet-action">Lernen</button>
        </form>
      @endif
      <form method="post" action="/decks/{{ $deck->id }}/copy">
        @csrf
        <button type="submit" class="sheet-action">
          <span class="min-w-0">
            Eigene Fassung anlegen
            <span class="block text-xs font-normal text-muted">Du bekommst eine Kopie, die du selbst ändern kannst.</span>
          </span>
        </button>
      </form>
      <a href="/decks/{{ $deck->id }}/export" class="sheet-action" download>Als CSV exportieren</a>
      @include('decks.partials.reset-action')

      {{-- Display only; the admin routes check the role themselves. --}}
      @if (auth()->user()->hasRole(\App\Enums\Role::Admin) && $deck->is_shared)
        <div class="my-2 border-t border-border"></div>
        <p class="px-3 text-xs font-medium text-subtle">Verwaltung</p>
        <a href="/admin/decks/{{ $deck->id }}/import" class="sheet-action">Liste einfügen (Karten überschreiben)</a>
      @endif

      {{-- Display only; the moderation routes check the role themselves. Never for decks shared only by name. --}}
      @if (auth()->user()->hasRole(\App\Enums\Role::Moderator) && $deck->is_shared)
        <div class="my-2 border-t border-border"></div>
        <p class="px-3 text-xs font-medium text-subtle">Moderation</p>
        <form method="post" action="/moderation/decks/{{ $deck->id }}/unshare"
              data-confirm-action="Teilen beenden"
              data-confirm="Teilen von „{{ $deck->name }}“ beenden? Er ist dann nur noch für {{ $deck->user->name }} sichtbar.">
          @csrf
          <button type="submit" class="sheet-action">Teilen beenden</button>
        </form>
        <form method="post" action="/moderation/decks/{{ $deck->id }}/delete"
              data-confirm-action="Stapel löschen"
              data-confirm="Stapel „{{ $deck->name }}“ von {{ $deck->user->name }} endgültig löschen?">
          @csrf
          <button type="submit" class="sheet-action sheet-action-danger">Stapel löschen</button>
        </form>
      @endif
    @endif
  </div>
</x-sheet>
@endsection
