<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutNonRegistered;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class CashOutNrCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:cash-out-nr {accountId} {amount} {--fake}';

    protected $description = 'Cash out to a non-registered bank account';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'account_id' => $this->argument('accountId'),
            'amount' => $this->argument('amount'),
            'request_id' => text('Request ID', default: 'CONR-'.Str::uuid()->toString()),
            'account_no' => text('Destination account number'),
            'bank_id' => text('Bank ID'),
            'ben_fname' => text('Beneficiary first name'),
            'ben_lname' => text('Beneficiary last name'),
            'ben_address' => text('Beneficiary address'),
            'reason' => text('Reason', default: 'Cash out NR via console'),
            'wallet_id' => text('Wallet ID'),
            'device_information' => ['device_id' => 'console', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];

        $context = ['account_id' => $data['account_id'], 'amount' => $data['amount'], 'request_id' => $data['request_id']];
        $this->logBefore($context);

        try {
            $result = CreateCashOutNonRegistered::run($data);
            $this->components->twoColumnDetail('Request ID', $result['request_id'] ?? $data['request_id']);
            $this->components->twoColumnDetail('Status', $result['response_message'] ?? '');
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
