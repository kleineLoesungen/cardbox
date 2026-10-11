{{-- "Stapel": own and followed decks, or shared decks of others to discover. --}}
@extends('layouts.app')
@section('title', 'Stapel')

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="mb-4 text-2xl font-semibold tracking-tight">Stapel</h1>

  <nav class="segmented mb-4" aria-label="Ansicht">
    <a href="/decks" class="{{ $tab === 'mine' ? 'is-active' : '' }}" @if ($tab === 'mine') aria-current="page" @endif>Meine</a>
    <a href="/decks?tab=discover" class="{{ $tab === 'discover' ? 'is-active' : '' }}" @if ($tab === 'discover') aria-current="page" @endif>Entdecken</a>
  </nav>

  <x-form-error />

  @if ($tab === 'discover')
    <form method="get" action="/decks" class="mb-4 flex gap-2">
      <input type="hidden" name="tab" value="discover">
      <input class="input min-w-0 flex-1" type="search" name="q" value="{{ $search }}" placeholder="Suchen …" aria-label="Stapel suchen">
      <select class="select w-32 shrink-0" name="lang" aria-label="Sprache" onchange="this.form.submit()">
        <option value="">Alle</option>
        @foreach (\App\Enums\Language::cases() as $l)
          <option value="{{ $l->value }}" @selected($language === $l->value)>{{ $l->label() }}</option>
        @endforeach
      </select>
    </form>

    @if ($decks->isEmpty())
      <div class="empty">
        <p class="empty-title">Keine geteilten Stapel gefunden</p>
        <p class="empty-hint">
          @if ($search !== '' || $language !== '')
            Versuch es mit einem anderen Suchwort.
          @else
            Sobald jemand einen Stapel teilt, erscheint er hier.
          @endif
        </p>
      </div>
    @else
      <ul class="list">
        @foreach ($decks as $deck)
          <li>
            <a href="/decks/{{ $deck->id }}" class="list-row">
              <span class="min-w-0 flex-1">
                <span class="block truncate font-medium">{{ $deck->name }}</span>
                <span class="block truncate text-sm text-muted">
                  {{ $deck->directionLabel() }} · {{ $deck->cards_count === 1 ? '1 Karte' : $deck->cards_count.' Karten' }}
                </span>
                <span class="block truncate text-xs text-subtle">
                  von {{ $deck->user->name }}
                  @if ($deck->followers_count > 0)
                    · {{ $deck->followers_count === 1 ? '1 folgt' : $deck->followers_count.' folgen' }}
                  @endif
                </span>
              </span>
              @if ($deck->followed_by_me)
                <span class="badge badge-success shrink-0">du folgst</span>
              @endif
              <span aria-hidden="true" class="text-subtle">›</span>
            </a>
          </li>
        @endforeach
      </ul>
    @endif
  @else
    @if ($decks->isEmpty())
      <div class="empty">
        <p class="empty-title">Du hast noch keine Stapel</p>
        <p class="empty-hint">Leg einen eigenen an oder schau, was andere geteilt haben.</p>
        <button type="button" class="btn btn-primary" data-open-sheet="new-deck">Eigenen Stapel anlegen</button>
        <a href="/decks?tab=discover" class="btn btn-secondary">Geteilte Stapel entdecken</a>
      </div>
    @else
      <ul class="list">
        @foreach ($decks as $deck)
          <x-deck-row :deck="$deck" />
        @endforeach
      </ul>
    @endif
  @endif
</div>

<button type="button" class="fab" data-open-sheet="new-deck">
  <span aria-hidden="true" class="text-xl leading-none">+</span> Neuer Stapel
</button>

<x-sheet id="new-deck" title="Neuer Stapel" :data-open-on-load="$errors->any() || request()->boolean('new') ? 'true' : null">
  {{-- 'deck' => null explicitly: the loops above leave $deck set. --}}
  @include('decks.partials.form', ['deck' => null, 'action' => '/decks', 'submit' => 'Stapel anlegen'])
</x-sheet>
@endsection
