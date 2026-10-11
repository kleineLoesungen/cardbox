@extends('layouts.app')
@section('title', 'Moderation')

@section('main')
<div class="mb-6">
  <h1 class="text-2xl font-semibold tracking-tight">Moderation</h1>
  <p class="mt-1 text-sm text-muted">Sichtbar ab der Rolle Moderator.</p>
</div>

<div class="alert alert-info">
  <div>
    <p class="font-medium">Platzhalter</p>
    <p class="mt-1">
      Diese Seite zeigt nur, wie eine rollengeschützte Route aussieht:
      Sie steht in <code class="code">routes/web.php</code> in der Gruppe
      <code class="code">role:moderator</code> — mehr ist nicht nötig.
    </p>
  </div>
</div>
@endsection
