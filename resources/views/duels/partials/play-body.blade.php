{{--
  One duel card, or the answer to it (docs/specs/duell.md). Swapped as a
  whole after each answer and "Weiter"; id="round" lets layouts/focus keep
  the phone's keyboard open between typed answers.

  @param \App\Models\Duel $duel
  @param array|null $card  the card that is up (DuelService::current), null with $result
  @param array|null $result  ['right', 'verdict', 'expected', 'done', 'step'] after an answer
  @param int $cards  number of words of the round
  @param int $offset  position before the round's first word (positions count through all rounds)
  @param string $base  /duels/{id} or /duell/{token}
--}}
@php
    $qTag = \App\Enums\Language::tryFrom($duel->front_language)?->tag();
    $aTag = \App\Enums\Language::tryFrom($duel->back_language)?->tag();
    // Issue #23: always front → back; the labels say which side is asked for.
    $qLabel = \App\Enums\Language::labelFor($duel->front_language);
    $aLabel = \App\Enums\Language::labelFor($duel->back_language);
    $step = $result['step'] ?? $card['position'];
    $shown = $step - $offset;
    $size = mb_strlen($card['question'] ?? '') <= 14 ? 'text-4xl' : 'text-2xl';
@endphp
<div id="round" class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-4"
     style="padding-top: calc(0.75rem + env(safe-area-inset-top, 0px)); padding-bottom: calc(2.5rem + var(--safe-bottom, max(env(safe-area-inset-bottom, 0px), 0.5rem)))">
  <div class="flex items-center gap-3">
    <a href="{{ $base }}" class="btn btn-ghost btn-icon shrink-0" aria-label="Duell verlassen">✕</a>
    <div class="meter flex-1" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $cards }}" aria-valuenow="{{ $shown }}">
      <span style="width: {{ $cards > 0 ? round(100 * ($result ? $shown : $shown - 1) / $cards) : 0 }}%"></span>
    </div>
    <span class="shrink-0 text-sm tabular-nums text-muted">{{ $shown }} / {{ $cards }}</span>
  </div>
  <p class="mt-3 text-center"><span class="badge badge-warning">Duell{{ $duel->rounds > 1 ? ' · Runde '.$duel->round.' von '.$duel->rounds : '' }}</span></p>

  @if ($result)
    {{-- The answer: right or wrong, then on. Enter goes on as well (layouts/focus). --}}
    <div class="my-4 flex flex-1 flex-col items-center justify-center gap-3 text-center" data-result-screen>
      @if ($result['verdict'] === 'right')
        <p class="text-2xl font-semibold text-success" tabindex="-1" autofocus>Richtig!</p>
      @elseif ($result['verdict'] === 'close')
        <p class="text-2xl font-semibold text-warning" tabindex="-1" autofocus>Fast! Zählt als richtig.</p>
        <p class="text-lg">So schreibt man es: <span class="font-semibold" lang="{{ $aTag }}">{{ $result['expected'] }}</span></p>
      @else
        <p class="text-xl font-semibold" tabindex="-1" autofocus>Richtig ist:</p>
        <p class="text-3xl font-semibold" lang="{{ $aTag }}">{{ $result['expected'] }}</p>
      @endif
    </div>
    @if ($result['done'])
      <a href="{{ $base }}" class="btn btn-primary btn-lg btn-block" data-result-next>{{ $duel->rounds > 1 ? 'Zur Rangliste' : 'Ergebnis ansehen' }}</a>
    @else
      <a href="{{ $base }}/play" class="btn btn-primary btn-lg btn-block" data-result-next data-keep-keyboard
         hx-get="{{ $base }}/play" hx-target="#round" hx-swap="outerHTML">Weiter</a>
    @endif
  @else
    {{-- The question. --}}
    <div @class(['flashcard flex flex-col', 'mt-3' => $duel->mode === 'typed', 'my-4 flex-1 justify-center' => $duel->mode !== 'typed'])>
      <div class="flashcard-inner flashcard-compact">
        <div class="flashcard-face">
          <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $qLabel }}</p>
          <p class="flashcard-text {{ $size }}" lang="{{ $qTag }}">{{ $card['question'] }}</p>
          @if ($card['hint'])
            <p class="text-sm text-muted">{{ $card['hint'] }}</p>
          @endif
        </div>
      </div>
    </div>

    @if ($duel->mode === 'choice')
      {{-- One form, four buttons: the tapped one sends its choice; a second tap is dropped. --}}
      <form class="grid gap-3" method="post" action="{{ $base }}/answer"
            hx-post="{{ $base }}/answer" hx-target="#round" hx-swap="outerHTML" hx-disabled-elt="find button" hx-sync="this:drop">
        @csrf
        <input type="hidden" name="step" value="{{ $step }}">
        <p class="text-center text-xs font-medium uppercase tracking-wide text-subtle">{{ $aLabel }}</p>
        @foreach ($card['choices'] as $i => $choice)
          <button type="submit" name="choice" value="{{ $i }}" class="btn btn-secondary btn-lg btn-block" lang="{{ $aTag }}">{{ $choice }}</button>
        @endforeach
      </form>
    @else
      <form class="stack mt-2 gap-2" method="post" action="{{ $base }}/answer" hx-post="{{ $base }}/answer" hx-target="#round" hx-swap="outerHTML"
            hx-disabled-elt="find button" hx-sync="this:drop">
        @csrf
        <input type="hidden" name="step" value="{{ $step }}">
        <input class="input input-lg" type="text" name="typed" maxlength="500" lang="{{ $aTag }}"
               placeholder="Deine Antwort ({{ $aLabel }})" aria-label="Deine Antwort ({{ $aLabel }})"
               autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done" autofocus>
        <button type="submit" class="btn btn-primary btn-lg btn-block">Prüfen</button>
        <button type="submit" name="skip" value="1" class="btn btn-ghost btn-block">Weiß ich nicht</button>
      </form>
    @endif
  @endif
</div>
