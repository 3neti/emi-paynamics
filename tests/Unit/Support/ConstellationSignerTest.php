<?php

use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;

it('generates a valid SHA512 signature from ordered fields', function () {
    $signer = new ConstellationSigner;
    $integrationKey = 'TEST_KEY_123';

    // Simulate Add Merchant Wallet: company_name + tin + email + ... + mobile_no + key
    $fields = ['ABCD Company', '103-000-000-002', 'test@abc.net', 'https://www.abc.com', 'testuser', 'Pass@12345678', 'John', 'Doe', 'DEFAULT_MERCHANT', '639084225745'];

    $signature = $signer->generateSignature($fields, $integrationKey);

    $expectedRaw = implode('', $fields).$integrationKey;
    $expectedSignature = hash('sha512', $expectedRaw);

    expect($signature)
        ->toBe($expectedSignature)
        ->toHaveLength(128); // SHA512 hex = 128 chars
});

it('produces different signatures for different field orders', function () {
    $signer = new ConstellationSigner;
    $key = 'KEY';

    $sig1 = $signer->generateSignature(['a', 'b'], $key);
    $sig2 = $signer->generateSignature(['b', 'a'], $key);

    expect($sig1)->not->toBe($sig2);
});
