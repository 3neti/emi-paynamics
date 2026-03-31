<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails;

it('fetches wallet details by wallet id', function () {
    Http::fake([
        '*/view_wallet/*' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTABCDEF',
                'account_id' => 'CNSTCSTMR12345',
                'wallet_type' => 'Personal',
                'status' => 'Active',
                'balance' => '1000.50',
                'compliance_level' => '1',
                'verification_status' => 'APPROVED',
                'external_uid' => 'ext-123',
            ],
        ]),
    ]);

    $result = GetWalletDetails::run('CNSTWLLTABCDEF');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['wallet_id'])->toBe('CNSTWLLTABCDEF')
        ->and($result['data']['compliance_level'])->toBe('1');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'view_wallet/CNSTWLLTABCDEF'));
});

it('fetches wallet balance with limit fields', function () {
    Http::fake([
        '*/check_balance/*' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTABCDEF',
                'balance' => '5000.00',
                'remaining_wallet_limit' => '45000.00',
                'remaining_inflow_limit' => '95000.00',
                'remaining_annual_inflow_limit' => '500000.00',
                'remaining_outflow_limit' => '45000.00',
            ],
        ]),
    ]);

    $result = GetWalletBalance::run('CNSTWLLTABCDEF');

    expect($result['success'])->toBeTrue()
        ->and($result['data']['balance'])->toBe('5000.00')
        ->and($result['data'])->toHaveKeys([
            'remaining_wallet_limit',
            'remaining_inflow_limit',
            'remaining_annual_inflow_limit',
            'remaining_outflow_limit',
        ]);
});
