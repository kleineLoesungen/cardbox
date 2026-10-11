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
  };

  document.addEventListener("click", (e) => {
    if (e.target.closest("[data-flip]")) flip();
  });

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
@endsection
