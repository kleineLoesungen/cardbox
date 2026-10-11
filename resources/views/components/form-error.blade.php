{{--
  Erste Fehlermeldung eines Formulars als Hinweisleiste.

    <x-form-error />                 Standard-Tasche
    <x-form-error bag="password" />  benannte Tasche (mehrere Formulare auf einer Seite)

  Laravel legt Validierungsfehler nach einer Weiterleitung in $errors ab und
  fuellt old('feld') mit den Eingaben — die Felder bleiben dadurch stehen.
--}}
@props(['bag' => 'default'])
@if ($errors->getBag($bag)->any())
  <div class="alert alert-danger" role="alert">{{ $errors->getBag($bag)->first() }}</div>
@endif
