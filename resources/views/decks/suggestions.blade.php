{{--
  The owner's list of open suggestions for one deck: the card now, the
  proposal (changed sides highlighted), type, comment and the sender's
  display name. "Übernehmen" changes the card, "Ablehnen" leaves it.

  @param \App\Models\Deck $deck
  @param \Illuminate\Support\Collection<int, \App\Models\CardSuggestion> $suggestions  with card and user
--}}
@extends('layouts.app')
@section('title', 'Vorschläge')

@section('main')
@php($frontTag = \App\Enums\Language::tryFrom($deck->front_language)?->tag())
@php($backTag = \App\Enums\Language::tryFrom($deck->back_language)?->tag())
<div class="mx-auto max-w-md">
  <a href="/decks/{{ $deck->id }}" class="text-sm text-muted hover:text-content">← {{ $deck->name }}</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Vorschläge</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Hinweise von Leuten, die mit „{{ $deck->name }}“ lernen. Übernimmst du einen, ändert sich die Karte für alle.
  </p>

  <x-form-error />

  <div class="stack">
    @foreach ($suggestions as $suggestion)
      @php($card = $suggestion->card)
      <div class="card">
        <div class="card-body stack gap-3">
          <p class="flex flex-wrap items-center gap-2 text-sm">
            <span class="badge badge-brand">{{ $suggestion->type->label() }}</span>
            <span class="text-muted">von {{ $suggestion->user->name }}</span>
          </p>

          <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
            <span class="text-subtle">Jetzt</span>
            <span class="min-w-0 break-words">
              <span lang="{{ $frontTag }}">{{ implode(', ', $card->front) }}</span>
              – <span class="text-muted" lang="{{ $backTag }}">{{ implode(', ', $card->back) }}</span>
            </span>
            @if ($suggestion->proposesText())
              <span class="font-medium text-brand">Neu</span>
              <span class="min-w-0 break-words">
                <span lang="{{ $frontTag }}" @class(['font-semibold' => $suggestion->proposed_front !== null])>{{ implode(', ', $suggestion->proposed_front ?? $card->front) }}</span>
                – <span lang="{{ $backTag }}" @class(['font-semibold' => $suggestion->proposed_back !== null])>{{ implode(', ', $suggestion->proposed_back ?? $card->back) }}</span>
              </span>
            @endif
          </div>

          @if ($suggestion->comment)
            <p class="text-sm break-words">„{{ $suggestion->comment }}“</p>
          @endif
          @if ($card->updated_at->greaterThan($suggestion->card_updated_at))
            <p class="hint">Die Karte wurde seit dem Vorschlag geändert.</p>
          @endif

          <div class="grid grid-cols-2 gap-2">
            <form method="post" action="/decks/{{ $deck->id }}/suggestions/{{ $suggestion->id }}/decline">
              @csrf
              <button type="submit" class="btn btn-secondary btn-block">Ablehnen</button>
            </form>
            <form method="post" action="/decks/{{ $deck->id }}/suggestions/{{ $suggestion->id }}/accept">
              @csrf
              <button type="submit" class="btn btn-primary btn-block">{{ $suggestion->proposesText() ? 'Übernehmen' : 'Erledigt' }}</button>
            </form>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection
