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

{{--
  Keeps the iPhone's keyboard open between typed answers: iOS only opens it
  for a focus inside a tap, and the next card arrives later. So the tap on
  "Weiter" focuses this field at once, and the new card's field takes over.
  16px, or iOS would zoom.
--}}
<input id="keyboard-keeper" type="text" tabindex="-1" aria-hidden="true" autocomplete="off"
       class="pointer-events-none fixed top-0 left-0 h-px w-px text-base opacity-0">

<script>
  // --- Keyboard on phones between typed answers (see #keyboard-keeper) -----
  const keeper = document.getElementById("keyboard-keeper");
  document.addEventListener("click", (e) => {
    if (e.target.closest("[data-keep-keyboard]")) keeper.focus({ preventScroll: true });
  }, true);
  document.body.addEventListener("htmx:afterSettle", () => {
    if (document.activeElement !== keeper) return;
    const field = document.querySelector("#round input[name=typed]");
    field ? field.focus({ preventScroll: true }) : keeper.blur();
  });
  // When the keyboard opens, phones scroll the field into view and can push
  // the card out at the top. If card and field fit above the keyboard,
  // scroll back to the top so the word stays readable.
  window.visualViewport?.addEventListener("resize", () => {
    const field = document.querySelector("#round input[name=typed]");
    // Pinch zoom resizes the visual viewport too; leave that alone.
    if (!field || document.activeElement !== field || window.visualViewport.scale !== 1) return;
    const bottom = field.getBoundingClientRect().bottom + window.scrollY;
    if (bottom + 8 <= window.visualViewport.height) window.scrollTo(0, 0);
  });
  // An answer that is no round (session expired → login page): go there instead of swapping it in.
  document.body.addEventListener("htmx:beforeSwap", (e) => {
    const url = e.detail.xhr.responseURL;
    if (url && !new URL(url).pathname.startsWith("/learn/round")) {
      e.detail.shouldSwap = false;
      window.location.href = url;
    }
  });
</script>

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
    // Result of a typed answer: Enter goes on — not the key press that sent the answer.
    const result = document.querySelector("[data-result-screen]");
    if (result && !e.repeat && e.key === "Enter" && !e.target.closest("a, button, input, textarea")) {
      e.preventDefault();
      result.querySelector("[data-result-next]")?.click();
      return;
    }
    // An intro of a new word: Enter or space goes on — once per key press.
    const intro = document.querySelector("[data-intro-screen]");
    if (intro && !e.repeat && (e.key === " " || e.key === "Enter") && !e.target.closest("a, button, input, textarea")) {
      e.preventDefault();
      intro.querySelector("[data-intro-next]")?.click();
      return;
    }
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
