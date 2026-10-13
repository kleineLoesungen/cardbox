{{--
  Full-screen layout for a learning round: no header, no tab bar — just the
  card and the buttons under the thumbs. The round partial brings its own
  "✕" to leave.
--}}
@extends('layouts.base')

@section('body')
<main class="min-h-dvh bg-surface">
  @yield('main')
</main>

<script>
  // --- Learning round: flip, rate, keyboard -----------------------------
  // Event delegation, because htmx swaps the whole round after each answer.
  // The flipped state lives on [data-card-screen], whose id changes per
  // card — see learn/partials/round-body.blade.php.
  const flip = () => {
    const round = document.querySelector("[data-card-screen]");
    if (!round || round.classList.contains("is-flipped")) return;
    round.classList.add("is-flipped");
    round.querySelector("[data-back]")?.removeAttribute("aria-hidden");
    round.querySelector("[data-before-flip]")?.setAttribute("hidden", "");
    const rate = round.querySelector("[data-after-flip]");
    rate?.removeAttribute("hidden");
    rate?.querySelector("button[value='1']")?.focus();
    document.dispatchEvent(new CustomEvent("cardflipped"));
  };

  document.addEventListener("click", (e) => {
    if (e.target.closest("[data-flip]")) flip();
  });

  // Read aloud: partials/speak-script, included below (shared with the dictionary).

  // --- Swipe after flipping: right = Gewusst, left = Nochmal ---------------
  // Pointer events cover touch and mouse. The card follows the finger; past
  // the threshold it rates, otherwise it springs back.
  let drag = null;
  document.addEventListener("pointerdown", (e) => {
    const inner = e.target.closest(".flashcard-inner");
    const screen = inner?.closest("[data-card-screen]");
    if (!inner || !screen?.classList.contains("is-flipped")) return;
    drag = { inner, screen, x: e.clientX, y: e.clientY, dx: 0, active: false };
  });
  document.addEventListener("pointermove", (e) => {
    if (!drag) return;
    const dx = e.clientX - drag.x;
    const dy = e.clientY - drag.y;
    // Only a mostly horizontal movement drags the card; the rest is scrolling.
    if (!drag.active && (Math.abs(dx) < 8 || Math.abs(dy) > Math.abs(dx))) return;
    drag.active = true;
    drag.dx = dx;
    drag.inner.classList.add("is-dragging");
    drag.inner.style.transform = `rotateY(180deg) translateX(${-dx}px) rotate(${-dx / 20}deg)`;
  });
  // rate = false for pointercancel (the browser took over, e.g. to scroll).
  const endDrag = (rate) => {
    if (!drag) return;
    const { inner, screen, dx, active } = drag;
    drag = null;
    inner.classList.remove("is-dragging");
    inner.style.transform = "";
    if (rate && active && Math.abs(dx) > Math.min(120, window.innerWidth / 4)) {
      screen.querySelector(`[data-after-flip] button[value='${dx > 0 ? 1 : 0}']`)?.click();
    }
  };
  document.addEventListener("pointerup", () => endDrag(true));
  document.addEventListener("pointercancel", () => endDrag(false));

  document.addEventListener("keydown", (e) => {
    const round = document.querySelector("[data-card-screen]");
    // e.repeat: a held key must not rate several cards.
    if (!round || e.repeat || e.target.closest("input, textarea")) return;
    if (!round.classList.contains("is-flipped") && (e.key === " " || e.key === "Enter")) {
      e.preventDefault();
      flip();
    } else if (round.classList.contains("is-flipped") && (e.key === "ArrowLeft" || e.key === "ArrowRight")) {
      round.querySelector(`[data-after-flip] button[value='${e.key === "ArrowRight" ? 1 : 0}']`)?.click();
    }
  });
</script>
@include('partials.speak-script')
@endsection
