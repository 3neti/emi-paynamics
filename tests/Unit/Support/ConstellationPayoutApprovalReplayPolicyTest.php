<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationPayoutApprovalReplayPolicy;

it('preserves the verified OTP and reference checks for replay', function (): void {
    $policy = app(ConstellationPayoutApprovalReplayPolicy::class);
    $payload = ['approval' => ['provider' => 'paynamics', 'reference_id' => 'request-1'], 'otp' => ['verified' => true]];
    $metadata = fn (): array => ['disbursement' => ['transaction_id' => 'request-1', 'status' => 'otp pending']];
    $exception = new RuntimeException('Failed to redeem voucher');

    expect($policy->shouldResume($payload, $metadata, $exception))->toBeTrue()
        ->and($policy->shouldResume([...$payload, 'otp' => ['verified' => false]], $metadata, $exception))->toBeFalse()
        ->and($policy->shouldResume($payload, fn (): array => ['disbursement' => ['transaction_id' => 'other', 'status' => 'otp pending']], $exception))->toBeFalse()
        ->and($policy->shouldResume($payload, fn (): array => ['disbursement' => ['transaction_id' => 'request-1', 'status' => 'complete']], $exception))->toBeFalse();
});

it('does not load voucher metadata for an already-redeemed exception', function (): void {
    $policy = app(ConstellationPayoutApprovalReplayPolicy::class);
    expect($policy->shouldResume(
        ['approval' => ['provider' => 'paynamics'], 'otp' => ['verified' => true]],
        fn (): array => throw new RuntimeException('metadata should not be loaded'),
        new RuntimeException('Voucher has already been redeemed'),
    ))->toBeTrue();
});

it('recognizes deferred pending approval without presenting an unfinished payout as complete', function (): void {
    $policy = app(ConstellationPayoutApprovalReplayPolicy::class);
    $metadata = fn (): array => [];

    expect($policy->isDeferredApprovalPending([
        'claim_type' => 'redeem', 'status' => 'redeemed', 'messages' => [], 'disbursement' => null,
    ], $metadata))->toBeTrue()
        ->and($policy->isDeferredApprovalPending([
            'claim_type' => 'redeem', 'status' => 'redeemed', 'messages' => [], 'disbursement' => ['status' => 'completed'],
        ], $metadata))->toBeFalse();
});
