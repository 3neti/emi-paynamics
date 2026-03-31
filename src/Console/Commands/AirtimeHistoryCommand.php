<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\GetAirtimeLoadDetailsByPartnerId;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class AirtimeHistoryCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:airtime-history {--fake}';

    protected $description = 'Get airtime load history by partner ID';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $data = [
            'partner_id' => text('Partner ID'),
            'start_date' => text('Start date (yyyy-MM-dd)', default: now()->subMonth()->format('Y-m-d')),
            'end_date' => text('End date (yyyy-MM-dd)', default: now()->format('Y-m-d')),
        ];
        $this->logBefore($data);

        try {
            $result = GetAirtimeLoadDetailsByPartnerId::run($data);
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
