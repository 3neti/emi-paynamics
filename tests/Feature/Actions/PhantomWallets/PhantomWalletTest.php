<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets\GetWithheldByPhantomWalletId;
use LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets\GetWithheldByWalletId;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddPhantomWallet;

it('creates a phantom wallet', function () {
    Http::fake([
        '*/phantom_wallet/add' => Http::response([
            'success' => true,
            'data' => ['wallet_id' => 'CNSTPHNTM00001', 'status' => 'Active'],
        ]),
    ]);

    $result = AddPhantomWallet::run([
        'external_uid' => 'phantom-ext-001',
        'expiration' => '2026-12-31',
        'profile_type' => 'DEFAULT_MERCHANT',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['wallet_id'])->toBe('CNSTPHNTM00001');
});

it('fetches withheld funds by wallet id', function () {
    Http::fake([
        '*/withhelds/wallet/*' => Http::response([
            'success' => true,
            'data' => [
                ['request_id' => 'REQ-W-001', 'withheld_amount' => '500.00', 'status' => 'PENDING'],
            ],
        ]),
    ]);

    $result = GetWithheldByWalletId::run('CNSTWLLT000001');

    expect($result['success'])->toBeTrue()
        ->and($result['data'])->toHaveCount(1);
});

it('fetches withheld funds by phantom wallet id', function () {
    Http::fake([
        '*/withhelds/phantom_wallet/*' => Http::response([
            'success' => true,
            'data' => [],
        ]),
    ]);

    $result = GetWithheldByPhantomWalletId::run('CNSTPHNTM00001');

    expect($result['success'])->toBeTrue()
        ->and($result['data'])->toBeEmpty();
});
