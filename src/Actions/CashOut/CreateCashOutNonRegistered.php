<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\CashOut;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateCashOutNonRegistered
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
            $data['account_no'] ?? '',
            $data['request_id'] ?? '',
            $data['amount'] ?? '',
            $data['bank_id'] ?? '',
            $data['ben_fname'] ?? '',
            $data['ben_lname'] ?? '',
            $data['ben_address'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        return $this->client->post('/integration/corp_wallet/withdraw_not_registered', $data)->json();
    }
}
