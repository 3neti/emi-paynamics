<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWithheldByPhantomWalletId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $phantomWalletId): array
    {
        return $this->client->get(
            "/integration/withhelds/phantom_wallet/{$phantomWalletId}",
        )->json();
    }
}
