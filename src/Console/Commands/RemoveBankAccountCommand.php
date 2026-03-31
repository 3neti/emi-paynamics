<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts\RemoveBankAccount;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class RemoveBankAccountCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:remove-bank-account {bankAccountId} {--fake}';

    protected $description = 'Remove a registered bank account';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $bankAccountId = $this->argument('bankAccountId');
        $context = ['bank_account_id' => $bankAccountId];
        $this->logBefore($context);

        try {
            $result = RemoveBankAccount::run($bankAccountId);
            $this->components->info('Bank account removed.');
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
