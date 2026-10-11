{{--
  Error message directly below a field — easier to connect for people who do
  not read the alert at the top of the form.

    <x-field-error name="username" />
    <x-field-error name="name" bag="profile" />
--}}
@props(['name', 'bag' => 'default'])
@if ($errors->getBag($bag)->has($name))
  <p class="error" id="{{ $name }}-error">{{ $errors->getBag($bag)->first($name) }}</p>
@endif
