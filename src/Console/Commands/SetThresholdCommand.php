<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\SetWalletThreshold;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class SetThresholdCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:set-threshold {walletId} {amount} {--fake}';

    protected $description = 'Set wallet threshold';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $walletId = $this->argument('walletId');
        $amount = $this->argument('amount');
        $context = ['wallet_id' => $walletId, 'amount' => $amount];
        $this->logBefore($context);

        try {
            $result = SetWalletThreshold::run($walletId, $amount);
            $this->components->info($result['message'] ?? json_encode($result));
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
