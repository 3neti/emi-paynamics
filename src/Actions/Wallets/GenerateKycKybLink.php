<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateKycKybLink
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Must contain account_id, level
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['account_id'] ?? '',
            $data['level'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post(
            '/integration/corp_wallet/kyc_request',
            $data,
        );

        return $response->json();
    }
}
