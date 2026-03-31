<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\GetAllSupportedBanks;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetAvailableProfiles;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class ProbeCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:probe {--fake : Use fake HTTP responses}';

    protected $description = 'Smoke test — verify Constellation API credentials';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $context = ['action' => 'probe'];
        $this->logBefore($context);

        try {
            $profiles = GetAvailableProfiles::run();
            $banks = GetAllSupportedBanks::run();

            $this->components->info('Profiles:');
            $this->components->twoColumnDetail('Consumer', collect($profiles['data']['consumer_profiles'] ?? [])->pluck('id')->implode(', '));
            $this->components->twoColumnDetail('Merchant', collect($profiles['data']['merchant_profiles'] ?? [])->pluck('id')->implode(', '));

            $bankCount = is_array($banks['data'] ?? null) ? count($banks['data']) : 0;
            $this->components->twoColumnDetail('Supported Banks', (string) $bankCount);

            $this->components->success('API credentials verified successfully.');
            $this->logAfter($context, $profiles);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error("Probe failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
