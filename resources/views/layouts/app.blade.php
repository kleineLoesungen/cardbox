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

  <header class="sticky top-0 z-30 border-b border-border bg-surface-raised/85 backdrop-blur">
    <div class="container-app flex h-14 items-center gap-2">

      <a href="/dashboard" class="row mr-2 shrink-0 font-semibold tracking-tight">
        <span class="grid size-7 place-items-center rounded-ui bg-brand text-brand-content text-xs font-bold">
          {{ $branding->initial() }}
        </span>
        <span>{{ $branding->name }}</span>
      </a>

      {{-- Breite Bildschirme: Navigation direkt in der Kopfzeile. --}}
      <nav class="hidden items-center gap-1 lg:flex" aria-label="Hauptnavigation">
        <x-nav-links link-class="nav-link" />
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
  <main class="container-app flex-1 pt-8 pb-24 lg:pb-8">
    @yield('main')
  </main>

  <footer class="border-t border-border py-6 pb-24 lg:pb-6">
    <div class="container-app flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-subtle">
      <span>{{ $branding->name }}</span>
      <a href="/styleguide" class="hover:text-content">Styleguide</a>
      <a href="/api/v1/health" class="hover:text-content">API-Status</a>
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

{{-- --- Untere Leiste, nur auf schmalen Bildschirmen ------------------------ --}}
<nav class="tabbar lg:hidden" aria-label="Hauptnavigation">
  <a href="/dashboard" class="tab {{ request()->is('dashboard') ? 'tab-active' : '' }}">
    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/>
    </svg>
    Übersicht
  </a>

  <a href="/notes" class="tab {{ request()->is('notes*') ? 'tab-active' : '' }}">
    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5"/><path d="M10 13h6M10 17h4"/>
    </svg>
    Notizen
  </a>

  @if ($user?->isAdmin())
    <a href="/admin/users" class="tab {{ request()->is('admin/users*') ? 'tab-active' : '' }}">
      <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="8" r="3.2"/><path d="M5.5 20v-1a6.5 6.5 0 0 1 13 0v1"/>
      </svg>
      Benutzer
    </a>
  @endif

  <button type="button" class="tab" data-menu-toggle
          aria-expanded="false" aria-controls="main-menu" aria-label="Mehr anzeigen">
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
      <x-nav-links link-class="nav-link-mobile" />

      <div class="my-2 border-t border-border"></div>

      @if ($user)
        <a href="/account" class="nav-link-mobile {{ request()->is('account') ? 'nav-link-active' : '' }}">
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
</script>
@endsection
