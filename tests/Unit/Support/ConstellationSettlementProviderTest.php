<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\FundingInstructionIssuer;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Contracts\ProviderFundingEvidenceVerifier;
use LBHurtado\EmiCore\Contracts\SettlementProviderRegistryContract;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Data\SystemReadinessResult;
use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationFundingProviderAdapter;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationProviderBalanceReader;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSettlementProvider;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSystemReadiness;

it('advertises only the provider-neutral capabilities implemented by Paynamics', function () {
    $manifest = app(SettlementProviderRegistryContract::class)
        ->get('paynamics_constellation');

    expect($manifest->label)->toBe('Paynamics Constellation')
        ->and($manifest->capabilities)->toBe([
            ProviderCapability::ReadinessProbe,
            ProviderCapability::BalanceRead,
            ProviderCapability::FundingEvidenceRead,
            ProviderCapability::FundingInstructionIssue,
        ])
        ->and(app(ConstellationFundingProviderAdapter::class))
        ->toBeInstanceOf(FundingInstructionIssuer::class)
        ->toBeInstanceOf(ProviderFundingEvidenceVerifier::class)
        ->and(app(ConstellationProviderBalanceReader::class))
        ->toBeInstanceOf(ProviderBalanceReader::class);
});

it('reports missing capability configuration without calling or leaking provider state', function () {
    $probe = new class extends ConstellationSystemReadiness
    {
        public int $checks = 0;

        public function check(): SystemReadinessResult
        {
            $this->checks++;

            return SystemReadinessResult::fail('sensitive provider response');
        }
    };
    config()->set('constellation.username', null);
    config()->set('constellation.password', 'secret-value');

    $readiness = (new ConstellationSettlementProvider($probe))->checkReadiness(
        new ProviderReadinessRequestData(
            provider: 'paynamics_constellation',
            connectionReference: 'primary',
            requiredCapabilities: [ProviderCapability::FundingEvidenceRead],
        ),
    );

    expect($readiness->readyFor([ProviderCapability::FundingEvidenceRead]))->toBeFalse()
        ->and($readiness->issues[ProviderCapability::FundingEvidenceRead->value])
        ->toContain('missing-config:username')
        ->and($probe->checks)->toBe(0)
        ->and(json_encode($readiness->issues))->not->toContain('secret-value')
        ->not->toContain('sensitive provider response');
});

it('sanitizes a failed provider probe and reports a successful one', function () {
    foreach ([
        'settlement_wallet_id' => 'settlement-wallet',
        'revenue_wallet_id' => 'revenue-wallet',
        'funding.wallet_id' => 'funding-wallet',
    ] as $key => $value) {
        config()->set("constellation.{$key}", $value);
    }

    $failed = new class extends ConstellationSystemReadiness
    {
        public function check(): SystemReadinessResult
        {
            return SystemReadinessResult::fail('raw provider failure');
        }
    };
    $ready = new class extends ConstellationSystemReadiness
    {
        public function check(): SystemReadinessResult
        {
            return SystemReadinessResult::ok();
        }
    };
    $request = new ProviderReadinessRequestData(
        provider: 'PAYNAMICS_CONSTELLATION',
        connectionReference: 'primary',
        requiredCapabilities: [ProviderCapability::FundingEvidenceRead],
    );

    $failure = (new ConstellationSettlementProvider($failed))->checkReadiness($request);
    $success = (new ConstellationSettlementProvider($ready))->checkReadiness($request);

    expect($failure->issues)->toBe([
        ProviderCapability::FundingEvidenceRead->value => ['provider-probe-failed'],
    ])->and(json_encode($failure->issues))->not->toContain('raw provider failure')
        ->and($success->readyFor([ProviderCapability::FundingEvidenceRead]))->toBeTrue();
});
