{{--
  Out-of-Band-Fragment: htmx setzt es unabhaengig vom eigentlichen Ziel in
  #toasts ein. So kann jede htmx-Antwort nebenbei eine Meldung mitschicken.

    @include('partials.toast-oob')   mit $toast = ['kind' => 'success'|'danger', 'message' => '…']
--}}
@isset($toast)
<div id="toasts" hx-swap-oob="beforeend">
  <div class="toast toast-{{ $toast['kind'] }}" data-autohide>
    <span>{{ $toast['message'] }}</span>
  </div>
</div>
@endisset
