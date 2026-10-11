{{-- Antwort auf POST /notes — genau EIN Eintrag, wird oben in die Liste geschoben. --}}
<x-note-item :note="$note" />
@include('partials.toast-oob')
