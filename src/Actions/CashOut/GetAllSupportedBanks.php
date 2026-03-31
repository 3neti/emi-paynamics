<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashOut;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAllSupportedBanks
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        return $this->client->get('/integration/corp_wallet/get_all_supported_banks')->json();
    }
}
