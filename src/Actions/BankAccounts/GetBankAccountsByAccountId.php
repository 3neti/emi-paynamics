<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBankAccountsByAccountId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $accountId): array
    {
        return $this->client->get("/integration/corp_wallet/bank_account/fetch_by_mid/{$accountId}")->json();
    }
}
