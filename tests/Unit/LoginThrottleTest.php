<?php

use App\Services\LoginThrottle;

it('sperrt nach zwei Fehlversuchen noch nicht', function () {
    expect(LoginThrottle::delaySeconds(1))->toBe(0)
        ->and(LoginThrottle::delaySeconds(2))->toBe(0);
});

it('sperrt nach dem dritten Fehlversuch', function () {
    expect(LoginThrottle::delaySeconds(3))->toBe(5);
});

it('lässt die Wartezeit wachsen und deckelt sie', function () {
    expect(LoginThrottle::delaySeconds(4))->toBe(15)
        ->and(LoginThrottle::delaySeconds(5))->toBe(45)
        ->and(LoginThrottle::delaySeconds(6))->toBe(135)
        ->and(LoginThrottle::delaySeconds(7))->toBe(405)
        ->and(LoginThrottle::delaySeconds(8))->toBe(LoginThrottle::MAX_SECONDS)
        ->and(LoginThrottle::delaySeconds(1000))->toBe(LoginThrottle::MAX_SECONDS);
});

it('normalisiert die Schlüssel', function () {
    expect(LoginThrottle::keys('  Max@Example.COM ', '10.0.0.1'))->toBe(['email:max@example.com', 'ip:10.0.0.1'])
        ->and(LoginThrottle::keys('a@b.de', null))->toBe(['email:a@b.de']);
});
