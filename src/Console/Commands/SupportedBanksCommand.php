<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\GetAllSupportedBanks;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class SupportedBanksCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:supported-banks {--fake}';

    protected $description = 'List all supported banks for cash-out';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $context = ['action' => 'supported-banks'];
        $this->logBefore($context);

        try {
            $result = GetAllSupportedBanks::run();
            $banks = $result['data'] ?? [];

            $this->table(
                ['Code', 'Name', 'Method', 'Fee', 'Savings', 'Checking', 'Current', 'Corporate'],
                collect($banks)->map(fn ($b) => [
                    $b['code'] ?? '',
                    $b['name'] ?? '',
                    $b['disbursement_method'] ?? '',
                    $b['fee'] ?? '',
                    ($b['savings'] ?? false) ? '✓' : '',
                    ($b['checking'] ?? false) ? '✓' : '',
                    ($b['current'] ?? false) ? '✓' : '',
                    ($b['corporate'] ?? false) ? '✓' : '',
                ])->toArray()
            );

            $this->components->info(count($banks).' bank entries found (same bank may appear multiple times for different disbursement methods).');
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
