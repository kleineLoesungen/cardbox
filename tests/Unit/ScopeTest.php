<?php

use App\Enums\Scope;

it('beschriftet bekannte Scopes und lässt unbekannte stehen', function () {
    expect(Scope::labelFor('notes:read'))->toBe('Notizen lesen')
        ->and(Scope::labelFor('unbekannt'))->toBe('unbekannt');
});

it('erkennt Tippfehler im Scope', function () {
    expect(Scope::tryFrom('notes:reed'))->toBeNull()
        ->and(Scope::tryFrom('users:read'))->toBe(Scope::UsersRead);
});
