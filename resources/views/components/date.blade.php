{{-- Ein Datum ohne Uhrzeit:  <x-date :value="$user->created_at" />  — siehe datetime.blade.php --}}
@props(['value'])
<time datetime="{{ $value->toIso8601ZuluString() }}" data-time="short">{{ $value->format('d.m.Y') }}</time>
