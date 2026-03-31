<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWithheldByWalletId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $walletId): array
    {
        return $this->client->get(
            "/integration/withhelds/wallet/{$walletId}",
        )->json();
    }
}
