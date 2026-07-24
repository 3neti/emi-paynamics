<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationProviderBalanceReader;

it('normalizes an authoritative Paynamics funding balance', function () {
    config()->set('constellation.funding.wallet_id', 'CNSTWLLTFUNDING');
    $balances = Mockery::mock(GetWalletBalance::class);
    $balances->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTFUNDING')
        ->andReturn([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTFUNDING',
                'balance' => '2000000.25',
            ],
        ]);

    $observation = (new ConstellationProviderBalanceReader($balances))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'PAYNAMICS_CONSTELLATION',
            connectionReference: 'paynamics-primary',
            settlementResourceReference: 'resource:paynamics:corporate-wallet',
            currency: 'PHP',
        ),
    );

    expect($observation->amountMinor)->toBe(200_000_025)
        ->and($observation->currency)->toBe('PHP')
        ->and($observation->evidenceReference)->toStartWith('paynamics-balance:')
        ->and($observation->evidenceReference)->not->toContain('CNSTWLLTFUNDING');
});

it('rejects unsuccessful and over-precision balance responses', function (array $response) {
    config()->set('constellation.funding.wallet_id', 'CNSTWLLTFUNDING');
    $balances = Mockery::mock(GetWalletBalance::class);
    $balances->shouldReceive('handle')->once()->andReturn($response);

    expect(fn () => (new ConstellationProviderBalanceReader($balances))->readBalance(
        new ProviderBalanceRequestData(
            provider: 'paynamics_constellation',
            connectionReference: 'paynamics-primary',
            settlementResourceReference: 'resource:paynamics:corporate-wallet',
            currency: 'PHP',
        ),
    ))->toThrow(RuntimeException::class);
})->with([
    'provider failure' => [[
        'success' => false,
        'data' => [],
    ]],
    'over precision' => [[
        'success' => true,
        'data' => ['balance' => '10.123'],
    ]],
]);
