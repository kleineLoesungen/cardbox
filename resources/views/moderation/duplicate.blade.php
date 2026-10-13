{{--
  Moderation: one group of duplicate cards. Pick the card that stays, adjust
  its content (meanings of the others can be added with a tap), merge.
  Cards in the same deck as the kept one are deleted (progress moves over),
  cards in other decks take its content and become linked copies.

  Sides are handled in a fixed language order "A/B" (alphabetical codes),
  because cards of one group may come from decks with the languages the
  other way round. The form shows the kept card's own order.

  @param string $groupCard  the card id the group was opened by
  @param \Illuminate\Support\Collection<int, \App\Models\Card> $group
  @param array<string, int> $learners  people with progress, per card id
--}}
@extends('layouts.app')
@section('title', 'Doppelte Karten')

@section('main')
@php($first = $group->first())
@php($langs = collect([$first->deck->front_language, $first->deck->back_language])->sort()->values())
@php($sides = fn (\App\Models\Card $c) => $c->deck->front_language === $langs[0] ? ['a' => $c->front, 'b' => $c->back] : ['a' => $c->back, 'b' => $c->front])
<div class="mx-auto max-w-md">
  <a href="/moderation/duplicates" class="text-sm text-muted hover:text-content">← Doppelte Karten</a>
  <h1 class="mt-1 text-2xl font-semibold tracking-tight break-words">{{ $first->frontText() }} – {{ $first->backText() }}</h1>
  <p class="mt-1 mb-4 text-sm text-muted">{{ $group->count() }} Karten in {{ $group->pluck('deck_id')->unique()->count() === 1 ? '1 Stapel' : $group->pluck('deck_id')->unique()->count().' Stapeln' }}</p>

  <x-form-error />

  <form method="post" action="/moderation/duplicates/{{ $groupCard }}/merge" class="stack" data-merge-form
        data-confirm-action="Zusammenführen"
        data-confirm="Zusammenführen? Karten im selben Stapel wie die bleibende werden gelöscht, ihr Lernstand wandert mit. Karten in anderen Stapeln übernehmen den Inhalt und bleiben mit ihr verknüpft.">
    @csrf

    <fieldset class="stack gap-2">
      <legend class="mb-2 text-sm font-semibold">Welche Karte bleibt?</legend>
      @foreach ($group as $card)
        @php($s = $sides($card))
        <input type="hidden" name="cards[]" value="{{ $card->id }}">
        <label class="choice-card"
               data-card
               data-a="{{ json_encode($s['a']) }}" data-b="{{ json_encode($s['b']) }}" data-hint="{{ $card->hint }}"
               data-a-front="{{ $card->deck->front_language === $langs[0] ? '1' : '0' }}"
               data-front-label="{{ $card->deck->frontLabel() }}" data-back-label="{{ $card->deck->backLabel() }}">
          <input type="radio" name="keep" value="{{ $card->id }}" @checked(old('keep', $first->id) === $card->id)>
          <span class="min-w-0 flex-1 stack gap-1">
            <span class="block text-sm text-muted">
              „{{ $card->deck->name }}“ von {{ $card->deck->user->name }}
              · {{ ($learners[$card->id] ?? 0) === 1 ? '1 Person lernt sie' : ($learners[$card->id] ?? 0).' Personen lernen sie' }}
            </span>
            <span class="block break-words">
              <span class="font-semibold">{{ implode(', ', $card->front) }}</span>@if ($card->hint) <span class="text-sm text-muted">{{ $card->hint }}</span>@endif
              – {{ implode(', ', $card->back) }}
            </span>
            {{-- Meanings of this card, to add to the kept one with a tap. --}}
            <span class="flex flex-wrap gap-1">
              @foreach (['a', 'b'] as $side)
                @foreach ($s[$side] as $meaning)
                  <button type="button" class="btn btn-ghost btn-sm" data-take-meaning data-side="{{ $side }}" data-text="{{ $meaning }}" hidden>+ {{ $meaning }}</button>
                @endforeach
              @endforeach
            </span>
          </span>
        </label>
      @endforeach
    </fieldset>

    <div class="card">
      <div class="card-body stack">
        <p class="text-sm font-semibold">So bleibt die Karte</p>
        @php($kept = $group->firstWhere('id', old('keep', $first->id)) ?? $first)
        @foreach ([['front', $kept->deck->frontLabel(), $kept->front], ['back', $kept->deck->backLabel(), $kept->back]] as [$name, $label, $values])
          <div class="field">
            <span class="label" data-side-label="{{ $name }}">{{ $label }}</span>
            <div class="stack gap-2" id="merge-{{ $name }}-group">
              @foreach (old($name, $values) as $i => $meaning)
                @include('partials.meaning-row', ['name' => $name.'[]', 'value' => $meaning, 'lang' => null, 'label' => $label, 'main' => $i === 0])
              @endforeach
            </div>
            <button type="button" class="btn btn-ghost btn-sm self-start" data-add-meaning="merge-{{ $name }}-group">+ weitere Bedeutung</button>
          </div>
        @endforeach
        <div class="field">
          <label class="label" for="merge-hint">Hinweis <span class="font-normal text-muted">(freiwillig)</span></label>
          <input class="input" type="text" id="merge-hint" name="hint" value="{{ old('hint', $kept->hint) }}" maxlength="{{ config('learning.max_hint_length') }}" autocomplete="off">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">Zusammenführen</button>
    <p class="hint text-center">Karten im selben Stapel werden gelöscht, ihr Lernstand wandert mit. Karten in anderen Stapeln werden angeglichen und verknüpft.</p>
  </form>
</div>

<script>
  // Picking another card to keep fills the form with its content (in its
  // own language order); "+ meaning" buttons add what the form lacks.
  (() => {
    const form = document.querySelector("[data-merge-form]");
    if (!form) return;
    const group = (name) => document.getElementById(`merge-${name}-group`);
    const keptCard = () => form.querySelector("input[name=keep]:checked")?.closest("[data-card]");
    // Side "a"/"b" of the fixed order → "front"/"back" of the kept card.
    const formSide = (side) => ((side === "a") === (keptCard()?.dataset.aFront === "1") ? "front" : "back");
    const values = (name) => [...group(name).querySelectorAll("input")].map((i) => i.value.trim()).filter(Boolean);

    const addRow = (name, value) => {
      const empty = [...group(name).querySelectorAll("input")].find((i) => i.value.trim() === "");
      if (empty) { empty.value = value; return; }
      form.querySelector(`[data-add-meaning="merge-${name}-group"]`).click();
      const inputs = group(name).querySelectorAll("input");
      inputs[inputs.length - 1].value = value;
    };

    const refreshButtons = () => {
      form.querySelectorAll("[data-take-meaning]").forEach((button) => {
        const present = values(formSide(button.dataset.side)).map((v) => v.toLowerCase());
        button.hidden = present.includes(button.dataset.text.toLowerCase());
      });
    };

    const fillFrom = (card) => {
      const a = JSON.parse(card.dataset.a);
      const b = JSON.parse(card.dataset.b);
      const [front, back] = card.dataset.aFront === "1" ? [a, b] : [b, a];
      for (const [name, list, label] of [["front", front, card.dataset.frontLabel], ["back", back, card.dataset.backLabel]]) {
        const rows = [...group(name).querySelectorAll("[data-meaning-row]")];
        rows.slice(1).forEach((row) => row.remove());
        rows[0].querySelector("input").value = list[0] ?? "";
        list.slice(1).forEach((value) => addRow(name, value));
        form.querySelector(`[data-side-label="${name}"]`).textContent = label;
        group(name).querySelectorAll("input").forEach((input) => { input.dataset.label = label; });
      }
      form.querySelector("#merge-hint").value = card.dataset.hint ?? "";
      refreshButtons();
    };

    form.addEventListener("change", (e) => {
      if (e.target.name === "keep") fillFrom(e.target.closest("[data-card]"));
    });
    form.addEventListener("input", refreshButtons);
    form.addEventListener("click", (e) => {
      const button = e.target.closest("[data-take-meaning]");
      if (!button) return;
      e.preventDefault();
      addRow(formSide(button.dataset.side), button.dataset.text);
      refreshButtons();
    });
    refreshButtons();
  })();
</script>
@endsection
