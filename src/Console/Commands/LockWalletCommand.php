<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\LockWallet;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class LockWalletCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:lock-wallet {walletId} {--fake}';

    protected $description = 'Lock a wallet';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $walletId = $this->argument('walletId');
        $context = ['wallet_id' => $walletId];
        $this->logBefore($context);

        try {
            $result = LockWallet::run($walletId);
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
