{{-- "Fortschritt zurücksetzen" in the deck menu; only the viewer's own progress. --}}
<form method="post" action="/decks/{{ $deck->id }}/reset"
      data-confirm-action="Zurücksetzen"
      data-confirm="Deinen Lernstand für „{{ $deck->name }}“ löschen? Alle Karten sind danach wieder neu – in beiden Richtungen.">
  @csrf
  <button type="submit" class="sheet-action">Fortschritt zurücksetzen</button>
</form>
