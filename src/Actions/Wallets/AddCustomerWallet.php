<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Wallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class AddCustomerWallet
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
            $data['first_name'] ?? '',
            $data['middle_name'] ?? '',
            $data['last_name'] ?? '',
            $data['email'] ?? '',
            $data['mobile_no'] ?? '',
            $data['address'] ?? '',
            $data['zip'] ?? '',
            $data['city'] ?? '',
            $data['state'] ?? '',
            $data['country'] ?? '',
            $data['username'] ?? '',
            $data['password'] ?? '',
            $data['profile_type'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post(
            '/integration/corp_wallet/customer_wallet/add',
            $data,
        );

        return $response->json();
    }
}
