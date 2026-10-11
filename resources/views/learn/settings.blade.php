{{--
  "Lernplan": three presets or a custom plan. Without JavaScript the number
  of boxes is a plain number field and all interval fields are visible (the
  server uses the first N−1); the script adds −/+ and the live preview.
--}}
@extends('layouts.app')
@section('title', 'Lernplan')

@section('main')
@php($presets = \App\Enums\StudyPlan::cases())
@php($selected = old('plan', $current['preset']?->value ?? 'custom'))
@php($max = \App\Services\LearningSettingsService::MAX_BOXES)
@php($gaps = $current['day_gaps'])
@php($last = end($gaps) ?: 1)
@for ($i = count($gaps); $i < $max - 1; $i++)
  @php($last = min(\App\Services\LearningSettingsService::MAX_GAP_DAYS, $last * 2))
  @php($gaps[] = $last)
@endfor
{{-- Old input only as plain values; a hand-made nested array must not break the page. --}}
@php($oldGaps = old('day_gaps'))
@if (is_array($oldGaps))
  @php($gaps = array_map(fn ($v) => is_scalar($v) ? $v : 1, array_values($oldGaps)))
@endif
@php($oldCount = old('box_count'))
@php($boxCount = is_scalar($oldCount) && is_numeric($oldCount) ? (int) $oldCount : $current['box_count'])

<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">Lernplan</h1>
  <p class="mt-1 mb-4 text-sm text-muted">
    Wie oft Karten wiederkommen. Gilt für alle deine Stapel. Dein Lernstand bleibt erhalten.
  </p>

  <x-form-error />

  <form method="post" action="/learn/settings" class="stack" data-plan-form
        data-min="{{ \App\Services\LearningSettingsService::MIN_BOXES }}" data-max="{{ $max }}">
    @csrf

    <fieldset class="stack gap-2">
      <legend class="sr-only">Lernplan wählen</legend>
      @foreach ($presets as $plan)
        <label class="choice-card">
          <input type="radio" name="plan" value="{{ $plan->value }}" @checked($selected === $plan->value)>
          <span class="min-w-0">
            <span class="block font-semibold">{{ $plan->label() }}</span>
            <span class="block text-sm text-muted">{{ $plan->description() }}</span>
            <span class="mt-1 block text-xs text-subtle">
              {{ $plan->boxCount() }} Fächer · {{ implode(' · ', $plan->dayGaps()) }} Tage
            </span>
          </span>
        </label>
      @endforeach

      <label class="choice-card">
        <input type="radio" name="plan" value="custom" @checked($selected === 'custom')>
        <span class="min-w-0">
          <span class="block font-semibold">Eigener Plan</span>
          <span class="block text-sm text-muted">Fächer und Abstände selbst festlegen.</span>
        </span>
      </label>
    </fieldset>

    <div class="card" data-custom-plan>
      <div class="card-body stack">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <label class="label" id="box-count-label" for="box-count">Anzahl Fächer</label>
          {{-- Without JavaScript a plain number field; the script swaps in −/+. --}}
          <input class="input w-24" type="number" inputmode="numeric" id="box-count" name="box_count"
                 min="{{ \App\Services\LearningSettingsService::MIN_BOXES }}" max="{{ $max }}"
                 value="{{ $boxCount }}" data-box-count>
          <div class="stepper" role="group" aria-labelledby="box-count-label" data-stepper hidden>
            <button type="button" class="btn btn-secondary btn-icon" data-step="-1" aria-label="Ein Fach weniger">−</button>
            <output data-box-output aria-live="polite">{{ $boxCount }}</output>
            <button type="button" class="btn btn-secondary btn-icon" data-step="1" aria-label="Ein Fach mehr">+</button>
          </div>
        </div>
        <x-field-error name="box_count" />

        <p class="hint">Nach wie vielen Tagen kommt eine Karte in diesem Fach wieder?</p>
        <div class="grid grid-cols-2 gap-3">
          @for ($i = 1; $i < $max; $i++)
            <div class="field min-w-0" data-gap="{{ $i }}">
              <label class="label" for="gap-{{ $i }}">Fach {{ $i }}</label>
              <div class="flex items-center gap-2">
                <input class="input" type="number" inputmode="numeric" id="gap-{{ $i }}" name="day_gaps[]"
                       min="1" max="{{ \App\Services\LearningSettingsService::MAX_GAP_DAYS }}"
                       value="{{ $gaps[$i - 1] ?? 1 }}">
                <span class="text-sm text-muted">Tage</span>
              </div>
            </div>
          @endfor
        </div>
        <x-field-error name="day_gaps" />
        @foreach ($errors->get('day_gaps.*') as $messages)
          <p class="error">{{ $messages[0] }}</p>
          @break
        @endforeach

        <p class="text-sm" data-plan-preview aria-live="polite"></p>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg btn-block">Speichern</button>
  </form>
</div>
@endsection

@push('scripts')
<script>
  // Lernplan: show the custom fields only for "Eigener Plan", −/+ for the
  // number of boxes, and a preview "Fach 1 → 1 Tag → Fach 2 → …".
  (() => {
    const form = document.querySelector("[data-plan-form]");
    if (!form) return;
    const custom = form.querySelector("[data-custom-plan]");
    const count = form.querySelector("[data-box-count]");
    const output = form.querySelector("[data-box-output]");
    const preview = form.querySelector("[data-plan-preview]");
    const min = Number(form.dataset.min);
    const max = Number(form.dataset.max);

    // With JavaScript: hide the number field, show the −/+ stepper.
    count.type = "hidden";
    form.querySelector("[data-stepper]").hidden = false;

    const render = () => {
      const isCustom = form.querySelector("input[name=plan]:checked")?.value === "custom";
      custom.hidden = !isCustom;
      const n = Number(count.value);
      output.textContent = n;
      const parts = [];
      form.querySelectorAll("[data-gap]").forEach((field) => {
        const i = Number(field.dataset.gap);
        field.hidden = i > n - 1;
        // Hidden fields are not sent, so only the first N−1 values arrive.
        field.querySelector("input").disabled = i > n - 1;
        if (i <= n - 1) {
          const days = Number(field.querySelector("input").value) || 1;
          parts.push(`Fach ${i} → ${days} ${days === 1 ? "Tag" : "Tage"}`);
        }
      });
      preview.textContent = parts.join(" → ") + " → Kann ich";
    };

    form.addEventListener("change", render);
    form.addEventListener("input", render);
    form.querySelectorAll("[data-step]").forEach((button) => {
      button.addEventListener("click", () => {
        count.value = Math.min(max, Math.max(min, Number(count.value) + Number(button.dataset.step)));
        render();
      });
    });
    render();
  })();
</script>
@endpush
