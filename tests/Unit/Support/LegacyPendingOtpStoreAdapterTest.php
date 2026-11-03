<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PendingPayoutOtpStore;
use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;

it('adapts the legacy OTP contract to the configured core store', function (): void {
    $store = Mockery::mock(PendingPayoutOtpStore::class);
    $store->shouldReceive('putPendingOtp')->once()->with(['request_id' => 'r1'], ['success' => true]);
    $store->shouldReceive('getSubmittedOtp')->once()->with(['request_id' => 'r1'])->andReturn('123456');
    app()->instance(PendingPayoutOtpStore::class, $store);

    $legacy = app(PendingOtpStore::class);
    $legacy->putPendingOtp(['request_id' => 'r1'], ['success' => true]);

    expect($legacy->getSubmittedOtp(['request_id' => 'r1']))->toBe('123456');
});

it('continues to resolve a host supplied legacy OTP store through the core contract', function (): void {
    $legacy = Mockery::mock(PendingOtpStore::class);
    app()->instance(PendingOtpStore::class, $legacy);

    expect(app(PendingPayoutOtpStore::class))->toBe($legacy);
});

it('fails clearly when neither OTP store is configured', function (): void {
    expect(fn () => app(PendingOtpStore::class))
        ->toThrow(RuntimeException::class, 'A pending payout OTP store must be configured.');
});
