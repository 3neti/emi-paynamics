<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class AddPhantomWallet
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Must contain external_uid, expiration, profile_type
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['external_uid'] ?? '',
            $data['expiration'] ?? '',
            $data['profile_type'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        return $this->client->post(
            '/integration/corp_wallet/phantom_wallet/add',
            $data,
        )->json();
    }
}
