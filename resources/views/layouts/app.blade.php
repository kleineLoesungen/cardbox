{{--
  Layout fuer den angemeldeten Bereich.

  Zwei Bildschirmgroessen, eine Quelle:

  * Ab `lg` (1024 px) steht die Navigation waagerecht in der Kopfzeile.
    Nicht schon ab `md`: Mit sechs Eintraegen fuer Administratoren passt die
    Kopfzeile bei 768 px nicht mehr — Tablets im Hochformat bekommen deshalb
    die untere Leiste.
  * Darunter sitzt sie als Leiste UNTEN — dort sind beim Halten des Geraets
    die Daumen. Was nicht in die Leiste passt, steckt hinter „Mehr".

  Die Eintraege stehen nur EINMAL im Markup: in components/nav-links.blade.php.
  Die Komponente wird zweimal eingesetzt, fuer die Kopfzeile und fuer das Menue.
  Die untere Leiste zeigt bewusst nur eine Auswahl als Abkuerzung.
--}}
@extends('layouts.base')

@section('body')
@php($user = auth()->user())
<div class="flex min-h-dvh flex-col">

  <header class="sticky top-0 z-30 border-b border-border bg-surface-raised/85 backdrop-blur" style="padding-top: env(safe-area-inset-top, 0px)">
    <div class="container-app flex h-14 items-center gap-2">

      <a href="/learn" class="row mr-2 min-h-12 shrink-0 font-semibold tracking-tight">
        {{-- The app icon (resources/icons/icon.svg, copied by scripts/icons.sh). --}}
        <img src="/favicon.svg" alt="" class="size-7">
        <span>{{ $branding->name }}</span>
      </a>

      {{-- Breite Bildschirme: Navigation direkt in der Kopfzeile. --}}
      <nav class="hidden items-center gap-1 lg:flex" aria-label="Hauptnavigation">
        <x-nav-links link-class="nav-link" :pending="$pendingUserCount" :children="$hasChildren" :section="$navSection" />
      </nav>

      <div class="ml-auto flex items-center gap-1">
        <button type="button" class="btn btn-ghost btn-icon" data-theme-toggle
                aria-label="Hell/Dunkel umschalten" title="Hell/Dunkel umschalten">
          <span aria-hidden="true">◐</span>
        </button>

        @if ($user)
          <div class="ml-1 hidden items-center gap-2 lg:flex">
            <a href="/account"
               class="grid size-8 place-items-center rounded-full bg-brand-subtle text-xs font-semibold text-brand transition-opacity hover:opacity-80"
               title="Mein Konto — {{ $user->name }} ({{ $user->role->label() }})">
              {{ $user->initials() }}
            </a>
            <form method="post" action="/logout">
              @csrf
              <button type="submit" class="btn btn-ghost btn-sm">Abmelden</button>
            </form>
          </div>
        @else
          <a href="/login" class="btn btn-primary btn-sm ml-1 hidden lg:inline-flex">Anmelden</a>
        @endif
      </div>
    </div>
  </header>

  {{-- `pb-24` haelt den Inhalt oberhalb der unteren Leiste. --}}
  <main class="container-app flex-1 pt-8 pb-[calc(6rem+var(--safe-bottom))] lg:pb-8">
    @yield('main')
  </main>

  <footer class="border-t border-border py-6 pb-24 lg:pb-6">
    <div class="container-app flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-subtle">
      <span>{{ $branding->name }}</span>
      {{-- Nur fuer Angemeldete: Ein Veroeffentlichungsdatum verriete sonst jedem,
           welche Korrekturen dieser Installation noch fehlen. --}}
      @auth
        @if ($appVersion)
          <span class="ml-auto">Version {{ $appVersion }}</span>
        @endif
      @endauth
    </div>
  </footer>
</div>

{{-- Shared confirmation sheet, see the script below (Leitlinie 8). --}}
<dialog id="confirm-sheet" class="dialog-sheet" role="alertdialog" aria-labelledby="confirm-sheet-text" aria-describedby="confirm-sheet-text">
  <div class="dialog-sheet-body stack">
    <p id="confirm-sheet-text" class="text-base" data-confirm-text></p>
    <button type="button" class="btn btn-danger btn-lg btn-block" data-confirm-yes>Ja</button>
    <form method="dialog">
      <button type="submit" class="btn btn-ghost btn-lg btn-block" data-confirm-no>Abbrechen</button>
    </form>
  </div>
</dialog>

{{-- --- Untere Leiste, nur auf schmalen Bildschirmen ------------------------ --}}
<nav class="tabbar lg:hidden" aria-label="Hauptnavigation">
  <a href="/learn" class="tab {{ $navSection === 'learn' ? 'tab-active' : '' }}">
    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
    </svg>
    Heute
  </a>

  <a href="/decks" class="tab {{ $navSection === 'decks' ? 'tab-active' : '' }}">
    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="4" y="7" width="14" height="13" rx="2"/><path d="M8 4h10a2 2 0 0 1 2 2v10"/>
    </svg>
    Stapel
  </a>

  <a href="/dictionary" class="tab {{ $navSection === 'dictionary' ? 'tab-active' : '' }}">
    {{-- An open book. --}}
    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M12 6.5C10.5 5 8 4.5 4 4.5v13c4 0 6.5.5 8 2 1.5-1.5 4-2 8-2v-13c-4 0-6.5.5-8 2z"/><path d="M12 6.5v13"/>
    </svg>
    Wörterbuch
  </a>

  <button type="button" @class(['tab relative', 'tab-active' => $navSection !== null && ! in_array($navSection, \App\Support\Navigation::TAB_BAR, true)]) data-menu-toggle
          aria-expanded="false" aria-controls="main-menu" aria-label="Mehr anzeigen">
    @if ($pendingUserCount > 0)
      <span class="tab-badge" aria-label="{{ $pendingUserCount }} warten auf Freigabe">{{ $pendingUserCount }}</span>
    @endif
    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" aria-hidden="true">
      <path d="M5 12h.01M12 12h.01M19 12h.01"/>
    </svg>
    Mehr
  </button>
</nav>

{{--
  Das Menue. Auf- und zugeklappt wird ueber die Tailwind-Klasse `hidden`, nicht
  ueber eigene CSS-Regeln: Utilities stehen in der obersten Kaskadenebene und
  gewinnen damit zuverlaessig gegen alles andere.
--}}
<div id="main-menu" class="hidden lg:hidden">
  <div class="sheet-backdrop" data-menu-backdrop></div>

  <div class="sheet" role="dialog" aria-modal="true" aria-label="Menü">
    <div class="mx-auto mt-2 h-1 w-10 rounded-full bg-border-strong" aria-hidden="true"></div>

    <nav class="container-app flex max-h-[70dvh] flex-col gap-1 overflow-y-auto py-3">
      <x-nav-links link-class="nav-link-mobile" :pending="$pendingUserCount" :children="$hasChildren" :section="$navSection" />

      <div class="my-2 border-t border-border"></div>

      @if ($user)
        <a href="/account" class="nav-link-mobile {{ $navSection === 'account' ? 'nav-link-active' : '' }}">
          Mein Konto
          <span class="mt-0.5 block text-xs font-normal text-subtle">
            {{ $user->name }} · {{ $user->role->label() }}
          </span>
        </a>

        <form method="post" action="/logout">
          @csrf
          <button type="submit" class="nav-link-mobile w-full text-left">Abmelden</button>
        </form>
      @else
        <a href="/login" class="nav-link-mobile">Anmelden</a>
      @endif
    </nav>
  </div>
</div>

<script>
  // --- Farbwahl umschalten und merken -------------------------------------
  document.addEventListener("click", (e) => {
    if (!e.target.closest("[data-theme-toggle]")) return;
    const root = document.documentElement;
    const dark = root.dataset.theme
      ? root.dataset.theme === "dark"
      : window.matchMedia("(prefers-color-scheme: dark)").matches;
    root.dataset.theme = dark ? "light" : "dark";
    try { localStorage.setItem("theme", root.dataset.theme); } catch (_) {}
  });

  // --- Bottom sheets (components/sheet.blade.php) ---------------------------
  // Open: data-open-sheet="id", or htmx swapping content into a closed sheet.
  // Close: ×, Escape, backdrop tap, or the htmx event "sheet-close".
  document.addEventListener("click", (e) => {
    const opener = e.target.closest("[data-open-sheet]");
    if (opener) {
      // One sheet at a time: stacked sheets are confusing on a phone.
      document.querySelectorAll("dialog.dialog-sheet[open]").forEach((d) => d.close());
      document.getElementById(opener.dataset.openSheet)?.showModal();
      return;
    }
    // A click on the <dialog> element itself is a click on its backdrop.
    if (e.target instanceof HTMLDialogElement && e.target.classList.contains("dialog-sheet")) {
      e.target.close();
    }
  });
  document.body.addEventListener("htmx:afterSwap", (e) => {
    const sheet = e.detail.target.closest?.("dialog.dialog-sheet");
    if (sheet && !sheet.open) sheet.showModal();
  });
  document.body.addEventListener("sheet-close", () => {
    document.querySelectorAll("dialog.dialog-sheet[open]").forEach((d) => d.close());
  });
  // --- Confirmation sheet (instead of the browser's confirm()) -----------
  // Plain forms: data-confirm="question", data-confirm-action="Stapel löschen".
  // htmx: hx-confirm="question" plus the same data-confirm-action.
  // The question comes from a data attribute, never from inline script.
  const confirmSheet = document.getElementById("confirm-sheet");
  const askConfirm = (question, action, onYes) => {
    confirmSheet.querySelector("[data-confirm-text]").textContent = question;
    const yes = confirmSheet.querySelector("[data-confirm-yes]");
    yes.textContent = action || "Ja";
    yes.onclick = () => { confirmSheet.close(); onYes(); };
    confirmSheet.showModal();
    confirmSheet.querySelector("[data-confirm-no]").focus();
  };
  document.addEventListener("submit", (e) => {
    const form = e.target.closest("form[data-confirm]");
    if (!form || form.dataset.confirmed === "1") return;
    e.preventDefault();
    const submitter = e.submitter;
    askConfirm(form.dataset.confirm, form.dataset.confirmAction, () => {
      form.dataset.confirmed = "1";
      form.requestSubmit(submitter);
      // The submit handler ran synchronously; ask again next time (bfcache).
      delete form.dataset.confirmed;
    });
  });
  document.body.addEventListener("htmx:confirm", (e) => {
    if (!e.detail.question) return;
    e.preventDefault();
    const source = e.detail.elt.closest("[data-confirm-action]");
    askConfirm(e.detail.question, source?.dataset.confirmAction, () => e.detail.issueRequest(true));
  });
  // "Alle auswählen": a checkbox data-check-all="decks[]" ticks all boxes of that name in its form.
  document.addEventListener("change", (e) => {
    const all = e.target.closest("[data-check-all]");
    if (!all?.form) return;
    all.form.querySelectorAll(`input[type=checkbox][name="${CSS.escape(all.dataset.checkAll)}"]`).forEach((box) => { box.checked = all.checked; });
  });
  // A sheet can ask to be open right away (e.g. "add card" after creating a deck).
  document.querySelectorAll("dialog.dialog-sheet[data-open-on-load]").forEach((d) => d.showModal());
  // Enter in the front field jumps to the back field instead of submitting.
  document.addEventListener("keydown", (e) => {
    const input = e.target.closest?.("[data-enter-next]");
    if (!input || e.key !== "Enter" || e.isComposing) return;
    e.preventDefault();
    const fields = [...input.form.querySelectorAll("input:not([type=hidden])")];
    fields[fields.indexOf(input) + 1]?.focus();
  });
  // Meaning rows of the edit form: the first is the main meaning, so its
  // ☆ is hidden; labels say which one is the main meaning.
  const syncMeaningRows = (group) => {
    group.querySelectorAll("[data-meaning-row]").forEach((row, i) => {
      row.querySelector("[data-make-main]").hidden = i === 0;
      const input = row.querySelector("input");
      input.setAttribute("aria-label", input.dataset.label + (i === 0 ? " (Hauptbedeutung)" : " " + (i + 1)));
    });
  };
  document.querySelectorAll("[data-meaning-row]").forEach((row) => syncMeaningRows(row.parentElement));
  document.body.addEventListener("htmx:afterSettle", (e) => {
    e.target.querySelectorAll?.("[data-meaning-row]").forEach((row) => syncMeaningRows(row.parentElement));
  });

  // "+ weitere Bedeutung": clone the last row (edit form) or input (add form).
  document.addEventListener("click", (e) => {
    const add = e.target.closest("[data-add-meaning]");
    if (!add) return;
    const group = document.getElementById(add.dataset.addMeaning);
    const rows = group?.querySelectorAll(":scope > [data-meaning-row]") ?? [];
    const last = rows.length ? rows[rows.length - 1] : group?.querySelector(":scope > input:last-of-type");
    if (!last) return;
    const copy = last.cloneNode(true);
    const input = copy.matches("input") ? copy : copy.querySelector("input");
    input.value = "";
    input.removeAttribute("id");
    input.removeAttribute("autofocus");
    input.removeAttribute("hx-get");
    group.append(copy);
    if (rows.length) syncMeaningRows(group);
    input.focus();
  });

  // ☆ "Als Hauptbedeutung": move the row to the top. Saving stays one step.
  document.addEventListener("click", (e) => {
    const button = e.target.closest("[data-make-main]");
    if (!button) return;
    const row = button.closest("[data-meaning-row]");
    const group = row.parentElement;
    group.prepend(row);
    syncMeaningRows(group);
    row.querySelector("input").focus();
  });

  // --- Menü der unteren Leiste --------------------------------------------
  (() => {
    const toggle = document.querySelector("[data-menu-toggle]");
    const menu = document.getElementById("main-menu");
    if (!toggle || !menu) return;

    const isOpen = () => !menu.classList.contains("hidden");

    const setOpen = (open) => {
      menu.classList.toggle("hidden", !open);
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "Menü schließen" : "Mehr anzeigen");
      // Hintergrund nicht mitscrollen lassen, solange das Menü offen ist.
      document.body.style.overflow = open ? "hidden" : "";
    };

    toggle.addEventListener("click", () => setOpen(!isOpen()));

    // Tipp auf den abgedunkelten Hintergrund schließt.
    menu.querySelector("[data-menu-backdrop]").addEventListener("click", () => setOpen(false));

    // Mit Escape schließen — erwartetes Verhalten für ein offenes Menü.
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") setOpen(false);
    });

    // Wird das Fenster breit, übernimmt die Kopfzeile wieder. Das Menü muss
    // dann zu sein, sonst steht es beim Zurückdrehen unerwartet offen — und
    // `overflow: hidden` bliebe am Body kleben.
    window.matchMedia("(min-width: 64rem)").addEventListener("change", (e) => {
      if (e.matches) setOpen(false);
    });
  })();
  // iOS 26: after the keyboard closes, the visible part of the page can stay
  // shifted, and fixed bars drift while scrolling (issue #22). A 1 px nudge
  // puts it back; elsewhere offsetTop is 0 and nothing happens.
  document.addEventListener("focusout", () => {
    setTimeout(() => {
      const view = window.visualViewport;
      if (!view || view.offsetTop <= 0 || document.activeElement?.matches?.("input, textarea")) return;
      const y = window.scrollY;
      window.scrollBy({ top: 1, behavior: "instant" });
      window.scrollTo({ top: y, behavior: "instant" });
    }, 150);
  });
</script>
@endsection
