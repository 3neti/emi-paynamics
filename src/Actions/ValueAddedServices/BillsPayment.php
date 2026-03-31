<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class BillsPayment
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Must contain request_id, biller_code, biller_fee, payee_name, payee_mobile, payee_email
     * @return array<string, mixed>
     */
    public function handle(array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['request_id'] ?? '',
            $data['biller_code'] ?? '',
            $data['biller_fee'] ?? '',
            $data['payee_name'] ?? '',
            $data['payee_mobile'] ?? '',
            $data['payee_email'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        $response = $this->client->post('/digitalgoods/transaction/bills_payment', $data);

        return $response->json() ?? ['success' => false, 'data' => [], 'error' => 'Empty response (HTTP '.$response->status().')'];
    }
}
