<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Transactions;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTransactionByWalletId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $walletId): array
    {
        $response = $this->client->get(
            "/integration/corp_wallet/elastic_trx/get_by_wallet_id/{$walletId}",
        );

        return $response->json();
    }
}
