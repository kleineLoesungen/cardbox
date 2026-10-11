{{--
  Die Menuepunkte. Genau hier einen neuen Punkt eintragen — die Komponente
  erscheint in der Kopfzeile (breit) und im Blatt hinter „Mehr" (schmal).

  Das Ausblenden nach Rolle ist reine Anzeige. Die Absicherung ist die
  Routengruppe in routes/web.php.
--}}
@props(['linkClass', 'pending' => 0])

@php($user = auth()->user())

<a href="/dashboard" class="{{ $linkClass }} {{ request()->is('dashboard') ? 'nav-link-active' : '' }}">Heute</a>
<a href="/decks" class="{{ $linkClass }} {{ request()->is('decks*') ? 'nav-link-active' : '' }}">Stapel</a>

@if ($user?->hasRole(\App\Enums\Role::Moderator))
  <a href="/moderation" class="{{ $linkClass }} {{ request()->is('moderation*') ? 'nav-link-active' : '' }}">Moderation</a>
@endif

@if ($user?->isAdmin())
  <a href="/admin/users" class="{{ $linkClass }} {{ request()->is('admin/users*') ? 'nav-link-active' : '' }}">
    Benutzer
    @if ($pending > 0)
      <span class="badge badge-warning ml-1" title="Warten auf Freigabe">{{ $pending }}</span>
    @endif
  </a>
  <a href="/admin/tokens" class="{{ $linkClass }} {{ request()->is('admin/tokens*') ? 'nav-link-active' : '' }}">Tokens</a>
  <a href="/admin/system" class="{{ $linkClass }} {{ request()->is('admin/system*') ? 'nav-link-active' : '' }}">System</a>
@endif
