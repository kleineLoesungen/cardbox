{{--
  One duel (docs/specs/duell.md): code while places are free, the own
  "Los geht's", after a round its ranking and "Runde N starten", at the end
  the overall ranking and the winner. Members see it in the app layout,
  guests (with $guestLink) in the plain one. The changing part is
  duels/partials/live.blade.php; it refreshes itself while the duel runs.

  @param \App\Models\Duel $duel
  @param \App\Models\DuelPlayer|null $me  null: only the result, seen by the code
  @param string $base  /duels/{id} or /duell/{token}
  @param list<array> $standings  DuelService::standings (all rounds)
  @param list<array>|null $roundStandings  ranking of the round that is up, once it is over (several rounds only)
  @param int $cards  number of words of the round that is up
  @param int $free  free places
  @param string|null $code  shown while places are free and while the result is shown
  @param \Illuminate\Support\Carbon|null $resultUntil  until when the result is shown (then deleted)
  @param bool $guest  plain layout for people without account
  @param bool $canManage  creator: may remove players
  @param bool $canStartNextRound  creator, between rounds
  @param int $roundStart  position before the first word of the round that is up
  @param string $creatorName
  @param \Illuminate\Support\Carbon|null $nextRoundUntil  until when the creator may start the next round
  @param string|null $guestLink  the guest's secret link
--}}
@extends($guest ? 'layouts.guest' : 'layouts.app')
@section('title', 'Duell')

@section('main')
<div class="mx-auto max-w-md">
  @unless ($guest)
    <a href="/duels" class="back-link">← Duelle</a>
  @endunless
  <h1 @class(['text-2xl font-semibold tracking-tight break-words', 'mt-1' => ! $guest])>Duell: {{ $duel->deck_name }}</h1>
  @include('duels.partials.live')
</div>

<script>
  // "Einladung teilen": the phone's share sheet (chat apps), else copy the text.
  // Delegated: the button is swapped whenever the duel changes.
  document.addEventListener("click", async (e) => {
    const button = e.target.closest("[data-share-invite]");
    if (!button) return;
    const { shareTitle: title, shareText: text, shareUrl: url } = button.dataset;
    if (navigator.share) {
      try {
        await navigator.share({ title, text, url });
      } catch (_) {
        // Closed without sharing.
      }
      return;
    }
    try {
      await navigator.clipboard.writeText(text + "\n" + url);
      // As a toast (layouts/base): the button itself may be swapped any moment.
      showToast("Einladung kopiert – jetzt im Chat einfügen.", "success");
    } catch (_) {
      showToast("Kopieren ging nicht – bitte den Code abschreiben.");
    }
  });
  // "Link kopieren": the guest's only way back, so make keeping it easy.
  document.addEventListener("click", async (e) => {
    if (!e.target.closest("[data-copy-link]")) return;
    const input = document.querySelector("[data-guest-link]");
    try {
      await navigator.clipboard.writeText(input.value);
      showToast("Link kopiert.", "success");
    } catch (_) {
      input.select();
    }
  });
  // A state already on its way when a confirmation opened would swap the
  // form away under it (removing would do nothing): keep the page as it is.
  document.body.addEventListener("htmx:beforeSwap", (e) => {
    if (e.detail.target.id === "duel-live" && document.querySelector("dialog[open]")) e.detail.shouldSwap = false;
  });
</script>
@endsection
