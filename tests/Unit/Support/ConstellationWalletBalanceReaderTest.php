<?php

declare(strict_types=1);

use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationWalletBalanceReader;

it('normalizes a wallet balance without losing the existing response', function (): void {
    $action = Mockery::mock(GetWalletBalance::class);
    $response = ['withdrawable_balance' => '12.34', 'wallet_balance' => '50.00', 'customer_no' => 'customer-1'];
    $action->shouldReceive('handle')->once()->with('wallet-1')->andReturn($response);
    $snapshot = (new ConstellationWalletBalanceReader($action))->readWalletBalance('wallet-1', 'PHP');

    expect($snapshot->balance)->toBe(12.34)
        ->and($snapshot->currency)->toBe('PHP')
        ->and($snapshot->customerId)->toBe('customer-1')
        ->and($snapshot->response)->toBe($response);
});
