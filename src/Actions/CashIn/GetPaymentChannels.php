<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashIn;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPaymentChannels
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(): array
    {
        return $this->client->get('/integration/corp_wallet/get_all_active_pchannels')->json();
    }
}
