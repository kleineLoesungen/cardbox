{{-- Die Stichpunkte einer Version; Migrationen hervorgehoben, weil sie beim
     Deployment eine eigene Rolle spielen. --}}
@props(['entry'])
<ul class="stack gap-1 text-sm text-muted">
  @foreach ($entry->items as $punkt)
    <li>{{ $punkt }}</li>
  @endforeach
  @foreach ($entry->migrations as $migration)
    <li><span class="badge badge-warning">Migration</span> {{ $migration }}</li>
  @endforeach
</ul>
