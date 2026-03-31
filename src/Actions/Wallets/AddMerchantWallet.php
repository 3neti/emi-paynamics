<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMerchantWallet
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['company_name'] ?? '',
            $data['tin'] ?? '',
            $data['email'] ?? '',
            $data['website'] ?? '',
            $data['username'] ?? '',
            $data['password'] ?? '',
            $data['account_first_name'] ?? '',
            $data['account_last_name'] ?? '',
            $data['profile_type'] ?? '',
            $data['mobile_no'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post(
            '/integration/corp_wallet/merchant_wallet/add',
            $data,
        );

        return $response->json();
    }
}
