<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class SetWalletThreshold
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $walletId, string $amount): array
    {
        $signature = $this->signer->generateSignature(
            [$walletId, $amount],
            config('constellation.merchant_key'),
        );

        return $this->client->put(
            "/integration/corp_wallet/threshold/{$walletId}",
            ['amount' => $amount, 'signature' => $signature],
        )->json();
    }
}
