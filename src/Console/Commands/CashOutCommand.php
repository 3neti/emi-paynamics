<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOut;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class CashOutCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:cash-out {accountId} {amount} {--fake}';

    protected $description = 'Initiate a cash-out (withdrawal)';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'account_id' => $this->argument('accountId'),
            'amount' => $this->argument('amount'),
            'request_id' => text('Request ID', default: 'CO-'.Str::uuid()->toString()),
            'bank_account_no' => text('Bank account number'),
            'reason' => text('Reason', default: 'Cash out via console'),
            'wallet_id' => text('Wallet ID'),
            'device_information' => ['device_id' => 'console', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];

        $context = ['account_id' => $data['account_id'], 'amount' => $data['amount'], 'request_id' => $data['request_id']];
        $this->logBefore($context);

        try {
            $result = CreateCashOut::run($data);
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
