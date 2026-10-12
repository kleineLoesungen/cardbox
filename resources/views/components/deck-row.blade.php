{{--
  One deck in a list: name, direction, card count; for other people's decks
  the owner's display name (never the username). With learning stats: share
  of mastered cards and cards due today.
--}}
@props(['deck', 'stats' => null])
@php($own = $deck->user_id === auth()->id())
<li>
  <a href="/decks/{{ $deck->id }}" class="list-row">
    <span class="min-w-0 flex-1">
      <span class="block truncate font-medium">{{ $deck->name }}</span>
      <span class="block truncate text-sm text-muted">
        {{ $deck->directionLabel() }} · {{ $deck->cards_count === 1 ? '1 Karte' : ($deck->cards_count ?? 0).' Karten' }}
      </span>
      @unless ($own)
        <span class="block truncate text-xs text-subtle">von {{ $deck->user->name }}</span>
      @endunless
      @if ($own && ($deck->open_suggestions_count ?? 0) > 0)
        <span class="block truncate text-xs font-medium text-brand">
          {{ $deck->open_suggestions_count === 1 ? '1 Vorschlag' : $deck->open_suggestions_count.' Vorschläge' }}
        </span>
      @endif
      @if ($stats !== null && ($deck->cards_count ?? 0) > 0)
        <span class="meter meter-success mt-2" title="{{ $stats['mastered_percent'] }} % kann ich">
          <span style="width: {{ $stats['mastered_percent'] }}%"></span>
        </span>
      @endif
    </span>
    @if ($stats !== null && $stats['due'] > 0)
      <span class="badge badge-brand shrink-0">{{ $stats['due'] }} fällig</span>
    @elseif ($own && $deck->is_shared)
      <span class="badge badge-brand shrink-0" title="Mit allen geteilt">geteilt</span>
    @elseif ($own)
      <span class="badge badge-neutral shrink-0" title="Nur für dich sichtbar">privat</span>
    @endif
    <span aria-hidden="true" class="text-subtle">›</span>
  </a>
</li>
