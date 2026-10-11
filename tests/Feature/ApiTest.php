<?php

use App\Auth\TokenFormat;
use App\Models\ApiToken;
use App\Models\Note;
use App\Models\User;

/**
 * Stellt ein Token mit den angegebenen Scopes aus und liefert den Klartext.
 *
 * @param  list<string>  $scopes
 */
function issueToken(array $scopes, array $attributes = []): string
{
    $generated = TokenFormat::generate();
    // forceFill: revoked_at und expires_at sind absichtlich nicht frei befuellbar.
    (new ApiToken)->forceFill([
        'name' => 'Testdienst',
        'token_id' => $generated['token_id'],
        'token_hash' => $generated['token_hash'],
        'scopes' => $scopes,
        ...$attributes,
    ])->save();

    return $generated['plaintext'];
}

describe('Anmeldung an der Schnittstelle', function () {
    it('meldet den Zustand ohne Token, samt Datenbank', function () {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['status' => 'ok', 'database' => 'ok']);
    });

    it('verlangt ein Token und sagt, wie es aussehen muss', function () {
        $this->getJson('/api/v1/notes')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="api"')
            ->assertJsonPath('error.code', 'unauthorized');
    });

    it('unterscheidet unbekannt, widerrufen, abgelaufen und falsches Geheimnis nicht', function () {
        $widerrufen = issueToken(['notes:read'], ['revoked_at' => now()]);
        $abgelaufen = issueToken(['notes:read'], ['expires_at' => now()->subMinute()]);
        $gueltig = issueToken(['notes:read']);
        [$id] = TokenFormat::parse($gueltig);

        foreach (['sk_unbekannt_geheim', $widerrufen, $abgelaufen, "sk_{$id}_falsches-geheimnis"] as $token) {
            $this->withToken($token)->getJson('/api/v1/notes')
                ->assertUnauthorized()
                ->assertJsonPath('error.code', 'unauthorized');
        }
    });

    it('nennt bei fehlender Berechtigung den nötigen Scope', function () {
        $token = issueToken(['notes:read']);

        $this->withToken($token)->postJson('/api/v1/notes', ['title' => 'x'])
            ->assertForbidden()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="api", scope="notes:write"')
            ->assertJsonPath('error.code', 'insufficient_scope')
            ->assertJsonPath('error.required_scope', 'notes:write');
    });

    it('gibt Name und Berechtigungen des Tokens zurück und vermerkt die Benutzung', function () {
        $token = issueToken(['notes:read', 'users:read']);

        $this->withToken($token)->getJson('/api/v1/token')
            ->assertOk()
            ->assertJsonPath('data.name', 'Testdienst')
            ->assertJsonPath('data.scopes', ['notes:read', 'users:read']);

        expect(ApiToken::sole()->last_used_at)->not->toBeNull();
    });

    it('erlaubt Maschinen keinen Zugriff auf die Weboberfläche', function () {
        $token = issueToken(['notes:read', 'notes:write', 'users:read']);

        $this->withToken($token)->get('/notes')->assertRedirect('/login');
    });
});

describe('Notizen', function () {
    it('listet systemweit und wahlweise für einen Besitzer', function () {
        $anna = User::factory()->create();
        Note::factory()->for($anna)->create();
        Note::factory()->create();
        $token = issueToken(['notes:read']);

        $this->withToken($token)->getJson('/api/v1/notes')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($token)->getJson("/api/v1/notes?user_id={$anna->id}")->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/v1/notes?user_id=keine-uuid')->assertOk()->assertJsonCount(0, 'data');
    });

    it('legt eine Notiz mit ausdrücklich angegebenem Besitzer an', function () {
        $anna = User::factory()->create();
        $token = issueToken(['notes:write']);

        $this->withToken($token)->postJson('/api/v1/notes', [
            'user_id' => $anna->id, 'title' => 'Neue Notiz', 'body' => 'Inhalt',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $anna->id)
            ->assertJsonPath('data.title', 'Neue Notiz')
            ->assertJsonStructure(['data' => ['id', 'user_id', 'title', 'body', 'created_at', 'updated_at']]);

        expect(Note::sole()->created_at->toIso8601ZuluString())->toEndWith('Z');
    });

    it('antwortet auf einen unbekannten Besitzer mit 422 statt 500', function (string $userId) {
        $token = issueToken(['notes:write']);

        $this->withToken($token)->postJson('/api/v1/notes', ['user_id' => $userId, 'title' => 'x'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.fields.user_id.0', 'Diesen Benutzer gibt es nicht.');
    })->with(['019a0000-0000-7000-8000-000000000000', 'keine-uuid']);

    it('ändert und löscht eine Notiz', function () {
        $note = Note::factory()->create();
        $token = issueToken(['notes:write']);

        $this->withToken($token)->putJson("/api/v1/notes/{$note->id}", ['title' => 'Geändert'])
            ->assertOk()->assertJsonPath('data.title', 'Geändert');

        $this->withToken($token)->deleteJson("/api/v1/notes/{$note->id}")->assertNoContent();
        expect(Note::count())->toBe(0);
    });

    it('meldet unbekannte Notizen als not_found', function (string $id) {
        $token = issueToken(['notes:read']);

        $this->withToken($token)->getJson("/api/v1/notes/{$id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    })->with(['019a0000-0000-7000-8000-000000000000', 'keine-uuid']);
});

it('liefert Benutzer ohne Passwortdaten', function () {
    User::factory()->create();
    $token = issueToken(['users:read']);

    $response = $this->withToken($token)->getJson('/api/v1/users')->assertOk()->assertJsonCount(1, 'data');

    expect($response->getContent())->not->toContain('password')->not->toContain('argon2');
});

it('liefert unbekannte API-Pfade als JSON', function () {
    $this->getJson('/api/v1/gibt-es-nicht')->assertNotFound()->assertJsonPath('error.code', 'not_found');
});
