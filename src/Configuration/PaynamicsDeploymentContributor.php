<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Configuration;

use LBHurtado\EmiCore\Contracts\DeploymentConnectionContributor;
use LBHurtado\EmiCore\Contracts\DeploymentEnvironmentContributor;
use LBHurtado\EmiCore\Contracts\ProviderCommissioningFingerprintInputs;
use LBHurtado\EmiCore\Data\Configuration\EnvironmentVariableData;
use LBHurtado\EmiCore\Data\Configuration\ProviderConnectionTemplateData;
use LBHurtado\EmiCore\Enums\ProviderCapability;

final class PaynamicsDeploymentContributor implements DeploymentConnectionContributor, DeploymentEnvironmentContributor, ProviderCommissioningFingerprintInputs
{
    public function settlementAccountFingerprintInputs(): array
    {
        return [
            'settlement_wallet_id' => config('constellation.settlement_wallet_id'),
            'revenue_wallet_id' => config('constellation.revenue_wallet_id'),
            'funding_wallet_id' => config('constellation.funding.wallet_id'),
        ];
    }

    public function providerCode(): string
    {
        return 'paynamics_constellation';
    }

    public function environmentVariables(): array
    {
        return [
            $this->variable('CONSTELLATION_BASE_URL', 'Paynamics Constellation API base URL.', 'constellation.base_url', 'https://asterism.payserv.net/v1'),
            $this->variable('CONSTELLATION_USERNAME', 'Paynamics API username.', 'constellation.username'),
            $this->variable('CONSTELLATION_PASSWORD', 'Paynamics API password.', 'constellation.password', secret: true),
            $this->variable('CONSTELLATION_MERCHANT_KEY', 'Paynamics merchant signing key.', 'constellation.merchant_key', secret: true),
            $this->variable('CONSTELLATION_NOTIFICATION_URL', 'Public Paynamics notification URL.', 'constellation.notification_url', 'https://example.test/webhooks/paynamics'),
            $this->variable('CONSTELLATION_SETTLEMENT_WALLET_ID', 'Paynamics settlement wallet identifier.', 'constellation.settlement_wallet_id', secret: true),
            $this->variable('CONSTELLATION_REVENUE_WALLET_ID', 'Paynamics revenue wallet identifier.', 'constellation.revenue_wallet_id', secret: true),
            $this->variable('CONSTELLATION_FUNDING_WALLET_ID', 'Paynamics funding wallet identifier.', 'constellation.funding.wallet_id', secret: true),
            $this->variable('CONSTELLATION_FUNDING_PAYMENT_METHOD', 'Paynamics funding payment method.', 'constellation.funding.payment_method', 'onlinebanktransfer'),
            $this->variable('CONSTELLATION_FUNDING_PAYMENT_CHANNEL', 'Paynamics funding payment channel.', 'constellation.funding.payment_channel', 'ubp_online'),
            $this->variable('CONSTELLATION_FUNDING_RESPONSE_URL', 'Paynamics funding response URL.', 'constellation.funding.response_url', 'https://example.test/x/funding/paynamics/return'),
            $this->variable('CONSTELLATION_FUNDING_CANCEL_URL', 'Paynamics funding cancellation URL.', 'constellation.funding.cancel_url', 'https://example.test/x/funding/paynamics/cancel'),
        ];
    }

    public function connectionTemplates(): array
    {
        return [new ProviderConnectionTemplateData(
            reference: 'paynamics-primary',
            provider: $this->providerCode(),
            currency: 'PHP',
            inventoryReference: 'inventory:paynamics:wallet-float',
            settlementResourceReference: 'resource:paynamics:corporate-wallet',
            settlementResourceType: 'emi_wallet_float',
            custodyMode: 'provider_projection',
            requiredCapabilities: [
                ProviderCapability::ReadinessProbe,
                ProviderCapability::BalanceRead,
                ProviderCapability::FundingEvidenceRead,
                ProviderCapability::FundingInstructionIssue,
            ],
        )];
    }

    private function variable(
        string $key,
        string $description,
        string $configPath,
        ?string $safeExample = null,
        bool $secret = false,
    ): EnvironmentVariableData {
        return new EnvironmentVariableData(
            key: $key,
            description: $description,
            category: 'Paynamics',
            configPath: $configPath,
            safeExample: $safeExample,
            secret: $secret,
            requiredForProviders: [$this->providerCode()],
        );
    }
}
