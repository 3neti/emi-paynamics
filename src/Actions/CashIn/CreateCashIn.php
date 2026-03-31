<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashIn;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCashIn
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
            $data['request_id'] ?? '',
            $data['wallet_id'] ?? '',
            $data['pmethod'] ?? '',
            $data['pchannel'] ?? '',
            $data['amount'] ?? '',
            $data['response_url'] ?? '',
            $data['cancel_url'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post(
            '/integration/corp_wallet/cashin/create',
            $data,
        );

        return $response->json();
    }
}
