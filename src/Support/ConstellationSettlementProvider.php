<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\PayoutProviderIdentity;
use LBHurtado\EmiCore\Contracts\ProviderReadinessProbe;
use LBHurtado\EmiCore\Contracts\SettlementProvider;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityManifestData;
use LBHurtado\EmiCore\Data\Providers\ProviderCapabilityReadinessData;
use LBHurtado\EmiCore\Data\Providers\ProviderReadinessRequestData;
use LBHurtado\EmiCore\Enums\ProviderCapability;

final readonly class ConstellationSettlementProvider implements PayoutProviderIdentity, ProviderReadinessProbe, SettlementProvider
{
    private const Provider = 'paynamics_constellation';

    public function __construct(
        private ConstellationSystemReadiness $readiness,
    ) {}

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function acceptsPayoutProvider(string $provider): bool
    {
        return in_array(strtolower(trim($provider)), ['paynamics', self::Provider], true);
    }

    public function manifest(): ProviderCapabilityManifestData
    {
        return new ProviderCapabilityManifestData(
            provider: self::Provider,
            label: 'Paynamics Constellation',
            capabilities: [
                ProviderCapability::ReadinessProbe,
                ProviderCapability::BalanceRead,
                ProviderCapability::FundingEvidenceRead,
                ProviderCapability::FundingInstructionIssue,
            ],
        );
    }

    public function checkReadiness(
        ProviderReadinessRequestData $request,
    ): ProviderCapabilityReadinessData {
        if (mb_strtolower(trim($request->provider)) !== self::Provider) {
            throw new InvalidArgumentException('Paynamics readiness provider mismatch.');
        }

        $capabilities = $request->requiredCapabilities === []
            ? $this->manifest()->capabilities
            : $request->requiredCapabilities;
        $checks = [];
        $issues = [];
        $providerReady = null;

        foreach ($capabilities as $capability) {
            $missing = $this->missingConfiguration($capability);

            if ($missing === [] && $this->manifest()->supports($capability)) {
                $providerReady ??= $this->readiness->check()->ready;

                if (! $providerReady) {
                    $missing[] = 'provider-probe-failed';
                }
            }

            $checks[$capability->value] = $missing === [];

            if ($missing !== []) {
                $issues[$capability->value] = $missing;
            }
        }

        return new ProviderCapabilityReadinessData(
            provider: self::Provider,
            connectionReference: $request->connectionReference,
            checks: $checks,
            issues: $issues,
            checkedAt: DateTimeImmutable::createFromInterface(now()),
        );
    }

    /**
     * @return list<string>
     */
    private function missingConfiguration(ProviderCapability $capability): array
    {
        if (! $this->manifest()->supports($capability)) {
            return ['capability-not-supported'];
        }

        $keys = [
            'base_url',
            'username',
            'password',
            'settlement_wallet_id',
            'revenue_wallet_id',
        ];

        if (in_array($capability, [
            ProviderCapability::BalanceRead,
            ProviderCapability::FundingEvidenceRead,
            ProviderCapability::FundingInstructionIssue,
        ], true)) {
            $keys[] = 'funding.wallet_id';
        }

        if (in_array($capability, [
            ProviderCapability::FundingEvidenceRead,
            ProviderCapability::FundingInstructionIssue,
        ], true)) {
            $keys[] = 'merchant_key';
        }

        if ($capability === ProviderCapability::FundingInstructionIssue) {
            $keys[] = 'funding.payment_method';
            $keys[] = 'funding.payment_channel';
            $keys[] = 'funding.response_url';
            $keys[] = 'funding.cancel_url';
        }

        return array_values(array_map(
            static fn (string $key): string => "missing-config:{$key}",
            array_filter(
                $keys,
                static fn (string $key): bool => trim((string) config(
                    "constellation.{$key}",
                )) === '',
            ),
        ));
    }
}
