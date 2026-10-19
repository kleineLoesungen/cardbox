{{--
  "Zurück" on a page below a section: to the section the person came from
  (Heute, Stapel, Wörterbuch, …), `default` when none is remembered.
  docs/specs/navigation.md

    <x-back-link default="decks" />
--}}
@props(['default'])
@php($back = \App\Support\Navigation::back(request(), $default))
<a href="{{ $back['url'] }}" class="text-sm text-muted hover:text-content">← {{ $back['label'] }}</a>
