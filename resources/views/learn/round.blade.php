@extends('layouts.focus')
@section('title', 'Lernen')

@section('main')
  @include('learn.partials.round-body')
  <x-tour :pages="['runde']" />
@endsection
