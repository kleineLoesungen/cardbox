@extends('mail.layout')
@section('subject', 'Testmail')

@section('content')
<p style="margin:0 0 16px;">Hallo,</p>
<p style="margin:0 0 16px;">
  diese Testmail bestätigt, dass der E-Mail-Versand von {{ $brand->name }}
  funktioniert.
</p>
<p style="margin:0 0 24px;">
  <a href="{{ $link }}"
     style="display:inline-block;padding:10px 18px;border-radius:8px;background:{{ $brand->themeColor }};color:#ffffff;text-decoration:none;font-weight:600;">
    Zur Anwendung
  </a>
</p>
<p style="margin:0;font-size:13px;color:#7a8194;">Ausgelöst von {{ $triggeredBy }}.</p>
@endsection
