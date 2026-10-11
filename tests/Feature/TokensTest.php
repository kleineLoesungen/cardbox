<?php

use App\Models\ApiToken;

it('nimmt mehrere angekreuzte Berechtigungen entgegen', function () {
    actingAsAdmin();

    $this->withHeader('HX-Request', 'true')->post('/admin/tokens', [
        'name' => 'Rechnungssystem',
        'scopes' => ['notes:read', 'notes:write'],
        'expires_in_days' => '30',
    ])->assertOk()->assertSee('Jetzt kopieren');

    $token = ApiToken::sole();
    expect($token->scopes)->toBe(['notes:read', 'notes:write'])
        ->and($token->expires_at->isFuture())->toBeTrue()
        ->and(round(now()->diffInDays($token->expires_at)))->toBe(30.0);
});

it('zeigt den Klartext genau einmal und speichert nur den Hash', function () {
    actingAsAdmin();

    $html = $this->post('/admin/tokens', ['name' => 'Dienst', 'scopes' => ['users:read']])->getContent();
    preg_match('/value="(sk_[^"]+)"/', $html, $treffer);
    $plaintext = $treffer[1];

    $token = ApiToken::sole();
    expect($token->token_hash)->not->toContain($plaintext)
        ->and($this->get('/admin/tokens')->getContent())->not->toContain($plaintext);
});

it('lehnt ein Token ohne Berechtigung ab', function () {
    actingAsAdmin();

    $this->withHeader('HX-Request', 'true')
        ->post('/admin/tokens', ['name' => 'Nutzlos'])
        ->assertStatus(422)
        ->assertSee('ohne Scope kann das Token nichts');

    expect(ApiToken::count())->toBe(0);
});

it('lehnt unbekannte Berechtigungen ab', function () {
    actingAsAdmin();

    $this->post('/admin/tokens', ['name' => 'Tippfehler', 'scopes' => ['notes:reed']])
        ->assertSessionHasErrors('scopes.0');

    expect(ApiToken::count())->toBe(0);
});

it('widerruft ein Token, behält aber den Datensatz', function () {
    $admin = actingAsAdmin();
    $this->post('/admin/tokens', ['name' => 'Dienst', 'scopes' => ['notes:read']]);
    $token = ApiToken::sole();

    $this->post("/admin/tokens/{$token->id}/revoke")->assertOk()->assertContent('');
    expect($token->refresh()->isRevoked())->toBeTrue();

    // Zweimal widerrufen geht nicht.
    $this->post("/admin/tokens/{$token->id}/revoke")->assertNotFound();
});
