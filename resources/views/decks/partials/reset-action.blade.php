{{--
  "Fortschritt zurücksetzen" in the deck menu: deletes the selected run with
  its progress — only the viewer's own, only this plan. Absent when the
  deck has not been started.
--}}
@if ($run)
  <form method="post" action="/decks/{{ $deck->id }}/runs/{{ $run->id }}/reset"
        data-confirm-action="Zurücksetzen"
        data-confirm="Deinen Lernstand für „{{ $deck->name }}“ mit Lernplan {{ $run->label() }} löschen? Die Karten bleiben. Danach kannst du den Stapel neu starten.">
    @csrf
    <button type="submit" class="sheet-action">Fortschritt zurücksetzen{{ $runs->count() > 1 ? ' ('.$run->label().')' : '' }}</button>
  </form>
@endif
