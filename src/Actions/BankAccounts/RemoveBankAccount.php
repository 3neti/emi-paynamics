<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveBankAccount
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $bankAccountId): array
    {
        return $this->client->delete("/integration/corp_wallet/bank_account/remove/{$bankAccountId}")->json();
    }
}
