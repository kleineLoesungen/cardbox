<?php

it('liefert ein Manifest mit Name und Farben aus der Konfiguration', function () {
    config(['app.name' => 'Vereinsportal', 'app.short_name' => 'Verein', 'pwa.theme_color' => '#AA3300']);

    $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('name', 'Vereinsportal')
        ->assertJsonPath('short_name', 'Verein')
        ->assertJsonPath('theme_color', '#aa3300');
});

it('liefert den Service Worker ungecacht und ohne Seiten im Zwischenspeicher', function () {
    $response = $this->get('/sw.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8');

    expect($response->headers->get('Cache-Control'))->toContain('no-cache')
        ->and($response->getContent())->toContain('caches.match("/offline")')
        ->not->toContain('/dashboard');
});

it('zeigt die Offline-Seite ohne Anmeldung', function () {
    $this->get('/offline')->assertOk()->assertSee('Keine Verbindung');
});

it('zeigt den Styleguide auch ohne Anmeldung', function () {
    $this->get('/styleguide')->assertOk()->assertSee('Farbrollen');
});
