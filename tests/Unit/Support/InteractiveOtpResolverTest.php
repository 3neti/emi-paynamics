<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutOtp;
use LBHurtado\EmiPaynamicsConstellation\Support\InteractiveOtpResolver;

it('requests Paynamics cash-out OTP and resolves OTP through callback', function () {
    $action = Mockery::mock(CreateCashOutOtp::class);

    $payload = [
        'account_id' => 'CNSTMRCHWPIG61',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ];

    $action->shouldReceive('handle')
        ->once()
        ->with($payload)
        ->andReturn([
            'success' => true,
            'data' => 'OTP successfully sent to 639171234567',
        ]);

    $resolver = new InteractiveOtpResolver($action);

    $resolver->withInputCallback(function (array $otpRequestPayload, array $result) use ($payload): string {
        expect($otpRequestPayload)->toBe($payload)
            ->and($result)->toMatchArray([
                'success' => true,
            ]);

        return '441498';
    });

    expect($resolver->resolve($payload))->toBe('441498');
});

it('throws when Paynamics cash-out OTP request fails', function () {
    $action = Mockery::mock(CreateCashOutOtp::class);

    $payload = [
        'account_id' => 'CNSTMRCHWPIG61',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ];

    $action->shouldReceive('handle')
        ->once()
        ->with($payload)
        ->andReturn([
            'success' => false,
            'data' => [
                'response_message' => 'OTP request failed.',
            ],
        ]);

    $resolver = new InteractiveOtpResolver($action);

    expect(fn () => $resolver->resolve($payload))
        ->toThrow(RuntimeException::class, 'OTP request failed');
});
