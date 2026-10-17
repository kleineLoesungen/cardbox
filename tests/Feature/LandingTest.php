<?php

use App\Models\User;

/*
 * Start page for guests: built from cards, with the way in that fits the
 * installation (first setup or sign-in/registration).
 */

it('zeigt Gästen die Lernkarte, die Schritte, den Karteikasten und den Weg hinein', function () {
    User::factory()->create();

    $this->get('/')->assertOk()
        ->assertSee('data-landing-card', false)
        ->assertSee('Hund')
        ->assertSee('So geht')
        ->assertSee('Der Karteikasten')
        ->assertSee('Kann ich')
        ->assertSee('href="/register"', false)
        ->assertSee('href="/login"', false)
        ->assertSee('Neue Konten schaltet ein Administrator frei.')
        ->assertDontSee('Ersten Administrator anlegen');
});

it('führt vor der Ersteinrichtung nur zum ersten Administrator', function () {
    $this->get('/')->assertOk()
        ->assertSee('Ersten Administrator anlegen')
        ->assertDontSee('href="/login"', false);
});

it('gibt die Beispielwörter maskiert an das Skript weiter', function () {
    User::factory()->create();

    // JSON in a double-quoted attribute: quotes must arrive as &quot;.
    $this->get('/')->assertSee('data-examples="[{&quot;q&quot;:&quot;Hund&quot;', false);
});

it('nennt die Funktionen fürs Lernen und für Eltern, die Erinnerung nur wenn eingerichtet', function () {
    User::factory()->create();
    config(['push.vapid.public_key' => null, 'push.vapid.private_key' => null]);

    $this->get('/')
        ->assertSee('Tagesziel')
        ->assertSee('Antworten eintippen')
        ->assertSee('Knifflige Wörter')
        ->assertSee('Eltern schauen zu')
        ->assertDontSee('täglicher Erinnerung');

    config(['push.vapid.public_key' => 'public', 'push.vapid.private_key' => 'private']);
    $this->get('/')->assertSee('auf Wunsch mit täglicher Erinnerung');
});
