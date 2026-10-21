{{--
  Joining a duel without an account (docs/specs/duell.md): code and name.
  Duels joined earlier in this browser are listed.

  @param list<array{token: string, duel: \App\Models\Duel}> $links
--}}
@extends('layouts.guest')
@section('title', 'Duell')

@section('main')
<div class="mx-auto max-w-sm">
  <h1 class="text-2xl font-semibold tracking-tight">Duell</h1>
  <p class="mt-1 text-sm text-muted">Du hast einen Code bekommen? Dann bist du herausgefordert: Alle bekommen dieselben Wörter – wer mehr weiß, gewinnt.</p>

  <form method="post" action="/duell" class="card mt-6">
    @csrf
    <div class="card-body stack">
      <x-form-error />
      <div class="field">
        <label class="label" for="guest-code">Code</label>
        <input class="input input-lg font-mono uppercase tracking-widest" type="text" id="guest-code" name="code"
               value="{{ old('code') }}" required maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false"
               placeholder="XXXX-XXXX">
      </div>
      <div class="field">
        <label class="label" for="guest-name">Dein Name</label>
        <input class="input input-lg" type="text" id="guest-name" name="name" value="{{ old('name') }}"
               maxlength="20" autocomplete="given-name" placeholder="z. B. Mia">
        <p class="hint">So sehen dich die anderen im Duell. Ist das Duell schon vorbei, zeigt der Code nur das Ergebnis – dann brauchst du keinen Namen.</p>
      </div>
      <button type="submit" class="btn btn-primary btn-lg btn-block">Mitmachen</button>
    </div>
  </form>

  @if ($links !== [])
    <h2 class="mt-6 mb-2 text-base font-semibold">Deine Duelle in diesem Browser</h2>
    <ul class="list">
      @foreach ($links as $link)
        <li>
          <a href="/duell/{{ $link['token'] }}" class="list-row">
            <span class="min-w-0 flex-1 truncate font-medium">{{ $link['duel']->deck_name }}</span>
            <span class="badge {{ $link['duel']->isFinished() ? 'badge-neutral' : 'badge-brand' }} shrink-0">{{ $link['duel']->isFinished() ? 'beendet' : 'läuft' }}</span>
            <span aria-hidden="true" class="text-subtle">›</span>
          </a>
        </li>
      @endforeach
    </ul>
  @endif

  {{-- Through /duels: after signing in, Laravel leads there, and the code of an invitation is filled in. --}}
  <p class="mt-6 text-center text-sm text-muted">Du hast ein Konto? <a href="/duels" class="text-brand hover:underline">Anmelden</a></p>
</div>
@include('duels.partials.code-from-link', ['field' => 'guest-code', 'keep' => true])
@endsection
