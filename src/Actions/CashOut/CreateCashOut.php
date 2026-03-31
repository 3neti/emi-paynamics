<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashOut;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCashOut
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
            $data['account_id'] ?? '',
            $data['bank_account_no'] ?? '',
            $data['request_id'] ?? '',
            $data['amount'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post(
            '/integration/corp_wallet/withdraw_request',
            $data,
        );

        return $response->json();
    }
}
