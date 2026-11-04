<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiCore\Contracts\ProviderWalletBalanceReader;
use LBHurtado\EmiCore\Data\Providers\ProviderWalletBalanceData;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;

final readonly class ConstellationWalletBalanceReader implements ProviderWalletBalanceReader
{
    public function __construct(private GetWalletBalance $balances) {}

    public function providerCode(): string
    {
        return 'paynamics_constellation';
    }

    public function readWalletBalance(string $walletId, string $fallbackCurrency): ProviderWalletBalanceData
    {
        $response = $this->balances->handle($walletId);
        $customer = data_get($response, 'customer_id') ?? data_get($response, 'customer_no');

        return new ProviderWalletBalanceData(
            balance: (float) (data_get($response, 'withdrawable_balance') ?? data_get($response, 'wallet_balance') ?? data_get($response, 'current_balance') ?? 0),
            currency: (string) data_get($response, 'currency', $fallbackCurrency),
            customerId: is_scalar($customer) ? (string) $customer : null,
            metadata: array_filter([
                'wallet_balance' => data_get($response, 'wallet_balance'),
                'current_balance' => data_get($response, 'current_balance'),
                'withdrawable_balance' => data_get($response, 'withdrawable_balance'),
                'remaining_wallet_limit' => data_get($response, 'remaining_wallet_limit'),
                'remaining_outflow_limit' => data_get($response, 'remaining_outflow_limit'),
                'remaining_inflow_limit' => data_get($response, 'remaining_inflow_limit'),
            ], static fn (mixed $value): bool => $value !== null),
            response: $response,
        );
    }
}
