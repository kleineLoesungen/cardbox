{{--
  Guided tour of a page (docs/specs/gefuehrte-tour.md). Rendered by
  App\View\Components\Tour only when steps are left; Driver.js is loaded
  then and only then.

    <x-tour :pages="['heute']" />

  The start script below:
  * shows only steps whose data-tour target is visible right now; the rest
    waits (after an htmx swap, a flip or opening a sheet it looks again),
  * with a sheet open only steps inside it — Driver.js then moves its layer
    into the <dialog>, which sits above everything else; a sheet already
    open when the page loads gets no tour that time,
  * reports every step of a tour as seen when it closes (also on ✕).
--}}
<div hidden data-tour-steps="{{ json_encode($steps, JSON_UNESCAPED_UNICODE) }}"></div>

@once
  @push('scripts')
    <link rel="stylesheet" href="/css/driver-1.10.2.css">
    <script src="/js/driver-1.10.2.js"></script>
    <script>
      (() => {
        const data = document.querySelector("[data-tour-steps]");
        if (!data || !window.driver?.js?.driver) return;
        let pending = JSON.parse(data.dataset.tourSteps);
        let active = null;
        const token = (() => {
          try { return JSON.parse(document.body.getAttribute("hx-headers"))["X-CSRF-TOKEN"]; } catch (_) { return ""; }
        })();
        const reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        // Fixed texts from App\Support\Tours; Driver.js renders HTML, so escape anyway.
        const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
        const target = (step) => step.target ? document.querySelector(`[data-tour="${CSS.escape(step.target)}"]`) : null;
        const visible = (el) => el !== null && el.getClientRects().length > 0;

        const report = (ids) => fetch("/tours/seen", {
          method: "POST",
          headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": token },
          body: JSON.stringify({ steps: ids }),
          keepalive: true,
        }).catch(() => { /* comes again next time — no message */ });

        const run = (onLoad = false) => {
          if (active || pending.length === 0) return;
          const sheet = document.querySelector("dialog[open]");
          // A sheet open right away (an error to fix, "Karte anlegen"): no tour now.
          if (onLoad && sheet) return;
          // A sheet still sliding in would give wrong positions: wait for it.
          const sliding = sheet?.getAnimations().filter((a) => a.playState === "running") ?? [];
          if (sliding.length > 0) {
            Promise.all(sliding.map((a) => a.finished)).then(() => setTimeout(run, 50), () => {});
            return;
          }
          const steps = pending.filter((step) => {
            if (!step.target) return !sheet;
            const el = target(step);
            return visible(el) && (sheet ? sheet.contains(el) : !el.closest("dialog"));
          });
          if (steps.length === 0) return;
          const ids = steps.map((step) => step.id);
          pending = pending.filter((step) => !ids.includes(step.id));

          active = window.driver.js.driver({
            steps: steps.map((step, i) => ({
              element: step.target ? target(step) : undefined,
              popover: {
                title: esc(step.title),
                description: esc(step.text),
                // Centred on the target keeps the bubble off the screen edges on a phone.
                align: "center",
                // No "Zurück" on the first step: there is nothing to go back to.
                showButtons: i === 0 ? ["next", "close"] : ["next", "previous", "close"],
              },
            })),
            animate: !reduced,
            smoothScroll: !reduced,
            showProgress: steps.length > 1,
            progressText: "@{{current}} von @{{total}}",
            nextBtnText: "Weiter",
            prevBtnText: "Zurück",
            doneBtnText: "Fertig",
            closeBtnLabel: "Tour überspringen",
            popoverClass: "tour-popover",
            stagePadding: 6,
            stageRadius: 12,
            // A tap next to the bubble must not skip the tour by accident; ✕ does.
            overlayClickBehavior: "none",
            onPopoverRender: (popover) => {
              popover.closeButton.innerHTML = "&times;";
            },
            // The open <dialog> sits in the top layer, above anything else:
            // overlay and bubble must move inside it, then be placed again.
            onHighlighted: () => {
              if (!sheet) return;
              const stray = [...document.querySelectorAll(".driver-overlay, .driver-popover")].filter((el) => el.parentElement !== sheet);
              if (stray.length === 0) return;
              stray.forEach((el) => sheet.append(el));
              requestAnimationFrame(() => active?.refresh());
            },
            onDestroyed: () => {
              active = null;
              report(ids);
            },
          });
          active.drive();
        };

        // After loading, after htmx swapped content (dictionary results, next
        // card), after flipping a card and after opening a sheet.
        setTimeout(() => run(true), 400);
        document.body.addEventListener("htmx:afterSettle", () => setTimeout(run, 150));
        document.addEventListener("click", (e) => {
          if (e.target.closest("[data-flip], [data-open-sheet]")) setTimeout(run, 450);
        });
      })();
    </script>
  @endpush
@endonce
