<?php

use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSignatureVerifier;

it('verifies a valid postback signature', function () {
    $verifier = new ConstellationSignatureVerifier;
    $key = 'MERCHANT_KEY_123';

    $payload = [
        'code' => 'GR001',
        'message' => 'Success',
        'advise' => 'Transaction approved',
        'timestamp' => '2025-01-01T00:00:00Z',
    ];

    $raw = $payload['code'].$payload['message'].$payload['advise'].$payload['timestamp'].$key;
    $payload['signature'] = hash('sha512', $raw);

    expect($verifier->verifySignature($payload, $key))->toBeTrue();
});

it('rejects an invalid postback signature', function () {
    $verifier = new ConstellationSignatureVerifier;

    $payload = [
        'code' => 'GR001',
        'message' => 'Success',
        'advise' => 'Transaction approved',
        'timestamp' => '2025-01-01T00:00:00Z',
        'signature' => 'invalid_signature_here',
    ];

    expect($verifier->verifySignature($payload, 'MERCHANT_KEY_123'))->toBeFalse();
});

it('rejects when signature is missing', function () {
    $verifier = new ConstellationSignatureVerifier;

    $payload = [
        'code' => 'GR001',
        'message' => 'Success',
        'advise' => '',
        'timestamp' => '2025-01-01T00:00:00Z',
    ];

    expect($verifier->verifySignature($payload, 'KEY'))->toBeFalse();
});
