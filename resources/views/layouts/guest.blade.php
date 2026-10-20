{{--
  For people without an account (a guest's duel, docs/specs/duell.md): the
  name of the app on top, no navigation, the page in the middle.
--}}
@extends('layouts.base')

@section('body')
<div class="flex min-h-dvh flex-col">
  <header class="border-b border-border" style="padding-top: env(safe-area-inset-top, 0px)">
    <div class="container-app flex h-14 items-center">
      <span class="flex items-center gap-2 font-semibold tracking-tight">
        <img src="/favicon.svg" alt="" class="size-6">
        {{ $branding->name }}
      </span>
    </div>
  </header>
  <main class="container-app flex-1 pt-8 pb-[calc(2rem+var(--safe-bottom))]">
    @yield('main')
  </main>
</div>
@endsection
