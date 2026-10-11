<?php

use App\Models\Note;
use App\Models\User;

it('zeigt nur die eigenen Notizen', function () {
    $user = actingAsUser();
    Note::factory()->for($user)->create(['title' => 'Meine Notiz']);
    Note::factory()->create(['title' => 'Fremde Notiz']);

    $this->get('/notes')
        ->assertOk()
        ->assertSee('Meine Notiz')
        ->assertDontSee('Fremde Notiz');
});

it('legt per htmx eine Notiz an und liefert nur den Eintrag samt Meldung', function () {
    $user = actingAsUser();

    $this->withHeader('HX-Request', 'true')
        ->post('/notes', ['title' => '  Einkaufen  ', 'body' => 'Milch'])
        ->assertOk()
        ->assertSee('Einkaufen')
        ->assertSee('hx-swap-oob', false)
        ->assertDontSee('<html', false);

    expect($user->notes()->sole()->title)->toBe('Einkaufen');
});

it('meldet einen Validierungsfehler per htmx als Meldung statt als Weiterleitung', function () {
    actingAsUser();

    $this->withHeader('HX-Request', 'true')
        ->post('/notes', ['title' => ''])
        ->assertStatus(422)
        ->assertHeader('HX-Reswap', 'none')
        ->assertSee('Titel darf nicht leer sein.');

    expect(Note::count())->toBe(0);
});

it('löscht eine eigene Notiz', function () {
    $user = actingAsUser();
    $note = Note::factory()->for($user)->create();

    $this->delete("/notes/{$note->id}")->assertOk()->assertContent('');

    expect(Note::count())->toBe(0);
});

it('behandelt fremde Notizen wie nicht vorhandene', function () {
    actingAsUser();
    $fremd = Note::factory()->for(User::factory())->create();

    $this->delete("/notes/{$fremd->id}")->assertNotFound();

    expect(Note::count())->toBe(1);
});

it('antwortet auf eine kaputte ID mit 404 statt mit einem Datenbankfehler', function () {
    actingAsUser();

    $this->delete('/notes/keine-uuid')->assertNotFound();
});
