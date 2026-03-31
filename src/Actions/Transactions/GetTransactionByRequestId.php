<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Transactions;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetTransactionByRequestId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $requestId): array
    {
        $response = $this->client->get(
            "/integration/corp_wallet/elastic_trx/get_by_request_id/{$requestId}",
        );

        return $response->json();
    }
}
