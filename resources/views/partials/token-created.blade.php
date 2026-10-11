{{--
  Antwort auf POST /admin/tokens. Wird genau einmal angezeigt: direkt nach dem
  Erstellen. Danach existiert nur noch der Hash in der Datenbank — der Klartext
  ist unwiederbringlich weg.
--}}
<div class="alert alert-success mb-6 flex-col items-stretch gap-3" id="token-created">
  <div>
    <p class="font-medium">Token „{{ $token->name }}“ erstellt</p>
    <p class="mt-1 text-xs opacity-90">
      Jetzt kopieren — es wird nie wieder angezeigt.
    </p>
  </div>

  <div class="row gap-2">
    <input class="input font-mono text-xs" id="token-value" value="{{ $plaintext }}" readonly>
    <button type="button" class="btn btn-secondary shrink-0" data-copy-token>Kopieren</button>
  </div>
</div>

{{-- Die Tabellenzeile nebenbei in die Liste einhaengen. --}}
<template id="new-row"><x-token-row :token="$token" /></template>
<script>
  (() => {
    const box = document.getElementById("token-created");
    const input = document.getElementById("token-value");

    input.addEventListener("focus", () => input.select());
    box.querySelector("[data-copy-token]").addEventListener("click", (e) => {
      navigator.clipboard.writeText(input.value);
      e.currentTarget.textContent = "Kopiert";
    });

    // Die neue Zeile oben in die Tabelle haengen, den Leerzustand entfernen.
    const row = document.getElementById("new-row");
    const body = document.getElementById("token-rows");
    if (row && body) {
      body.querySelector("[data-empty]")?.remove();
      body.prepend(row.content.cloneNode(true));
      htmx.process(body.firstElementChild);
      row.remove();
    }

    // Der Klartext ist nur dieses eine Mal zu sehen. War die Seite gescrollt,
    // laege der Kasten sonst ausserhalb des Sichtbereichs — dann ginge das
    // Token verloren, ohne dass es jemand merkt.
    box.scrollIntoView({ behavior: "smooth", block: "center" });
  })();
</script>
