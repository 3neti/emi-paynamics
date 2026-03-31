<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOut;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutOtp;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\VerifyTransaction;

it('sends a cash-out request with correct signature', function () {
    Http::fake([
        '*/withdraw_request' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => 'CO-001',
                'status' => 'PENDING',
                'amount' => '500.00',
            ],
        ]),
    ]);

    $result = CreateCashOut::run([
        'account_id' => 'CNSTCSTMR12345',
        'bank_account_no' => '1234567890',
        'request_id' => 'CO-001',
        'amount' => '500.00',
        'reason' => 'Withdrawal',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['request_id'])->toBe('CO-001');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'withdraw_request') && $r['signature'] !== null);
});

it('sends a cash-out OTP request', function () {
    Http::fake([
        '*/request_otp' => Http::response([
            'success' => true,
            'data' => ['status' => 'OTP_SENT'],
        ]),
    ]);

    $result = CreateCashOutOtp::run([
        'account_id' => 'CNSTCSTMR12345',
        'bank_account_no' => '1234567890',
        'request_id' => 'CO-001',
        'reason' => 'Withdrawal',
        'amount' => '500.00',
    ]);

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'request_otp'));
});

it('verifies a transaction with pin', function () {
    Http::fake([
        '*/transaction/verify_request' => Http::response([
            'success' => true,
            'data' => ['status' => 'VERIFIED'],
        ]),
    ]);

    $result = VerifyTransaction::run([
        'request_id' => 'CO-001',
        'wallet_id' => 'CNSTWLLT000001',
        'pin' => '123456',
        'timestamp' => '2025-01-01T00:00:00Z',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['status'])->toBe('VERIFIED');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'transaction/verify_request') && $r['signature'] !== null);
});
