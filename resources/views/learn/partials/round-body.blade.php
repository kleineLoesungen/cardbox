{{--
  One screen of a learning round: the current card, or the end.
  Swapped as a whole by htmx after each answer (target #round).

  @param array      $round     state from StudyRoundService
  @param array|null $current   ['card' => Card, 'direction' => Direction, 'repeat' => bool, 'intro' => bool, 'changed' => bool, …]
  @param int        $position  number of the card that is up
  @param int        $streak    days in a row (end screen only)
  @param list<string> $mastered  words that reached "Kann ich" in this round (end screen only)
  @param array{answers: int, goal: int, goal_met: bool}|null $day  today's daily goal (end screen only)
  @param array|null $checked   result of a typed answer, shown before the next card (StudyRoundService::answerTyped)
  @param string $return  where ✕ and "Fertig" lead: "Heute" or the deck the round was started from
--}}
@php($practice = $round['mode'] === 'practice')
{{-- "Für den Test üben": every card until known once, nothing saved. --}}
@php($test = $round['mode'] === 'test')
<div id="round" class="mx-auto flex min-h-dvh w-full max-w-md flex-col px-4"
     style="padding-top: calc(0.75rem + env(safe-area-inset-top, 0px)); padding-bottom: calc(1rem + var(--safe-bottom))">

@php($checked ??= null)
@if ($checked)
  {{--
    Result of a typed answer (schreiben-und-erinnern.md, A2). Already rated;
    "Weiter" loads the card that is up now. Enter goes on as well (layouts/focus).
  --}}
  @php($card = $checked['card'])
  @php($direction = $checked['direction'])
  @php($question = $direction === \App\Enums\Direction::Forward ? $card->front : $card->back)
  @php($answer = $direction === \App\Enums\Direction::Forward ? $card->back : $card->front)
  @php($qLang = \App\Enums\Language::tryFrom($direction->questionLanguage($card->deck)))
  @php($aLang = \App\Enums\Language::tryFrom($direction->answerLanguage($card->deck)))
  @php($others = array_values(array_filter($answer, fn ($m) => $m !== $checked['meaning'])))
  @php($markedText = implode(', ', array_column(array_filter($checked['parts'], fn ($p) => $p['diff']), 'text')))
  <div class="contents" id="round-step-{{ $round['step'] }}-result" data-result-screen>
    <div class="flex items-center gap-3">
      <a href="{{ $return }}" class="btn btn-ghost btn-icon shrink-0" aria-label="Runde beenden">✕</a>
      <div class="flex-1"></div>
    </div>

    <div class="my-4 flex flex-1 flex-col justify-center stack gap-4 text-center" aria-live="polite">
      <p class="text-lg text-muted" lang="{{ $qLang?->tag() }}">{{ $question[0] ?? '' }}</p>

      {{-- Focused after the swap, so screen readers read the result (and Enter still goes on). --}}
      @if ($checked['verdict'] === 'right')
        <p class="text-2xl font-semibold text-success" tabindex="-1" autofocus>Richtig!</p>
      @elseif ($checked['verdict'] === 'close')
        <p class="text-2xl font-semibold text-warning" tabindex="-1" autofocus>Fast! So schreibt man es:</p>
      @else
        <p class="text-xl font-semibold" tabindex="-1" autofocus>Richtig ist:</p>
      @endif

      {{-- One line on purpose: whitespace between the parts would split the word. --}}
      <p class="text-3xl font-semibold break-words" lang="{{ $aLang?->tag() }}">@foreach ($checked['parts'] as $part)<span @class(['answer-diff' => $part['diff']])>{{ $part['text'] }}</span>@endforeach</p>
      @if ($markedText !== '')
        <p class="sr-only">Anders als bei dir: {{ $markedText }}</p>
      @endif
      @if ($others !== [])
        <p class="text-base text-muted">auch: <span lang="{{ $aLang?->tag() }}">{{ implode(', ', $others) }}</span></p>
      @endif
      @if ($checked['verdict'] !== 'right' && $checked['typed'] !== '')
        <p class="text-sm text-muted">Du hast geschrieben: <span lang="{{ $aLang?->tag() }}" class="font-medium text-content">{{ $checked['typed'] }}</span></p>
      @endif
      @if ($checked['verdict'] === 'close' && ! $checked['known'])
        <p class="text-sm text-muted">Im Test zählt die Rechtschreibung – das Wort kommt noch einmal.</p>
      @endif

      <div class="flex min-h-11 justify-center">
        <button type="button" class="btn btn-ghost" data-speak hidden aria-label="{{ $checked['expected'] }} vorlesen"
                data-speak-text="{{ $checked['expected'] }}" data-speak-lang="{{ $aLang?->tag() }}">
          <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>
          </svg>
          Vorlesen
        </button>
      </div>
    </div>

    {{--
      htmx keeps the page (and with it the iPhone's keyboard, see layouts/focus);
      the href still works without JavaScript.
    --}}
    <a href="/learn/round" class="btn btn-primary btn-lg btn-block" data-result-next data-keep-keyboard
       hx-get="/learn/round" hx-target="#round" hx-swap="outerHTML">Weiter</a>
  </div>

@elseif ($current && $current['intro'])
  {{--
    A new word, shown open before it is asked (docs/specs/motivierender-start.md, A).
    No [data-card-screen]: nothing to flip, swipe or rate; "Weiter" is no answer.
  --}}
  @php($card = $current['card'])
  @php($deck = $card->deck)
  @php($qLang = \App\Enums\Language::tryFrom($deck->front_language))
  @php($aLang = \App\Enums\Language::tryFrom($deck->back_language))
  <div class="contents" id="round-step-{{ $round['step'] }}" data-intro-screen>
    <div class="flex items-center gap-3">
      <a href="{{ $return }}" class="btn btn-ghost btn-icon shrink-0" aria-label="Runde beenden">✕</a>
      <div class="meter flex-1" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $round['total'] }}" aria-valuenow="{{ $position - 1 }}">
        <span style="width: {{ $round['total'] > 0 ? round(100 * ($position - 1) / $round['total']) : 0 }}%"></span>
      </div>
      <span class="shrink-0 text-sm tabular-nums text-muted">{{ $position }} / {{ $round['total'] }}</span>
    </div>

    <div class="mt-3 flex min-h-6 justify-center">
      <span class="badge badge-brand">Neues Wort</span>
    </div>

    <div class="my-4 flex flex-1 flex-col justify-center">
      <div class="card">
        <div class="card-body stack gap-4 text-center">
          @foreach ([[$qLang, $card->front, true], [$aLang, $card->back, false]] as [$lang, $side, $isQuestion])
            <div class="stack gap-1">
              <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $lang?->label() }}</p>
              <p class="text-3xl font-semibold break-words" lang="{{ $lang?->tag() }}">{{ $side[0] ?? '' }}</p>
              @if ($isQuestion && $card->hint)
                <p class="text-sm text-muted">{{ $card->hint }}</p>
              @endif
              @if (count($side) > 1)
                <p class="text-base text-muted">auch: <span lang="{{ $lang?->tag() }}">{{ implode(', ', \App\Models\Card::alternatives($side)) }}</span></p>
              @endif
              <div class="flex min-h-11 justify-center">
                <button type="button" class="btn btn-ghost" data-speak hidden aria-label="{{ $side[0] ?? '' }} vorlesen"
                        data-speak-text="{{ $side[0] ?? '' }}" data-speak-lang="{{ $lang?->tag() }}">
                  <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>
                  </svg>
                  Vorlesen
                </button>
                @if ($isQuestion)
                  <a href="/help#vorlesen" class="self-center text-sm text-subtle hover:text-content" data-speak-missing hidden>
                    Vorlesen fehlt? So geht's
                  </a>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </div>

    <form hx-post="/learn/round/answer" hx-target="#round" hx-swap="outerHTML" hx-disabled-elt="find button" class="stack gap-2">
      <input type="hidden" name="card" value="{{ $card->id }}">
      <input type="hidden" name="step" value="{{ $round['step'] }}">
      <p class="hint text-center">Merk es dir – gleich fragt die App danach.</p>
      <button type="submit" class="btn btn-primary btn-lg btn-block" data-intro-next data-keep-keyboard>Weiter</button>
    </form>
  </div>

@elseif ($current)
  @php($card = $current['card'])
  @php($deck = $card->deck)
  @php($direction = $current['direction'])
  @php($question = $direction === \App\Enums\Direction::Forward ? $card->front : $card->back)
  @php($answer = $direction === \App\Enums\Direction::Forward ? $card->back : $card->front)
  @php($qLang = \App\Enums\Language::tryFrom($direction->questionLanguage($deck)))
  @php($aLang = \App\Enums\Language::tryFrom($direction->answerLanguage($deck)))
  @php($size = mb_strlen($question[0] ?? '') <= 14 ? 'text-4xl' : (mb_strlen($question[0] ?? '') <= 40 ? 'text-2xl' : 'text-lg'))

  {{--
    The flipped state sits on this element, not on #round. Its id changes
    with every step, so htmx does not carry the old "is-flipped" class over
    to the next card while settling — otherwise the new card would start
    flipped and turn back, showing its answer for a moment.
  --}}
  {{-- Typed answers (schreiben-und-erinnern.md, A) have nothing to flip: no data-card-screen. --}}
  <div class="contents" id="round-step-{{ $round['step'] }}" @unless ($current['typed']) data-card-screen @endunless>

  {{-- Top: leave, progress --}}
  <div class="flex items-center gap-3">
    <a href="{{ $return }}" class="btn btn-ghost btn-icon shrink-0" aria-label="Runde beenden">✕</a>
    {{-- In a test round progress counts the cards known, as repeats never end there. --}}
    @php($done = $test ? $round['known'] : $position - 1)
    <div class="meter flex-1" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $round['total'] }}" aria-valuenow="{{ $test ? $done : $position }}">
      <span style="width: {{ $round['total'] > 0 ? round(100 * $done / $round['total']) : 0 }}%"></span>
    </div>
    <span class="shrink-0 text-sm tabular-nums text-muted">{{ $test ? $done : $position }} / {{ $round['total'] }}</span>
  </div>

  <div class="mt-3 flex min-h-6 flex-wrap justify-center gap-2">
    @if ($practice)
      <span class="badge badge-warning">Freies Üben – nur Übung</span>
    @elseif ($test)
      <span class="badge badge-warning">Test üben – nur Übung</span>
    @endif
    @if ($current['repeat'])
      <span class="badge badge-neutral">Wiederholung</span>
    @endif
    @if ($current['changed'])
      {{-- Edited by the owner since this person last answered it; progress stays. --}}
      <span class="badge badge-brand">geändert</span>
    @endif
  </div>

  {{-- The card: tap to flip (typed: only the question, the answer is not in the page) --}}
  {{--
    Typed: a compact card at the top with the field right under it, so both
    stay visible above the phone's keyboard.
  --}}
  <div @class(['flashcard flex flex-col', 'my-4 flex-1 justify-center' => ! $current['typed'], 'mt-3' => $current['typed']])>
    <div @class(['flashcard-inner', 'cursor-pointer' => ! $current['typed'], 'flashcard-compact' => $current['typed']])
         @unless ($current['typed']) data-flip role="button" tabindex="0" aria-label="Karte umdrehen" @endunless>
      <div class="flashcard-face">
        <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $qLang?->label() }}</p>
        <p class="flashcard-text {{ $size }}" lang="{{ $qLang?->tag() }}">{{ $question[0] ?? '' }}</p>
        @if ($card->hint)
          <p class="text-sm text-muted">{{ $card->hint }}</p>
        @endif
      </div>
      {{-- Hidden from screen readers until flipped (flip() removes aria-hidden). --}}
      @unless ($current['typed'])
      <div class="flashcard-face flashcard-back" aria-hidden="true" data-back>
        <p class="text-sm text-muted" lang="{{ $qLang?->tag() }}">{{ $question[0] ?? '' }}@if ($card->hint) {{ $card->hint }}@endif</p>
        <p class="text-xs font-medium uppercase tracking-wide text-subtle">{{ $aLang?->label() }}</p>
        {{-- Main meaning large, alternatives small as "auch: …". --}}
        <p class="flashcard-text {{ $size }}" lang="{{ $aLang?->tag() }}">{{ $answer[0] ?? '' }}</p>
        @if (count($answer) > 1)
          <p class="text-base text-muted">auch: <span lang="{{ $aLang?->tag() }}">{{ implode(', ', \App\Models\Card::alternatives($answer)) }}</span></p>
        @endif
      </div>
      @endunless
    </div>

    {{--
      Read aloud: the question before flipping, the answer after — main
      meanings only, alternatives would sound like one long word. Hidden
      until the script finds a voice for the language; never shown for
      "Aufgabe"/"Ergebnis", which have no language tag.
    --}}
    {{-- min-h-11 also when typing: a voice found late must not push the field under the keyboard. --}}
    <div @class(['flex min-h-11 justify-center', 'mt-3' => ! $current['typed'], 'mt-1' => $current['typed']])>
      <button type="button" class="btn btn-ghost" data-speak hidden
              data-question="{{ $question[0] ?? '' }}" data-question-lang="{{ $qLang?->tag() }}"
              @unless ($current['typed']) data-answer="{{ $answer[0] ?? '' }}" data-answer-lang="{{ $aLang?->tag() }}" @endunless>
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>
        </svg>
        Vorlesen
      </button>
      {{-- Shown instead when the device could speak but has no voice for this language. --}}
      <a href="/help#vorlesen" class="self-center text-sm text-subtle hover:text-content" data-speak-missing hidden>
        Vorlesen fehlt? So geht's
      </a>
    </div>
  </div>

  @if ($current['typed'])
    {{-- Type the answer; the server checks it (AnswerCheckService). --}}
    <form class="stack mt-2 gap-2" hx-post="/learn/round/answer" hx-target="#round" hx-swap="outerHTML"
          hx-disabled-elt="find button" hx-sync="this:drop">
      <input type="hidden" name="card" value="{{ $card->id }}">
      <input type="hidden" name="step" value="{{ $round['step'] }}">
      <input class="input input-lg" type="text" name="typed" maxlength="500" lang="{{ $aLang?->tag() }}"
             placeholder="Deine Antwort" aria-label="Deine Antwort ({{ $aLang?->label() }})"
             autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="done" autofocus>
      <button type="submit" class="btn btn-primary btn-lg btn-block">Prüfen</button>
      <button type="submit" name="skip" value="1" class="btn btn-ghost btn-block">Weiß ich nicht</button>
    </form>
  @else
  {{-- Under the thumbs: flip first, then rate --}}
  <div data-before-flip>
    <button type="button" class="btn btn-primary btn-lg btn-block" data-flip>Umdrehen</button>
  </div>

  <form data-after-flip hidden class="grid grid-cols-2 gap-3"
        hx-post="/learn/round/answer" hx-target="#round" hx-swap="outerHTML" hx-disabled-elt="find button">
    <input type="hidden" name="card" value="{{ $card->id }}">
    <input type="hidden" name="step" value="{{ $round['step'] }}">
    <button type="submit" name="known" value="0" class="btn btn-secondary btn-lg">Nochmal</button>
    <button type="submit" name="known" value="1" class="btn btn-primary btn-lg">Gewusst</button>
  </form>
  @endif
  </div>

@else
  {{-- End of the round --}}
  <div class="flex flex-1 flex-col items-center justify-center gap-4 text-center">
    @if ($round['answered'] === 0)
      <h1 class="text-2xl font-semibold">Gerade ist nichts zu lernen</h1>
      <p class="text-muted">Alle Karten sind erledigt oder noch nicht dran.</p>
    @elseif ($test)
      {{-- total counts questions (a card twice with back → front), so no number of words here. --}}
      <h1 class="text-3xl font-semibold">Alles gewusst!</h1>
      <p class="text-lg"><strong>{{ $round['first'] }} von {{ $round['total'] }}</strong> gleich beim ersten Mal.</p>
      <p class="text-sm text-muted">Test üben zählt nicht für den Karteikasten.</p>
    @else
      <h1 class="text-3xl font-semibold">Geschafft!</h1>
      <p class="text-lg">
        <strong>{{ $round['known'] }} von {{ $round['answered'] }}</strong> gewusst
      </p>
      @if (! $practice && $round['promoted'] > 0)
        <p class="text-muted">
          {{ $round['promoted'] === 1 ? '1 Karte ist' : $round['promoted'].' Karten sind' }} ein Fach weiter.
        </p>
      @endif
      @if (($hard ?? []) !== [])
        {{-- Often wrong (runden-und-knifflige-woerter.md, B): a nudge towards a mnemonic. --}}
        <div class="stack gap-1">
          <p class="text-base"><span class="font-semibold text-warning">Knifflig:</span> {{ implode(', ', array_slice($hard, 0, 5)) }}@if (count($hard) > 5) und {{ count($hard) - 5 }} weitere @endif</p>
          <p class="text-sm text-muted">Denk dir eine Eselsbrücke aus – zum Beispiel ein Bild im Kopf oder einen Reim.</p>
        </div>
      @endif
      @if ($mastered !== [])
        {{-- The growing vocabulary (docs/specs/motivierender-start.md, C). --}}
        <p class="text-base">
          <span class="font-semibold text-success">Neu in „Kann ich“:</span>
          {{ implode(', ', array_slice($mastered, 0, 8)) }}@if (count($mastered) > 8) und {{ count($mastered) - 8 }} weitere @endif
        </p>
      @endif
      @if ($practice)
        <p class="text-sm text-muted">Freies Üben zählt nicht für den Karteikasten.</p>
      @elseif ($streak > 0)
        <p class="badge badge-brand text-sm">{{ $streak === 1 ? '1 Tag' : $streak.' Tage' }} in Folge gelernt</p>
      @endif
    @endif
    @if ($round['answered'] > 0 && $day && $day['answers'] >= $day['goal'] && ! ($round['goal_before'] ?? false))
      <p class="text-lg font-semibold text-success">Tagesziel geschafft!</p>
    @endif
  </div>

  <div class="stack">
    @if ($test && $round['answered'] > 0)
      <form method="post" action="/learn/round">
        @csrf
        <input type="hidden" name="mode" value="test">
        <input type="hidden" name="deck" value="{{ $round['deck'] }}">
        @if (($round['only'] ?? null) === 'hard')
          <input type="hidden" name="only" value="hard">
        @endif
        <button type="submit" class="btn btn-secondary btn-lg btn-block">Nochmal üben</button>
      </form>
    @endif
    {{-- Back to where the round was started: "Heute" or the deck (docs/specs/navigation.md). --}}
    <a href="{{ $return }}" class="btn btn-primary btn-lg btn-block">Fertig</a>
  </div>
@endif
</div>
