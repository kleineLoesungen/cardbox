{{-- Eine Zeile der Token-Tabelle:  <x-token-row :token="$token" /> --}}
@props(['token'])
<tr id="token-{{ $token->id }}">
  <td class="font-medium">{{ $token->name }}</td>
  <td><code class="code">{{ $token->displayHint() }}</code></td>
  <td>
    <div class="flex flex-wrap gap-1">
      @forelse ($token->scopeLabels() as $label)
        <span class="badge badge-neutral">{{ $label }}</span>
      @empty
        <span class="text-xs text-subtle">keine</span>
      @endforelse
    </div>
  </td>
  <td>
    @if ($token->isValid() && $token->scopes !== [])
      <span class="badge badge-success">{{ $token->statusLabel() }}</span>
    @elseif ($token->isValid())
      <span class="badge badge-warning">{{ $token->statusLabel() }}</span>
    @else
      <span class="badge badge-neutral">{{ $token->statusLabel() }}</span>
    @endif
  </td>
  <td class="text-muted">
    @if ($token->last_used_at)
      <x-datetime :value="$token->last_used_at" />
    @else
      <span class="text-subtle">nie</span>
    @endif
  </td>
  <td class="text-right">
    @if ($token->isValid())
      <button type="button"
              class="btn btn-ghost btn-sm text-danger"
              hx-post="/admin/tokens/{{ $token->id }}/revoke"
              hx-target="#token-{{ $token->id }}"
              hx-swap="outerHTML"
              hx-confirm="Token widerrufen? Systeme, die es benutzen, verlieren sofort den Zugriff.">
        Widerrufen
      </button>
    @endif
  </td>
</tr>
