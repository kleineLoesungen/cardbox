{{-- Empty state below the (empty) card list; also sent out of band after deleting the last card. --}}
<div id="cards-empty" class="empty mt-4">
  @if ($isOwner)
    <p class="empty-title">Noch keine Karten</p>
    <p class="empty-hint">Tippe auf „+ Karte“ und schreib vorne und hinten ein Wort hinein.</p>
    <button type="button" class="btn btn-primary" data-open-sheet="add-card">Erste Karte anlegen</button>
  @else
    <p class="empty-title">Dieser Stapel ist noch leer.</p>
  @endif
</div>
