<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\GetBillers;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class BillersCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:billers {--fake}';

    protected $description = 'List available billers for bills payment';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $context = ['action' => 'billers'];
        $this->logBefore($context);

        try {
            $result = GetBillers::run();
            $billers = $result['data'] ?? [];
            $this->table(['Code', 'Name'], collect($billers)->map(fn ($b) => [$b['biller_code'] ?? '', $b['name'] ?? ''])->toArray());
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
