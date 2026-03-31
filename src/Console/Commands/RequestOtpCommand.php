<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutOtp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class RequestOtpCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:request-otp {--fake}';

    protected $description = 'Request OTP for a cash-out transaction';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'account_id' => text('Account ID'),
            'bank_account_no' => text('Bank account number'),
            'request_id' => text('Request ID'),
            'reason' => text('Reason', default: 'Cash out'),
            'amount' => text('Amount'),
        ];

        $context = ['account_id' => $data['account_id'], 'request_id' => $data['request_id']];
        $this->logBefore($context);

        try {
            $result = CreateCashOutOtp::run($data);
            $this->components->info($result['data'] ?? json_encode($result));
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
