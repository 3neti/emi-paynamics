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
        $requestId = (string) ($data['request_id'] ?? data_get($data, 'payload.request_id', ''));
        $remarks = (string) ($data['remarks'] ?? data_get($data, 'payload.remarks', ''));

        $signature = $this->signer->generateSignature([
            $data['amount'] ?? '',
            $data['source_wallet_id'] ?? '',
            $data['destination_wallet_id'] ?? '',
            $requestId,
            $remarks,
        ], config('constellation.merchant_key'));

        $payload = [
            'amount' => $data['amount'] ?? '',
            'source_wallet_id' => $data['source_wallet_id'] ?? '',
            'destination_wallet_id' => $data['destination_wallet_id'] ?? '',
            'signature' => $signature,
            'payload' => [
                'request_id' => $requestId,
                'remarks' => $remarks,
            ],
            'device_information' => $data['device_information'] ?? [],
            'network_information' => $data['network_information'] ?? [],
            'meta_data' => $data['meta_data'] ?? new \stdClass,
        ];

        $response = $this->client->post(
            '/integration/corp_wallet/transfer_pre',
            $payload,
        );

        return $response->json();
    }
}
