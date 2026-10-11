{{-- Details stehen nur im Log, nie auf der Seite. --}}
@extends('errors.layout')
@section('code', '500')
@section('title', 'Interner Fehler')
@section('message', 'Es ist ein interner Fehler aufgetreten. Er wurde protokolliert.')
