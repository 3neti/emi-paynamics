<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashOut;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCashOutByRequestId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $requestId): array
    {
        return $this->client->get("/integration/corp_wallet/withdraw/get_by_request_id/{$requestId}")->json();
    }
}
