{{--
  Bottom sheet on a native <dialog>.

    <x-sheet id="add-card" title="Karte hinzufügen"> … </x-sheet>

  Open it with any element carrying data-open-sheet="add-card", or by
  swapping htmx content into it (it opens itself, see layouts/app). Close
  with the × button, Escape, a tap on the backdrop, or the htmx event
  `sheet-close` (response header HX-Trigger: sheet-close).
--}}
@props(['id', 'title'])
<dialog id="{{ $id }}" class="dialog-sheet" aria-labelledby="{{ $id }}-title" {{ $attributes }}>
  <div class="dialog-sheet-header">
    <h2 class="dialog-sheet-title" id="{{ $id }}-title">{{ $title }}</h2>
    <form method="dialog">
      <button type="submit" class="btn btn-ghost btn-icon" aria-label="Schließen">✕</button>
    </form>
  </div>
  <div class="dialog-sheet-body" id="{{ $id }}-body">
    {{ $slot }}
  </div>
</dialog>
