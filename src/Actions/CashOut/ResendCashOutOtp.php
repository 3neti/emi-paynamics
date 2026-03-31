<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashOut;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class ResendCashOutOtp
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
            $data['account_id'] ?? '',
            $data['request_id'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        return $this->client->post('/integration/corp_wallet/resend_otp', $data)->json();
    }
}
