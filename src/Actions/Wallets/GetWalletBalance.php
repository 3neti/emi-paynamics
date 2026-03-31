<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWalletBalance
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $walletId): array
    {
        $response = $this->client->get(
            "/integration/corp_wallet/check_balance/{$walletId}",
        );

        return $response->json();
    }
}
