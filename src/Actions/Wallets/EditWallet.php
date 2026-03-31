<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class EditWallet
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * PATCH semantics — only provided fields are sent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(string $walletId, array $data): array
    {
        $signature = $this->signer->generateSignature(
            [json_encode($data)],
            config('constellation.merchant_key'),
        );

        $data['signature'] = $signature;

        $response = $this->client->patch(
            "/integration/corp_wallet/edit_wallet/{$walletId}",
            $data,
        );

        return $response->json();
    }
}
