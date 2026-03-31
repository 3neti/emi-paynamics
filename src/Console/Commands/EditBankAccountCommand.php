<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts\EditBankAccount;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class EditBankAccountCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:edit-bank-account {bankAccountId} {--fake}';

    protected $description = 'Edit a registered bank account';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $bankAccountId = $this->argument('bankAccountId');

        $data = [
            'account_id' => text('Account ID'),
            'bank_account_no' => text('Bank account number'),
            'acc_currency' => text('Currency', default: 'PHP'),
            'bank_id' => text('Bank ID'),
            'acct_type' => text('Account type', default: 'savings'),
            'acc_holder_fname' => text('Holder first name'),
            'acc_holder_mname' => text('Holder middle name', default: ''),
            'acc_holder_lname' => text('Holder last name'),
            'acc_holder_email' => text('Holder email'),
            'acc_holder_phone' => text('Holder phone'),
            'acc_holder_address' => text('Holder address'),
            'acc_holder_city' => text('City'),
            'acc_state' => text('State'),
            'country' => text('Country', default: 'PH'),
            'zip' => text('ZIP'),
            'alias' => text('Alias', default: 'primary'),
        ];

        $context = ['bank_account_id' => $bankAccountId, 'account_id' => $data['account_id']];
        $this->logBefore($context);

        try {
            $result = EditBankAccount::run($bankAccountId, $data);
            $this->components->info('Bank account updated.');
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
