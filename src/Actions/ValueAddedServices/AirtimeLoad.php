<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class AirtimeLoad
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Must contain request_id, amount, sku, recipient_name, recipient_mobile
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['request_id'] ?? '',
            $data['amount'] ?? '',
            $data['sku'] ?? '',
            $data['recipient_name'] ?? '',
            $data['recipient_mobile'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post('/digitalgoods/transaction/airtime_load', $data);

        return $response->json() ?? ['success' => false, 'data' => [], 'error' => 'Empty response (HTTP '.$response->status().')'];
    }
}
