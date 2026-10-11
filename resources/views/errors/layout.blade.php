{{--
  Rahmen fuer Fehlerseiten. Bewusst eigenstaendig und ohne $branding oder
  Anmeldedaten: Eine Fehlerseite muss auch dann noch erscheinen, wenn genau
  das kaputt ist, worauf andere Seiten sich verlassen.

  Laravel sucht zuerst errors/<status>.blade.php, dann errors/4xx bzw. 5xx.
--}}
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') · {{ config('app.name') }}</title>
  <link rel="stylesheet" href="/css/app.css">
  <script>
    try {
      const saved = localStorage.getItem("theme");
      if (saved === "dark" || saved === "light") document.documentElement.dataset.theme = saved;
    } catch (_) {}
  </script>
</head>
<body class="min-h-dvh bg-surface text-content antialiased">
  <main class="mx-auto flex min-h-dvh max-w-md flex-col items-center justify-center gap-4 px-6 text-center">
    <p class="text-5xl font-semibold tabular-nums text-subtle">@yield('code')</p>
    <h1 class="text-xl font-semibold">@yield('title')</h1>
    <p class="text-sm text-muted">@yield('message')</p>
    <div class="flex flex-wrap justify-center gap-2">
      <a href="/" class="btn btn-primary">Zur Startseite</a>
      <button type="button" class="btn btn-secondary" onclick="history.back()">Zurück</button>
    </div>
  </main>
</body>
</html>
