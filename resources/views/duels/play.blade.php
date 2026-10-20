{{-- A duel in full screen, like a learning round (docs/specs/duell.md). --}}
@extends('layouts.focus')
@section('title', 'Duell')

@section('main')
  @include('duels.partials.play-body')
@endsection
