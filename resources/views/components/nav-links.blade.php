{{--
  Die Menuepunkte. Genau hier einen neuen Punkt eintragen — die Komponente
  erscheint in der Kopfzeile (breit) und im Blatt hinter „Mehr" (schmal).

  Das Ausblenden nach Rolle ist reine Anzeige. Die Absicherung ist die
  Routengruppe in routes/web.php. Markiert wird der Bereich, aus dem man
  kommt (App\Support\Navigation, docs/specs/navigation.md).
--}}
@props(['linkClass', 'pending' => 0, 'children' => false, 'section' => null])

@php($user = auth()->user())

<a href="/learn" class="{{ $linkClass }} {{ $section === 'learn' ? 'nav-link-active' : '' }}">Heute</a>
<a href="/decks" class="{{ $linkClass }} {{ $section === 'decks' ? 'nav-link-active' : '' }}">Stapel</a>
<a href="/dictionary" class="{{ $linkClass }} {{ $section === 'dictionary' ? 'nav-link-active' : '' }}">Wörterbuch</a>
<a href="/duels" class="{{ $linkClass }} {{ $section === 'duels' ? 'nav-link-active' : '' }}">Duelle</a>
<a href="/help" class="{{ $linkClass }} {{ $section === 'help' ? 'nav-link-active' : '' }}">Hilfe</a>

@if ($children)
  <a href="/children" class="{{ $linkClass }} {{ $section === 'children' ? 'nav-link-active' : '' }}">Meine Kinder</a>
@endif

@if ($user?->hasRole(\App\Enums\Role::Moderator))
  <a href="/moderation" class="{{ $linkClass }} {{ $section === 'moderation' ? 'nav-link-active' : '' }}">Moderation</a>
@endif

@if ($user?->isAdmin())
  <a href="/admin/users" class="{{ $linkClass }} {{ $section === 'admin/users' ? 'nav-link-active' : '' }}">
    Benutzer
    @if ($pending > 0)
      <span class="badge badge-warning ml-1" title="Warten auf Freigabe">{{ $pending }}</span>
    @endif
  </a>
  <a href="/admin/tokens" class="{{ $linkClass }} {{ $section === 'admin/tokens' ? 'nav-link-active' : '' }}">Tokens</a>
  <a href="/admin/system" class="{{ $linkClass }} {{ $section === 'admin/system' ? 'nav-link-active' : '' }}">System</a>
@endif
