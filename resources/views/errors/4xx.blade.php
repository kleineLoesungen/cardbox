@extends('errors.layout')
@section('code', $exception->getStatusCode())
@section('title', 'Anfrage abgelehnt')
@section('message', 'Diese Anfrage konnte nicht bearbeitet werden.')
