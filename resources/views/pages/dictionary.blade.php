{{--
  "Wörterbuch": a search field that looks words up in every visible card.
  Works without JavaScript as a plain GET form; with htmx the results
  follow the typing.

  @param string $term
  @param string $language  language code of the filter, '' = all
  @param list<\App\Enums\Language> $languages  languages in visible decks
  @param array $result     from DictionaryService::search
--}}
@extends('layouts.app')
@section('title', 'Wörterbuch')

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">Wörterbuch</h1>
  <p class="mt-1 mb-4 text-sm text-muted">Schlag ein Wort nach – in deinen Stapeln und allen geteilten.</p>

  <form method="get" action="/dictionary" class="stack gap-2" role="search">
    <input class="input input-lg" type="search" name="q" value="{{ $term }}" placeholder="Wort suchen …"
           aria-label="Wort suchen" autocomplete="off" autocapitalize="off" enterkeyhint="search"
           @if ($term === '') autofocus @endif
           hx-get="/dictionary/search" hx-trigger="input changed delay:350ms, search, dictionary-refresh from:body"
           hx-target="#dictionary-results" hx-swap="outerHTML" hx-include="[name=lang]">
    @if (count($languages) > 1)
      <select class="select" name="lang" aria-label="Sprache"
              hx-get="/dictionary/search" hx-trigger="change"
              hx-target="#dictionary-results" hx-swap="outerHTML" hx-include="[name=q]">
        <option value="">Alle Sprachen</option>
        @foreach ($languages as $lang)
          <option value="{{ $lang->value }}" @selected($language === $lang->value)>{{ $lang->label() }}</option>
        @endforeach
      </select>
    @endif
    <noscript><button type="submit" class="btn btn-primary btn-block">Suchen</button></noscript>
  </form>

  <div class="mt-4">
    @include('partials.dictionary-results', ['term' => $term, 'result' => $result])
  </div>
</div>

{{-- Editing, deleting and "Fehler melden" (issue #12); filled via htmx. --}}
<x-sheet id="dictionary-sheet" title="Karte"></x-sheet>

@push('scripts')
  @include('partials.speak-script')
@endpush
@endsection
