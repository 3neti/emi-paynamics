<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Enums\ProviderCapability;
use LBHurtado\EmiPaynamicsConstellation\Configuration\PaynamicsDeploymentContributor;

it('contributes sanitized Paynamics deployment requirements', function (): void {
    $contributor = new PaynamicsDeploymentContributor;
    $variables = collect($contributor->environmentVariables())->keyBy('key');
    $connection = $contributor->connectionTemplates()[0];

    expect($contributor->providerCode())->toBe('paynamics_constellation')
        ->and($variables)->toHaveKeys([
            'CONSTELLATION_USERNAME',
            'CONSTELLATION_PASSWORD',
            'CONSTELLATION_MERCHANT_KEY',
            'CONSTELLATION_FUNDING_WALLET_ID',
        ])
        ->and($variables['CONSTELLATION_PASSWORD']->secret)->toBeTrue()
        ->and($variables['CONSTELLATION_PASSWORD']->safeExample)->toBeNull()
        ->and($connection->reference)->toBe('paynamics-primary')
        ->and($connection->requiredCapabilities)->toContain(ProviderCapability::FundingInstructionIssue);
});
