<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use Lorisleiva\Actions\Concerns\AsAction;

class EditBankAccount
{
    use AsAction;

    public function __construct(
        private ConstellationClient $client,
        private ConstellationSigner $signer,
    ) {}

    /** @return array<string, mixed> */
    public function handle(string $bankAccountId, array $data): array
    {
        $signature = $this->signer->generateSignature([
            $data['bank_account_no'] ?? '',
            $data['acc_currency'] ?? '',
            $data['bank_id'] ?? '',
            $data['acct_type'] ?? '',
            $data['acc_holder_fname'] ?? '',
            $data['acc_holder_mname'] ?? '',
            $data['acc_holder_lname'] ?? '',
            $data['acc_holder_email'] ?? '',
            $data['acc_holder_phone'] ?? '',
            $data['acc_holder_address'] ?? '',
            $data['acc_holder_city'] ?? '',
            $data['acc_state'] ?? '',
            $data['country'] ?? '',
            $data['zip'] ?? '',
            $data['alias'] ?? '',
            $data['account_id'] ?? '',
        ], config('constellation.merchant_key'));

        $data['signature'] = $signature;

        return $this->client->put("/integration/corp_wallet/bank_account/update/{$bankAccountId}", $data)->json();
    }
}
