{{--
  Start page for guests. Built from the app's own cards: a flash card that
  flips on tap (and shows the next word after turning back), the steps as
  ruled index cards, the box bar with a card travelling to "Kann ich", and
  the features as small cards. Signed-in people never see it
  (PageController::landing redirects to /learn).

  @param bool $firstSetup  no account yet: the only way in is creating the admin
--}}
@extends('layouts.base')
@section('title', 'Vokabeln lernen mit dem Karteikasten')

@php($examples = [
    ['q' => 'Hund', 'qLang' => 'Deutsch', 'qTag' => 'de', 'a' => 'dog', 'aLang' => 'Englisch', 'aTag' => 'en', 'also' => ''],
    ['q' => 'schnell', 'qLang' => 'Deutsch', 'qTag' => 'de', 'a' => 'fast', 'aLang' => 'Englisch', 'aTag' => 'en', 'also' => 'quick'],
    ['q' => 'Schmetterling', 'qLang' => 'Deutsch', 'qTag' => 'de', 'a' => 'papillon', 'aLang' => 'Französisch', 'aTag' => 'fr', 'also' => ''],
    ['q' => '6 · 7', 'qLang' => 'Aufgabe', 'qTag' => '', 'a' => '42', 'aLang' => 'Ergebnis', 'aTag' => '', 'also' => ''],
    ['q' => 'Freund', 'qLang' => 'Deutsch', 'qTag' => 'de', 'a' => 'amigo', 'aLang' => 'Spanisch', 'aTag' => 'es', 'also' => 'compañero'],
])
@php($first = $examples[0])

@section('body')
<div class="flex min-h-dvh flex-col overflow-x-clip">
  <header class="border-b border-border">
    <div class="container-app flex h-14 items-center">
      <span class="flex items-center gap-2 font-semibold tracking-tight">
        <img src="/favicon.svg" alt="" class="size-6">
        {{ $branding->name }}
      </span>
      <div class="ml-auto">
        @unless ($firstSetup)
          <a href="/login" class="btn btn-ghost btn-sm">Anmelden</a>
        @endunless
      </div>
    </div>
  </header>

  <main class="flex-1">
    {{-- Hero: the card is the message --}}
    <section class="container-app mx-auto flex max-w-md flex-col items-center gap-8 pt-10 pb-12 text-center">
      <div>
        <h1 class="text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
          Vokabeln lernen mit dem Karteikasten
        </h1>
        <p class="mt-3 text-balance text-muted">
          Jeden Tag ein paar Minuten. Was du kannst, wandert nach hinten –
          was noch nicht sitzt, kommt öfter dran.
        </p>
      </div>

      <div class="card-stack" data-landing-card data-examples="{{ json_encode($examples) }}">
        <div class="card-stack-layer card-stack-layer-1" aria-hidden="true"></div>
        <div class="card-stack-layer card-stack-layer-2" aria-hidden="true"></div>
        <div class="flashcard">
          <button type="button" class="flashcard-inner" data-landing-flip aria-label="Karte umdrehen">
            <span class="flashcard-face">
              <span class="text-xs font-medium uppercase tracking-wide text-subtle" data-q-lang>{{ $first['qLang'] }}</span>
              <span class="flashcard-text text-4xl" data-q lang="{{ $first['qTag'] }}">{{ $first['q'] }}</span>
            </span>
            <span class="flashcard-face flashcard-back" aria-hidden="true" data-back>
              <span class="text-sm text-muted" data-q-small>{{ $first['q'] }}</span>
              <span class="text-xs font-medium uppercase tracking-wide text-subtle" data-a-lang>{{ $first['aLang'] }}</span>
              <span class="flashcard-text text-4xl" data-a lang="{{ $first['aTag'] }}">{{ $first['a'] }}</span>
              <span class="text-base text-muted" data-also @if ($first['also'] === '') hidden @endif>auch: {{ $first['also'] }}</span>
            </span>
          </button>
        </div>
      </div>
      <p class="text-sm text-subtle" aria-live="polite" data-landing-hint>Tipp auf die Karte.</p>

      <div class="flex w-full max-w-xs flex-col gap-3">
        @if ($firstSetup)
          <a href="/register" class="btn btn-primary btn-lg btn-block">Ersten Administrator anlegen</a>
          <p class="hint">Noch gibt es kein Konto. Das erste wird Administrator.</p>
        @else
          <a href="/register" class="btn btn-primary btn-lg btn-block">Konto anlegen</a>
          <a href="/login" class="btn btn-secondary btn-lg btn-block">Anmelden</a>
          <p class="hint">Neue Konten schaltet ein Administrator frei.</p>
        @endif
      </div>
    </section>

    {{-- How it works: three ruled index cards --}}
    <section class="border-t border-border bg-surface-sunken py-12">
      <div class="container-app mx-auto max-w-md">
        <h2 class="text-xl font-semibold tracking-tight">So geht's</h2>
        <ol class="mt-5 stack">
          <li class="index-card">
            <p class="flex items-center gap-2 font-semibold"><span class="index-card-number">1</span> Karten anlegen</p>
            <p class="ruled mt-2 text-sm leading-6">Vorne das Wort, hinten die Übersetzung. Eine ganze Liste auf einmal einfügen oder Karten von anderen übernehmen.</p>
          </li>
          <li class="index-card">
            <p class="flex items-center gap-2 font-semibold"><span class="index-card-number">2</span> Jeden Tag kurz lernen</p>
            <p class="ruled mt-2 text-sm leading-6">„Los geht's“ zeigt dir genau die Karten, die heute dran sind. Karte ansehen, umdrehen, ehrlich sagen: gewusst oder nochmal.</p>
          </li>
          <li class="index-card">
            <p class="flex items-center gap-2 font-semibold"><span class="index-card-number">3</span> Fach für Fach nach hinten</p>
            <p class="ruled mt-2 text-sm leading-6">Jede gewusste Karte rückt ein Fach weiter und kommt seltener. Am Ende liegt sie bei „Kann ich“.</p>
          </li>
        </ol>
      </div>
    </section>

    {{-- The card box: one card travels to "Kann ich" --}}
    <section class="py-12">
      <div class="container-app mx-auto max-w-md">
        <h2 class="text-xl font-semibold tracking-tight">Der Karteikasten</h2>
        <p class="mt-2 text-sm text-muted">
          Zwischen „Neu“ und „Kann ich“ liegen vier Fächer. Eine Karte kommt nach 1, 2, 4 und 7 Tagen
          wieder. Weißt du sie nicht, fängt sie in Fach 1 neu an – und kommt am Ende der Runde gleich nochmal.
        </p>
        <div class="mt-5 box-journey" role="img" aria-label="Eine Karte wandert von Neu über Fach 1 bis 4 nach Kann ich.">
          @foreach (['Neu', 'Fach 1', 'Fach 2', 'Fach 3', 'Fach 4', 'Kann ich'] as $i => $label)
            <div class="box-journey-slot">
              <div class="box-tile {{ $i === 5 ? 'box-tile-mastered' : '' }}">
                <span class="box-tile-label">{{ $label }}</span>
              </div>
            </div>
          @endforeach
          <span class="box-journey-card" aria-hidden="true"></span>
        </div>
      </div>
    </section>

    {{-- Features as cards --}}
    <section class="border-t border-border bg-surface-sunken py-12">
      <div class="container-app mx-auto max-w-md">
        <h2 class="text-xl font-semibold tracking-tight">Was {{ $branding->name }} noch kann</h2>
        <div class="mt-5 grid grid-cols-1 gap-3 min-[400px]:grid-cols-2">
          <div class="feature-card">
            <p class="feature-card-title">Stapel teilen</p>
            <p class="feature-card-text">Einem Stapel folgen und mitlernen – oder ihn kopieren und selbst ändern.</p>
          </div>
          <div class="feature-card">
            <p class="feature-card-title">Schnell vor der Arbeit</p>
            <p class="feature-card-text">Einen Stapel mit dem Lernplan „Schnell“ starten. Die anderen bleiben, wie sie sind.</p>
          </div>
          <div class="feature-card">
            <p class="feature-card-title">Vorlesen</p>
            <p class="feature-card-text">Wörter anhören – in der richtigen Sprache, mit der Stimme deines Geräts.</p>
          </div>
          <div class="feature-card">
            <p class="feature-card-title">Fehler melden</p>
            <p class="feature-card-text">Stimmt eine Karte nicht? Ein Vorschlag an die Person, der der Stapel gehört.</p>
          </div>
          <div class="feature-card">
            <p class="feature-card-title">Auch Mathe</p>
            <p class="feature-card-text">Statt Sprachen „Aufgabe“ und „Ergebnis“ – fürs Einmaleins und mehr.</p>
          </div>
          <div class="feature-card">
            <p class="feature-card-title">Wie eine App</p>
            <p class="feature-card-text">Auf den Startbildschirm legen und mit einem Tipp loslernen.</p>
          </div>
        </div>
      </div>
    </section>

    {{-- Closing card --}}
    <section class="py-12">
      <div class="container-app mx-auto max-w-md">
        <div class="index-card text-center">
          <p class="text-lg font-semibold">Bereit für die erste Runde?</p>
          <p class="mt-1 text-sm text-muted">Ein paar Minuten am Tag reichen.</p>
          <div class="mx-auto mt-4 max-w-xs">
            @if ($firstSetup)
              <a href="/register" class="btn btn-primary btn-lg btn-block">Ersten Administrator anlegen</a>
            @else
              <a href="/register" class="btn btn-primary btn-lg btn-block">Konto anlegen</a>
            @endif
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="border-t border-border">
    <div class="container-app flex h-14 items-center text-sm text-subtle">{{ $branding->name }}</div>
  </footer>
</div>

<script>
  // Hero card: tap flips it; tapping the back turns it over and shows the
  // next example. Words come from data-examples (rendered by Blade).
  (() => {
    const stack = document.querySelector("[data-landing-card]");
    if (!stack) return;
    const examples = JSON.parse(stack.dataset.examples);
    const button = stack.querySelector("[data-landing-flip]");
    const back = stack.querySelector("[data-back]");
    const hint = document.querySelector("[data-landing-hint]");
    const set = (sel, text, lang) => {
      const el = stack.querySelector(sel);
      el.textContent = text;
      if (lang !== undefined) el.setAttribute("lang", lang);
    };
    let index = 0;

    button.addEventListener("click", () => {
      const flipped = stack.classList.toggle("is-flipped");
      back.toggleAttribute("aria-hidden", !flipped);
      button.setAttribute("aria-label", flipped ? "Nächste Karte" : "Karte umdrehen");
      if (flipped) {
        hint.textContent = "Nochmal tippen für die nächste Karte.";
        return;
      }
      // Change the words while the card is turned away.
      setTimeout(() => {
        index = (index + 1) % examples.length;
        const e = examples[index];
        set("[data-q-lang]", e.qLang);
        set("[data-q]", e.q, e.qTag);
        set("[data-q-small]", e.q);
        set("[data-a-lang]", e.aLang);
        set("[data-a]", e.a, e.aTag);
        const also = stack.querySelector("[data-also]");
        also.textContent = "auch: " + e.also;
        also.hidden = e.also === "";
      }, 200);
    });
  })();
</script>
@endsection
