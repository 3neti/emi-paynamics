<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\ResendCashOutOtp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class ResendOtpCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:resend-otp {accountId} {requestId} {--fake}';

    protected $description = 'Resend OTP for a cash-out';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $data = ['account_id' => $this->argument('accountId'), 'request_id' => $this->argument('requestId')];
        $this->logBefore($data);

        try {
            $result = ResendCashOutOtp::run($data);
            $this->components->info($result['data'] ?? json_encode($result));
            $this->logAfter($data, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($data, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
