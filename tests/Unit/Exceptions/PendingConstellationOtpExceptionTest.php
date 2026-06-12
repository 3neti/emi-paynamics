<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;

it('carries Paynamics pending OTP request context', function () {
    $exception = PendingConstellationOtpException::fromPayload(
        otpRequestPayload: [
            'account_id' => 'CNSTMRCHWPIG61',
            'bank_account_no' => '09173011987',
            'bank_id' => 'GXI',
            'request_id' => 'TEST-Z3EL-09173011987-S1',
            'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
            'amount' => '75.00',
        ],
        otpRequestResult: [
            'success' => true,
            'data' => 'OTP successfully sent to 639171234567',
        ],
    );

    expect($exception->provider())->toBe('paynamics')
        ->and($exception->requestId())->toBe('TEST-Z3EL-09173011987-S1')
        ->and($exception->amount())->toBe('75.00')
        ->and($exception->bankAccountNo())->toBe('09173011987')
        ->and($exception->bankId())->toBe('GXI')
        ->and($exception->reason())->toBe('Voucher payout TEST-Z3EL-09173011987-S1')
        ->and($exception->target())->toBe('OTP successfully sent to 639171234567')
        ->and($exception->otpRequestPayload())->toMatchArray([
            'request_id' => 'TEST-Z3EL-09173011987-S1',
        ])
        ->and($exception->otpRequestResult())->toMatchArray([
            'success' => true,
        ]);
});

it('converts pending OTP context into approval metadata', function () {
    $exception = PendingConstellationOtpException::fromPayload(
        otpRequestPayload: [
            'bank_account_no' => '09173011987',
            'bank_id' => 'GXI',
            'request_id' => 'TEST-Z3EL-09173011987-S1',
            'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
            'amount' => '75.00',
        ],
        otpRequestResult: [
            'success' => true,
            'data' => 'OTP successfully sent to 639171234567',
        ],
    );

    expect($exception->toApprovalMetadata())->toMatchArray([
        'provider' => 'paynamics',
        'authorization_type' => 'otp',
        'reference_id' => 'TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'target' => 'OTP successfully sent to 639171234567',
        'otp_required' => true,
        'polling_required' => false,
        'manual_review' => false,
        'message' => 'Paynamics payout OTP is pending.',
    ]);
});
