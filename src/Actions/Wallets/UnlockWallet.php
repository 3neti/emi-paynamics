<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class UnlockWallet
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $walletId): array
    {
        $signature = $this->signer->generateSignature(
            [$walletId],
            config('constellation.merchant_key'),
        );

        return $this->client->post(
            '/integration/corp_wallet/unlock_wallet',
            ['wallet_id' => $walletId, 'signature' => $signature],
        )->json();
    }
}
