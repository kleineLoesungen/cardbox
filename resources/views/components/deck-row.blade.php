{{--
  One deck in a list: name, direction, card count; for other people's decks
  the owner's display name (never the username).
--}}
@props(['deck'])
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
    </span>
    @if ($own && $deck->is_shared)
      <span class="badge badge-brand shrink-0" title="Mit allen geteilt">geteilt</span>
    @elseif ($own)
      <span class="badge badge-neutral shrink-0" title="Nur für dich sichtbar">privat</span>
    @endif
    <span aria-hidden="true" class="text-subtle">›</span>
  </a>
</li>
