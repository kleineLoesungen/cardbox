<?php

use App\Http\Controllers\AuthController;
use App\Support\Branding;

it('erkennt Hexfarben', function (string $value, bool $expected) {
    expect(Branding::isHexColor($value))->toBe($expected);
})->with([
    ['#3370c7', true],
    ['#FFF', true],
    ['3370c7', false],
    ['#3370c', false],
    ['#zzzzzz', false],
    ['red', false],
    ['', false],
    ['#3370c7" onload="', false],
]);

it('drückt Wartezeiten menschenlesbar aus', function () {
    expect(AuthController::waitText(5))->toBe('in 5 Sekunden')
        ->and(AuthController::waitText(90))->toBe('in 90 Sekunden')
        ->and(AuthController::waitText(135))->toBe('in 3 Minuten');
});
