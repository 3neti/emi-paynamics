<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAirtimeLoadDetailsByPartnerId
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /** @return array<string, mixed> */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['partner_id'] ?? '',
            $data['start_date'] ?? '',
            $data['end_date'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post('/digitalgoods/transaction/airtime_load_details_mid', $data);

        return $response->json() ?? ['success' => false, 'data' => [], 'error' => 'Empty response (HTTP '.$response->status().')'];
    }
}
