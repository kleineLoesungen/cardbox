@extends('layouts.app')
@section('title', 'App-Tokens')

@section('main')
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">App-Tokens</h1>
  <p class="mt-1 max-w-3xl text-sm text-muted">
    Zugänge für <strong>fremde Systeme</strong> zur dokumentierten JSON-Schnittstelle
    unter <code class="code">/api/v1</code>. Ein Token gehört keinem Benutzer und
    erbt keine Rolle — was es darf, steht ausschließlich in seinen Berechtigungen.
  </p>
</div>

{{-- Frisch erzeugtes Token — einmalig sichtbar. --}}
<div id="new-token"></div>

{{--
  `min-w-0` an den Spalten ist noetig, nicht kosmetisch: Ein Grid-Element hat
  standardmaessig `min-width: auto` und schrumpft damit nie unter die
  Mindestbreite seines Inhalts. Der lange curl-Befehl weiter unten wuerde die
  Spalte sonst auf schmalen Bildschirmen ueber den Rand druecken.
--}}
<div class="grid gap-6 xl:grid-cols-[24rem_1fr]">

  <div class="card h-fit">
    <div class="card-header"><h2 class="card-title">Token erstellen</h2></div>
    <form class="card-body stack"
          hx-post="/admin/tokens"
          hx-target="#new-token"
          hx-swap="innerHTML"
          hx-on::after-request="if (event.detail.successful) this.reset()">

      <div class="field">
        <label class="label" for="token-name">Name</label>
        <input class="input" type="text" id="token-name" name="name"
               placeholder="z. B. Rechnungssystem" required>
        <p class="hint">Welches System benutzt dieses Token?</p>
      </div>

      <fieldset class="field">
        <legend class="label mb-1">Berechtigungen</legend>
        <div class="stack gap-2">
          @foreach ($scopes as $scope)
            <label class="row items-start gap-2">
              {{-- name="scopes[]" — OHNE die Klammern kommt nur der letzte Haken an. --}}
              <input type="checkbox" class="checkbox mt-0.5" name="scopes[]" value="{{ $scope->value }}">
              <span>
                <span class="text-sm text-content">{{ $scope->label() }}</span>
                <span class="block text-xs text-muted">{{ $scope->description() }}</span>
                <code class="code mt-1 inline-block">{{ $scope->value }}</code>
              </span>
            </label>
          @endforeach
        </div>
        <p class="hint mt-2">
          Nur vergeben, was das System wirklich braucht. Ohne Berechtigung kann
          ein Token nichts abrufen.
        </p>
      </fieldset>

      <div class="field">
        <label class="label" for="expires_in_days">Gültigkeit</label>
        <select class="select" id="expires_in_days" name="expires_in_days">
          <option value="30">30 Tage</option>
          <option value="90" selected>90 Tage</option>
          <option value="365">1 Jahr</option>
          <option value="">Unbegrenzt</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        <span class="htmx-indicator spinner"></span>
        Token erstellen
      </button>
    </form>
  </div>

  <div class="min-w-0">
    <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th><th>Kennung</th><th>Berechtigungen</th>
            <th>Status</th><th>Zuletzt benutzt</th><th></th>
          </tr>
        </thead>
        <tbody id="token-rows">
          @forelse ($tokens as $token)
            <x-token-row :token="$token" />
          @empty
            <tr data-empty>
              <td colspan="6">
                <div class="py-6 text-center">
                  <p class="text-sm font-medium text-content">Noch keine Tokens</p>
                  <p class="mt-1 text-sm text-muted">Erstell mit dem Formular eines, damit ein anderes System die API benutzen kann.</p>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card mt-6">
      <div class="card-header"><h2 class="card-title">Benutzung</h2></div>
      <div class="card-body">
        <pre class="code-block">curl {{ config('app.url') }}/api/v1/token \
  -H "Authorization: Bearer sk_..."</pre>
        <p class="mt-3 text-sm text-muted">
          Der Endpunkt <code class="code">/api/v1/token</code> gibt Name und
          Berechtigungen zurück — gut geeignet, um eine Einrichtung zu prüfen.
          Alle Endpunkte stehen in <code class="code">docs/API.md</code>.
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
