<?php

use App\Enums\Role;

it('lässt höhere Rollen niedrigere einschließen', function () {
    expect(Role::Admin->atLeast(Role::User))->toBeTrue()
        ->and(Role::Admin->atLeast(Role::Admin))->toBeTrue()
        ->and(Role::Moderator->atLeast(Role::User))->toBeTrue()
        ->and(Role::User->atLeast(Role::Moderator))->toBeFalse()
        ->and(Role::Moderator->atLeast(Role::Admin))->toBeFalse();
});

it('hat für jede Rolle eine deutsche Beschriftung', function () {
    expect(array_map(fn (Role $r) => $r->label(), Role::cases()))
        ->toBe(['Benutzer', 'Moderator', 'Administrator']);
});
