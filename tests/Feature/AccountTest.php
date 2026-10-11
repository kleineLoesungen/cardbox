<?php

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;

it('ändert den Anzeigenamen', function () {
    $user = actingAsUser();

    $this->from('/account')->post('/account', ['name' => 'Neuer Name'])
        ->assertRedirect('/account')
        ->assertSessionHas('saved', 'profile');

    expect($user->refresh()->name)->toBe('Neuer Name');
});

it('lässt den eigenen Benutzernamen nicht ändern', function () {
    $user = actingAsUser(['username' => 'bleibt']);

    $this->from('/account')->post('/account', ['name' => 'X', 'username' => 'anders'])
        ->assertSessionHas('saved', 'profile');

    expect($user->refresh()->username)->toBe('bleibt');
    $this->get('/account')->assertSee('bleibt');
});

it('ändert das Passwort nur mit dem richtigen aktuellen Passwort', function () {
    $user = actingAsUser();

    $this->from('/account')->post('/account/password', [
        'current_password' => 'falsch',
        'new_password' => 'ganz-neues-passwort',
        'new_password_repeat' => 'ganz-neues-passwort',
    ])->assertSessionHasErrorsIn('password', ['current_password']);

    $this->from('/account')->post('/account/password', [
        'current_password' => UserFactory::PASSWORD,
        'new_password' => 'ganz-neues-passwort',
        'new_password_repeat' => 'ganz-neues-passwort',
    ])->assertSessionHas('saved', 'password');

    expect(Hash::check('ganz-neues-passwort', $user->refresh()->password))->toBeTrue();
});

it('speichert Passwörter als Argon2id', function () {
    $user = User::factory()->create(['password' => 'irgendein-passwort']);

    expect($user->password)->toStartWith('$argon2id$');
});
