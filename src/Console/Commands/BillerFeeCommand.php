<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\GetBillerFee;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class BillerFeeCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:biller-fee {--fake}';

    protected $description = 'Get fee for a biller';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $data = ['biller_code' => text('Biller code'), 'amount' => text('Amount')];
        $this->logBefore($data);

        try {
            $result = GetBillerFee::run($data);
            $this->table(['Field', 'Value'], collect($result['data'] ?? $result)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->toArray());
            $this->logAfter($data, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($data, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
