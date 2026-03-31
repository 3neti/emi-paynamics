<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\BankAccounts\AddBankAccount;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class AddBankAccountCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:add-bank-account {accountId} {--fake}';

    protected $description = 'Register a bank account for cash-out';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'account_id' => $this->argument('accountId'),
            'bank_account_no' => text('Bank account number'),
            'acc_currency' => text('Currency', default: 'PHP'),
            'bank_id' => text('Bank ID'),
            'acct_type' => text('Account type (savings/checking)', default: 'savings'),
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

        $context = ['account_id' => $data['account_id'], 'bank_id' => $data['bank_id']];
        $this->logBefore($context);

        try {
            $result = AddBankAccount::run($data);
            $this->components->info('Bank account registered.');
            $this->components->twoColumnDetail('Bank Account ID', $result['data']['bank_account_id'] ?? json_encode($result));
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
