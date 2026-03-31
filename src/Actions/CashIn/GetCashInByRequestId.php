<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashIn;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCashInByRequestId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $requestId): array
    {
        return $this->client->get("/integration/corp_wallet/cashin/get_cashin_by_reqid/{$requestId}")->json();
    }
}
