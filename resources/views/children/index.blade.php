{{-- "Meine Kinder" with more than one child (docs/specs/eltern-ansicht.md, B). --}}
@extends('layouts.app')
@section('title', 'Meine Kinder')

@section('main')
<div class="mx-auto max-w-md">
  <h1 class="text-2xl font-semibold tracking-tight">Meine Kinder</h1>

  @if ($children->isEmpty())
    <div class="empty mt-6">
      <p class="empty-title">Noch keine Kinder in deiner Liste</p>
      <p class="empty-hint">Das Kind erzeugt in seinem Konto unter „Eltern“ einen Code. Den gibst du unter „Mein Konto“ ein.</p>
      <a href="/account#kinder" class="btn btn-primary btn-lg">Kind hinzufügen</a>
    </div>
  @else
    <ul class="list mt-6">
      @foreach ($children as $child)
        <li>
          <a href="/children/{{ $child->id }}" class="list-row">
            <span class="min-w-0 flex-1 truncate font-medium">{{ $child->name }}</span>
            <span aria-hidden="true" class="text-subtle">›</span>
          </a>
        </li>
      @endforeach
    </ul>
    <p class="mt-4 text-center text-sm"><a href="/account#kinder" class="text-brand hover:underline">Kind hinzufügen oder entfernen</a></p>
  @endif
</div>
@endsection
