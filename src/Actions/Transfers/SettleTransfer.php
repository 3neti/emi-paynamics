<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Transfers;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class SettleTransfer
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Must contain request_id, remarks
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['request_id'] ?? '',
            $data['remarks'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post(
            '/integration/corp_wallet/transfer_settle',
            $data,
        );

        return $response->json();
    }
}
