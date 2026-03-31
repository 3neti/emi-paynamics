<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts\GetBankAccountsByAccountId;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class BankAccountsCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:bank-accounts {accountId} {--fake}';

    protected $description = 'List bank accounts for an account';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $accountId = $this->argument('accountId');
        $context = ['account_id' => $accountId];
        $this->logBefore($context);

        try {
            $result = GetBankAccountsByAccountId::run($accountId);
            $accounts = $result['data'] ?? [];
            $this->table(['Bank Account ID', 'Bank Code'], collect($accounts)->map(fn ($a) => [$a['bank_account_id'] ?? '', $a['bank_code'] ?? ''])->toArray());
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
