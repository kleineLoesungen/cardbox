// Erzeugt von App\Http\Controllers\PwaController — Quelle: resources/views/pwa/service-worker.blade.php
const CACHE = @js($cache);

// Was ohne Netz verfuegbar sein muss: die Offline-Seite und ihr Aussehen.
const PRECACHE = [
  "/offline",
  "/css/app.css",
  "/js/htmx.min.js",
  "/favicon.svg",
  "/icons/icon-192.png",
];

// Statische Dateien, die ohne Netz aus dem Speicher kommen duerfen.
const STATIC = ["/css/", "/js/", "/icons/", "/favicon.svg"];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.addAll(PRECACHE))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((names) => Promise.all(
        names.filter((name) => name !== CACHE).map((name) => caches.delete(name))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  // Seiten: nie aus dem Speicher. Ohne Netz die Offline-Seite.
  if (request.mode === "navigate") {
    event.respondWith(fetch(request).catch(() => caches.match("/offline")));
    return;
  }

  // Statische Dateien: frisch vom Server, ohne Netz aus dem Speicher.
  if (STATIC.some((prefix) => url.pathname.startsWith(prefix))) {
    event.respondWith(
      fetch(request)
        .then((response) => {
          if (response.ok) {
            const copy = response.clone();
            caches.open(CACHE).then((cache) => cache.put(request, copy));
          }
          return response;
        })
        .catch(() => caches.match(request))
    );
  }

  // Alles andere (API, htmx-Fragmente) bleibt unangetastet.
});
