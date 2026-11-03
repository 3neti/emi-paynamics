<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PendingPayoutOtpInterpreter;

it('registers the provider OTP interpretation capability', function (): void {
    $interpreter = collect(app()->tagged('emi.pending-payout-otp-interpreters'))
        ->first(fn ($candidate) => $candidate instanceof PendingPayoutOtpInterpreter);

    expect($interpreter)->not->toBeNull()
        ->and($interpreter->reference(['request_id' => '  req-1  ']))->toBe('req-1')
        ->and($interpreter->reference(['request_id' => ' ']))->toBeNull()
        ->and($interpreter->pendingMetadata(
            ['request_id' => 'req-1', 'amount' => '75.00'],
            ['data' => 'OTP sent'],
        ))->toMatchArray([
            'provider' => 'paynamics', 'reference_id' => 'req-1', 'amount' => '75.00', 'target' => 'OTP sent',
        ]);
});
