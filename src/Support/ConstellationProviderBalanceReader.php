<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use DateTimeImmutable;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\ProviderBalanceReader;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceObservationData;
use LBHurtado\EmiCore\Data\Providers\ProviderBalanceRequestData;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;
use RuntimeException;

final readonly class ConstellationProviderBalanceReader implements ProviderBalanceReader
{
    private const Provider = 'paynamics_constellation';

    public function __construct(
        private GetWalletBalance $balances,
    ) {}

    public function providerCode(): string
    {
        return self::Provider;
    }

    public function readBalance(
        ProviderBalanceRequestData $request,
    ): ProviderBalanceObservationData {
        if (mb_strtolower(trim($request->provider)) !== self::Provider) {
            throw new InvalidArgumentException('Paynamics balance provider mismatch.');
        }

        $walletId = trim((string) config('constellation.funding.wallet_id'));

        if ($walletId === '') {
            throw new RuntimeException('Paynamics funding account is not configured.');
        }

        $response = $this->balances->handle($walletId);
        $balance = data_get($response, 'data.balance');

        if (($response['success'] ?? false) !== true || ! is_scalar($balance)) {
            throw new RuntimeException('Paynamics did not return a valid balance observation.');
        }

        $currency = mb_strtoupper(trim($request->currency));
        $amountMinor = $this->minorUnits((string) $balance);
        $observedAt = DateTimeImmutable::createFromInterface(now());
        $evidenceReference = 'paynamics-balance:'.hash('sha256', implode('|', [
            $request->connectionReference,
            $request->settlementResourceReference,
            $currency,
            (string) $amountMinor,
            $observedAt->format(DATE_ATOM),
        ]));

        return new ProviderBalanceObservationData(
            provider: self::Provider,
            connectionReference: $request->connectionReference,
            settlementResourceReference: $request->settlementResourceReference,
            amountMinor: $amountMinor,
            currency: $currency,
            observedAt: $observedAt,
            evidenceReference: $evidenceReference,
        );
    }

    private function minorUnits(string $amount): int
    {
        $amount = trim($amount);

        if (preg_match('/^(?<whole>0|[1-9]\d*)(?:\.(?<fraction>\d{1,2}))?$/', $amount, $matches) !== 1) {
            throw new RuntimeException('Paynamics returned an invalid balance amount.');
        }

        $whole = (int) $matches['whole'];
        $fraction = str_pad((string) ($matches['fraction'] ?? ''), 2, '0');

        return ($whole * 100) + (int) $fraction;
    }
}
