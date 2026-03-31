<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWalletDetails
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(string $walletIdOrExternalUid): array
    {
        $response = $this->client->get(
            "/integration/corp_wallet/view_wallet/{$walletIdOrExternalUid}",
        );

        return $response->json();
    }
}
