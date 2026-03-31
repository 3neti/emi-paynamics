<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\GetBillsPaymentDetails;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class BillStatusCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:bill-status {requestId} {--fake}';

    protected $description = 'Check bills payment status by request ID';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $requestId = $this->argument('requestId');
        $context = ['request_id' => $requestId];
        $this->logBefore($context);

        try {
            $result = GetBillsPaymentDetails::run($requestId);
            $this->table(['Field', 'Value'], collect($result['data'] ?? $result)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->toArray());
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
