{{-- Below the front field while typing: warns about an existing card with the same front. --}}
@if ($exists)
  <p id="front-hint" class="hint text-warning" role="status">Gibt es schon in diesem Stapel. Speichern geht trotzdem.</p>
@else
  <p id="front-hint" hidden></p>
@endif
