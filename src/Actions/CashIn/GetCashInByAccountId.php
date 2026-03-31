<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashIn;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCashInByAccountId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $accountId): array
    {
        return $this->client->get("/integration/corp_wallet/cashin/get_cashin_by_mid/{$accountId}")->json();
    }
}
