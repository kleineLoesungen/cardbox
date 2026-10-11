{{--
  Ein Zeitstempel mit Uhrzeit:  <x-datetime :value="$note->created_at" />

  Ausgegeben wird UTC im `datetime`-Attribut; der Text ist nur der Rueckfall
  ohne JavaScript. Der Browser ersetzt ihn beim Laden durch die Ortszeit des
  Betrachters (Skript in layouts/base.blade.php) — samt Sommerzeit.

  Nie {{ $value->format(...) }} direkt ins Template schreiben: Das ist UTC und
  im Sommer zwei Stunden daneben.
--}}
@props(['value'])
<time datetime="{{ $value->toIso8601ZuluString() }}" data-time="long">{{ $value->format('d.m.Y, H:i') }}</time>
