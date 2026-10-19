{{--
  "Karten auswählen" (issue #10): which cards of this deck the person learns.
  Book order (oldest first), so "bis hier" means "up to this point of the
  book". Plain form; "Alle", "Keine" and "bis hier" are a small script.

  @param \App\Models\Deck $deck
  @param \Illuminate\Support\Collection<int, \App\Models\Card> $cards
  @param list<string>|null $activeIds  null = every card is active
--}}
@extends('layouts.app')
@section('title', 'Karten auswählen')

@section('main')
@php($active = fn (string $id) => $activeIds === null || in_array($id, $activeIds, true))
<div class="mx-auto max-w-md">
  <a href="/decks/{{ $deck->id }}" class="text-sm text-muted hover:text-content">← {{ $deck->name }}</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight">Karten auswählen</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Lern erst einen Teil, z. B. die ersten Kapitel. Pausierte Karten kommen nicht dran und behalten ihren Lernstand.
    Neue Karten, die später dazukommen, bleiben pausiert, bis du sie anhakst – außer du hakst alle an.
  </p>

  <x-form-error />

  <form method="post" action="/decks/{{ $deck->id }}/selection" class="stack" data-selection-form>
    @csrf
    <div class="flex flex-wrap items-center gap-2">
      <button type="button" class="btn btn-ghost btn-sm" data-select="all">Alle</button>
      <button type="button" class="btn btn-ghost btn-sm" data-select="none">Keine</button>
      <span class="ml-auto text-sm text-muted" data-selection-count aria-live="polite"></span>
    </div>

    <ul class="list">
      @foreach ($cards as $i => $card)
        <li class="list-row gap-3">
          <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
            <input type="checkbox" class="checkbox shrink-0" name="cards[]" value="{{ $card->id }}" @checked($active($card->id))>
            <span class="card-row-sides">
              <span>{{ $card->frontText() }}@if ($card->hint) <span class="text-xs text-subtle">{{ $card->hint }}</span>@endif</span>
              <span class="text-muted">{{ $card->backText() }}</span>
            </span>
          </label>
          <button type="button" class="btn btn-ghost btn-sm shrink-0" data-up-to="{{ $i }}"
                  aria-label="Karten 1 bis {{ $i + 1 }} lernen, den Rest pausieren">bis hier</button>
        </li>
      @endforeach
    </ul>

    {{-- Always in view, however far the list is scrolled (issue #17). --}}
    <div class="sticky-actions">
      <button type="submit" class="btn btn-primary btn-lg btn-block">Auswahl speichern</button>
    </div>
  </form>

  @if ($activeIds !== null)
    <form method="post" action="/decks/{{ $deck->id }}/selection/all" class="mt-2">
      @csrf
      <button type="submit" class="btn btn-ghost btn-block">Alle Karten lernen, auch künftige</button>
    </form>
  @endif
</div>

<script>
  (() => {
    const form = document.querySelector("[data-selection-form]");
    const boxes = [...form.querySelectorAll("input[name='cards[]']")];
    const count = form.querySelector("[data-selection-count]");
    const update = () => {
      count.textContent = `${boxes.filter((b) => b.checked).length} von ${boxes.length} aktiv`;
    };
    form.addEventListener("click", (e) => {
      const all = e.target.closest("[data-select]");
      const upTo = e.target.closest("[data-up-to]");
      if (all) boxes.forEach((b) => { b.checked = all.dataset.select === "all"; });
      if (upTo) boxes.forEach((b, i) => { b.checked = i <= Number(upTo.dataset.upTo); });
      update();
    });
    form.addEventListener("change", update);
    update();
  })();
</script>
@endsection
