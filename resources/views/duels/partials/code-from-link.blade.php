{{--
  An invitation link (/duell#CODE, "Einladung teilen" on the duel page)
  fills the code field. The code sits behind "#", so it never reaches the
  server. On the guest page it is also kept for the tab: who signs in from
  there finds it in "Duelle" again.

  @param string $field  id of the code input
  @param bool $keep  remember the code for the sign-in (guest page)
--}}
<script>
  (() => {
    const pattern = /^[A-Za-z0-9]{4}-?[A-Za-z0-9]{4}$/;
    let code = "";
    try {
      code = decodeURIComponent(location.hash.slice(1));
      if (!pattern.test(code)) code = sessionStorage.getItem("duel-invite") || "";
      @if ($keep)
        if (pattern.test(code)) sessionStorage.setItem("duel-invite", code);
      @else
        sessionStorage.removeItem("duel-invite");
      @endif
    } catch (_) {
      // Storage blocked: the link still fills the field.
    }
    const input = document.getElementById(@js($field));
    if (input && !input.value && pattern.test(code)) input.value = code.toUpperCase();
  })();
</script>
