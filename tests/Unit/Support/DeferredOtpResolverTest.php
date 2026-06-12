<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutOtp;
use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;
use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use LBHurtado\EmiPaynamicsConstellation\Support\DeferredOtpResolver;

it('returns submitted OTP when one already exists', function () {
    $action = Mockery::mock(CreateCashOutOtp::class);

    $store = new class implements PendingOtpStore
    {
        public function getSubmittedOtp(array $otpRequestPayload): ?string
        {
            return '441498';
        }

        public function putPendingOtp(array $otpRequestPayload, array $otpRequestResult): void
        {
            throw new RuntimeException('Should not store pending OTP when submitted OTP exists.');
        }
    };

    $payload = [
        'account_id' => 'CNSTMRCHWPIG61',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ];

    $action->shouldNotReceive('handle');

    $resolver = new DeferredOtpResolver($action, $store);

    expect($resolver->resolve($payload))->toBe('441498');
});

it('requests OTP stores pending context and throws pending OTP exception when no submitted OTP exists', function () {
    $action = Mockery::mock(CreateCashOutOtp::class);

    $store = new class implements PendingOtpStore
    {
        public array $pendingPayload = [];

        public array $pendingResult = [];

        public function getSubmittedOtp(array $otpRequestPayload): ?string
        {
            return null;
        }

        public function putPendingOtp(array $otpRequestPayload, array $otpRequestResult): void
        {
            $this->pendingPayload = $otpRequestPayload;
            $this->pendingResult = $otpRequestResult;
        }
    };

    $payload = [
        'account_id' => 'CNSTMRCHWPIG61',
        'bank_account_no' => '09173011987',
        'bank_id' => 'GXI',
        'request_id' => 'TEST-Z3EL-09173011987-S1',
        'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        'amount' => '75.00',
    ];

    $result = [
        'success' => true,
        'data' => 'OTP successfully sent to 639171234567',
    ];

    $action->shouldReceive('handle')
        ->once()
        ->with($payload)
        ->andReturn($result);

    $resolver = new DeferredOtpResolver($action, $store);

    try {
        $resolver->resolve($payload);

        $this->fail('Expected pending OTP exception was not thrown.');
    } catch (PendingConstellationOtpException $exception) {
        expect($store->pendingPayload)->toBe($payload)
            ->and($store->pendingResult)->toBe($result)
            ->and($exception->requestId())->toBe('TEST-Z3EL-09173011987-S1')
            ->and($exception->target())->toBe('OTP successfully sent to 639171234567');
    }
});

it('throws runtime exception when OTP request fails', function () {
    $action = Mockery::mock(CreateCashOutOtp::class);

    $store = new class implements PendingOtpStore
    {
        public function getSubmittedOtp(array $otpRequestPayload): ?string
        {
            return null;
        }

        public function putPendingOtp(array $otpRequestPayload, array $otpRequestResult): void
        {
            throw new RuntimeException('Should not store failed OTP request.');
        }
    };

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

    $resolver = new DeferredOtpResolver($action, $store);

    expect(fn () => $resolver->resolve($payload))
        ->toThrow(RuntimeException::class, 'OTP request failed: OTP request failed.');
});
