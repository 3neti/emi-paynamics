<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Transfers;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class PreTransfer
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Must contain amount, source_wallet_id, destination_wallet_id, request_id, remarks
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['amount'] ?? '',
            $data['source_wallet_id'] ?? '',
            $data['destination_wallet_id'] ?? '',
            $data['request_id'] ?? '',
            $data['remarks'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post(
            '/integration/corp_wallet/transfer_pre',
            $data,
        );

        return $response->json();
    }
}
