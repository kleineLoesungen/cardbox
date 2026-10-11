{{--
  Grundgeruest jeder Seite. Enthaelt bewusst KEINE Navigation — die kommt in
  layouts/app.blade.php dazu. Anmelde-, Offline- und Fehlerseiten erben direkt von hier.
--}}
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') · {{ $branding->name }}</title>

  <link rel="stylesheet" href="/css/app.css">
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">

  {{-- Installierbare App (PWA). Werte aus APP_NAME, PWA_THEME_COLOR usw. --}}
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="{{ $branding->themeColor }}">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="{{ $branding->shortName }}">
  <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

  {{--
    htmx: Wie mit Fehlerantworten umgegangen wird.
    422 (Validierungsfehler) wird eingesetzt — die Antwort enthaelt nur eine
    Meldung als Out-of-Band-Fragment, siehe bootstrap/app.php. Alle anderen
    Fehler werden NICHT eingesetzt, sondern unten als Meldung angezeigt.
  --}}
  <meta name="htmx-config" content='{"responseHandling":[{"code":"204","swap":false},{"code":"[23]..","swap":true},{"code":"422","swap":true,"error":true},{"code":"[45]..","swap":false,"error":true}]}'>

  {{--
    Vor dem ersten Zeichnen die gespeicherte Farbwahl setzen, damit es beim
    Laden nicht kurz hell aufblitzt. Bewusst nicht "defer".
  --}}
  <script>
    (() => {
      try {
        const saved = localStorage.getItem("theme");
        if (saved === "dark" || saved === "light") {
          document.documentElement.dataset.theme = saved;
        }
      } catch (_) { /* localStorage kann gesperrt sein — dann Systemwahl */ }
    })();
  </script>

  <script src="/js/htmx.min.js" defer></script>
</head>

{{--
  hx-headers: Jede htmx-Anfrage schickt das CSRF-Token mit. Normale Formulare
  brauchen dafuer @csrf. Moderne Browser bestehen die Pruefung zusaetzlich
  ueber Sec-Fetch-Site — das Token ist die Rueckfallebene.
--}}
<body class="min-h-dvh bg-surface text-content antialiased"
      hx-headers='{"X-CSRF-TOKEN": "{{ csrf_token() }}"}'>
  @yield('body')

  {{--
    Zielbereich fuer Meldungen. Der Server schickt Toasts als
    Out-of-Band-Fragment hierher — siehe docs/RECIPES.md.
    Nach einer Weiterleitung kommen sie aus der Session: ->with('toast', [...]).
  --}}
  <div id="toasts" class="toast-region" role="status" aria-live="polite">
    @if (session('toast'))
      <div class="toast toast-{{ session('toast')['kind'] }}" data-autohide>
        <span>{{ session('toast')['message'] }}</span>
      </div>
    @endif
  </div>

  <script>
    // --- Zeitstempel in die Ortszeit des Betrachters umrechnen -------------
    // Der Server liefert UTC. Welche Zeitzone der Mensch vor dem Bildschirm
    // hat, weiss nur sein Browser — also rechnet der es aus. Damit stimmt
    // auch die Sommerzeit, ohne dass der Server sie kennen muss.
    const formatTimes = (root) => {
      const long = new Intl.DateTimeFormat("de-DE", {
        day: "2-digit", month: "2-digit", year: "numeric",
        hour: "2-digit", minute: "2-digit",
      });
      const short = new Intl.DateTimeFormat("de-DE", {
        day: "2-digit", month: "2-digit", year: "numeric",
      });
      const full = new Intl.DateTimeFormat("de-DE", {
        dateStyle: "full", timeStyle: "long",
      });

      root.querySelectorAll("time[data-time]").forEach((el) => {
        const value = el.getAttribute("datetime");
        if (!value) return;
        const d = new Date(value);
        if (isNaN(d.getTime())) return;
        el.textContent = (el.dataset.time === "short" ? short : long).format(d);
        el.title = full.format(d);
      });
    };

    formatTimes(document);

    // Von htmx nachgeladene Ausschnitte bringen eigene Zeitstempel mit.
    document.body.addEventListener("htmx:afterSettle", (e) => {
      formatTimes(e.target instanceof Element ? e.target : document);
    });

    // --- Service Worker --------------------------------------------------
    // Macht die App installierbar und zeigt ohne Netz eine Offline-Seite.
    // Browser erlauben ihn nur ueber HTTPS oder auf localhost — im lokalen
    // Netz per http://192.168… also nicht. Fehler sind dann kein Problem,
    // die Seite funktioniert trotzdem.
    if ("serviceWorker" in navigator) {
      window.addEventListener("load", () => {
        navigator.serviceWorker.register("/sw.js").catch(() => {});
      });
    }

    // --- Meldungen ---------------------------------------------------------
    const toasts = document.getElementById("toasts");

    const autohide = (node) => {
      setTimeout(() => {
        node.style.transition = "opacity 200ms";
        node.style.opacity = "0";
        setTimeout(() => node.remove(), 200);
      }, 4000);
    };

    const showToast = (message, kind = "danger") => {
      const el = document.createElement("div");
      el.className = `toast toast-${kind}`;
      el.setAttribute("data-autohide", "");
      el.textContent = message;
      toasts.append(el);
    };

    // Meldungen nach vier Sekunden selbst entfernen — auch die aus der Session.
    toasts.querySelectorAll("[data-autohide]").forEach(autohide);
    new MutationObserver((records) => {
      for (const record of records) {
        for (const node of record.addedNodes) {
          if (node.nodeType === 1 && node.matches("[data-autohide]")) autohide(node);
        }
      }
    }).observe(toasts, { childList: true });

    // Fehler einer htmx-Anfrage sichtbar machen, statt still nichts zu tun.
    // 422 bringt seine Meldung selbst mit (siehe bootstrap/app.php).
    document.body.addEventListener("htmx:responseError", (e) => {
      const status = e.detail.xhr.status;
      if (status === 422) return;
      const messages = {
        403: "Dafür fehlt die Berechtigung.",
        404: "Das gibt es nicht (mehr).",
        419: "Die Sitzung ist abgelaufen. Bitte die Seite neu laden.",
      };
      showToast(messages[status] || `Das hat nicht geklappt (Fehler ${status}).`);
    });
    document.body.addEventListener("htmx:sendError", () => {
      showToast("Keine Verbindung zum Server.");
    });
  </script>
  @stack('scripts')
</body>
</html>
