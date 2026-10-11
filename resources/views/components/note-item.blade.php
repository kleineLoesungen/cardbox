{{-- Ein Eintrag der Notizliste:  <x-note-item :note="$note" /> --}}
@props(['note'])
<article data-note="{{ $note->id }}"
         class="card transition-opacity"
         id="note-{{ $note->id }}">
  <div class="card-body flex items-start justify-between gap-4">
    <div class="min-w-0 flex-1">
      <h3 class="truncate font-medium text-content">{{ $note->title }}</h3>
      @if ($note->body !== '')
        <p class="mt-1 line-clamp-3 text-sm whitespace-pre-line text-muted">{{ $note->body }}</p>
      @endif
      <p class="mt-2 text-xs text-subtle">
        Angelegt am <x-datetime :value="$note->created_at" />
      </p>
    </div>

    <button type="button"
            class="btn btn-ghost btn-sm text-danger"
            hx-delete="/notes/{{ $note->id }}"
            hx-target="#note-{{ $note->id }}"
            hx-swap="outerHTML swap:150ms"
            hx-confirm="Diese Notiz wirklich löschen?"
            aria-label="Notiz löschen">
      Löschen
    </button>
  </div>
</article>
