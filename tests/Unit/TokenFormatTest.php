<?php

use App\Auth\TokenFormat;

it('kann ein erzeugtes Token zerlegen und prüfen', function () {
    $token = TokenFormat::generate();
    [$id, $secret] = TokenFormat::parse($token['plaintext']);

    expect($id)->toBe($token['token_id'])
        ->and(TokenFormat::verifySecret($secret, $token['token_hash']))->toBeTrue()
        ->and(TokenFormat::verifySecret('falsch', $token['token_hash']))->toBeFalse();
});

it('trennt ein Geheimnis mit Unterstrich korrekt', function () {
    // base64url erzeugt `_` im Geheimnis. Der Trenner ist trotzdem eindeutig,
    // weil die hex-kodierte ID keines enthaelt.
    expect(TokenFormat::parse('sk_0a1b2c_abc_def'))->toBe(['0a1b2c', 'abc_def']);
});

it('weist kaputte Formate ab', function (string $input) {
    expect(TokenFormat::parse($input))->toBeNull();
})->with(['', 'sk_', 'sk_nurid', 'pk_abc_def', 'sk__geheim']);

it('erzeugt jedes Mal ein anderes Token', function () {
    expect(TokenFormat::generate()['plaintext'])->not->toBe(TokenFormat::generate()['plaintext']);
});
