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
      <a href="/decks" class="text-sm text-muted hover:text-content">← Stapel</a>
      <h1 class="mt-1 text-2xl font-semibold tracking-tight break-words">{{ $deck->name }}</h1>
      <p class="mt-1 text-sm text-muted">
        {{ $deck->directionLabel() }} · @include('partials.card-count', ['count' => $cards->count(), 'oob' => false])
      </p>
      <p class="mt-1 text-sm text-muted">
        @if ($isOwner)
          @if ($deck->is_shared)
            <span class="badge badge-brand">geteilt</span>
            {{ $followerCount === 1 ? '1 Person folgt' : $followerCount.' Personen folgen' }}
          @else
            <span class="badge badge-neutral">privat</span> nur für dich sichtbar
          @endif
          @if ($deck->copied_from_name)
            · Kopie von „{{ $deck->copied_from_name }}“
          @endif
        @else
          von {{ $deck->user->name }}
          @if ($isFollowing)
            · <span class="badge badge-success">du folgst</span>
          @endif
        @endif
      </p>
      @if ($deck->description)
        <p class="mt-2 text-sm break-words">{{ $deck->description }}</p>
      @endif
    </div>
    <button type="button" class="btn btn-ghost btn-icon mt-6 shrink-0" data-open-sheet="deck-menu" aria-label="Weitere Aktionen">
      <span aria-hidden="true" class="text-xl leading-none">⋯</span>
    </button>
  </div>

  <x-form-error />

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

  {{-- Somebody else's deck the viewer does not follow yet: the two ways to use it. --}}
  @if (! $isOwner && ! $isFollowing)
    <div class="card mb-4">
      <div class="card-body stack">
        <p class="text-sm">Diesen Stapel hat {{ $deck->user->name }} geteilt. Wie möchtest du ihn nutzen?</p>
        <form method="post" action="/decks/{{ $deck->id }}/follow">
          @csrf
          <button type="submit" class="btn btn-primary btn-lg btn-block">Folgen</button>
          <p class="hint mt-1 text-center">Du lernst mit, {{ $deck->user->name }} hält ihn aktuell.</p>
        </form>
        <form method="post" action="/decks/{{ $deck->id }}/copy">
          @csrf
          <button type="submit" class="btn btn-secondary btn-lg btn-block">Kopieren</button>
          <p class="hint mt-1 text-center">Du bekommst eine eigene Fassung zum Ändern.</p>
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
                  :can-suggest="! $isOwner && $deck->is_shared" :can-moderate="$canModerate"
                  :suggestion="$mySuggestions[$card->id] ?? null" />
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

@if (! $isOwner && $deck->is_shared)
  <x-sheet id="suggest-sheet" title="Fehler melden"></x-sheet>
@endif

@if ($canModerate)
  <x-sheet id="card-sheet" title="Karte korrigieren"></x-sheet>
@endif

{{-- "⋯" menu: what the viewer may do with this deck. --}}
<x-sheet id="deck-menu" :title="$deck->name">
  <div class="stack gap-1">
    @if ($isOwner)
      <button type="button" class="sheet-action" data-open-sheet="deck-edit">Umbenennen oder Sprachen ändern</button>
      <form method="post" action="/decks/{{ $deck->id }}/share">
        @csrf
        <input type="hidden" name="shared" value="{{ $deck->is_shared ? '0' : '1' }}">
        <button type="submit" class="sheet-action">
          {{ $deck->is_shared ? 'Nicht mehr teilen' : 'Mit allen teilen' }}
        </button>
      </form>
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
            data-confirm="Stapel „{{ $deck->name }}“ mit {{ $cards->count() === 1 ? '1 Karte' : $cards->count().' Karten' }} löschen?{{ $followerCount > 0 ? ' '.($followerCount === 1 ? '1 Person folgt' : $followerCount.' Personen folgen').' ihm und verliert ihn auch.' : '' }}">
        @csrf
        <button type="submit" class="sheet-action sheet-action-danger">Stapel löschen</button>
      </form>
    @else
      @if ($isFollowing)
        <form method="post" action="/decks/{{ $deck->id }}/unfollow"
              data-confirm-action="Nicht mehr folgen"
              data-confirm="Nicht mehr folgen? Dein Lernstand bleibt erhalten, falls du wieder folgst.">
          @csrf
          <button type="submit" class="sheet-action">Nicht mehr folgen</button>
        </form>
      @else
        <form method="post" action="/decks/{{ $deck->id }}/follow">
          @csrf
          <button type="submit" class="sheet-action">Folgen</button>
        </form>
      @endif
      <form method="post" action="/decks/{{ $deck->id }}/copy">
        @csrf
        <button type="submit" class="sheet-action">Als eigenen Stapel kopieren</button>
      </form>
      <a href="/decks/{{ $deck->id }}/export" class="sheet-action" download>Als CSV exportieren</a>
      @include('decks.partials.reset-action')

      {{-- Display only; the admin routes check the role themselves. --}}
      @if (auth()->user()->hasRole(\App\Enums\Role::Admin) && $deck->is_shared)
        <div class="my-2 border-t border-border"></div>
        <p class="px-3 text-xs font-medium text-subtle">Verwaltung</p>
        <a href="/admin/decks/{{ $deck->id }}/import" class="sheet-action">Liste einfügen (Karten überschreiben)</a>
      @endif

      {{-- Display only; the moderation routes check the role themselves. --}}
      @if (auth()->user()->hasRole(\App\Enums\Role::Moderator))
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
