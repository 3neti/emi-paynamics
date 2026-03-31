<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class WalletBalanceCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:wallet-balance {walletId} {--fake}';

    protected $description = 'Get wallet balance and limits from Constellation';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $walletId = $this->argument('walletId');
        $context = ['wallet_id' => $walletId];
        $this->logBefore($context);

        try {
            $result = GetWalletBalance::run($walletId);

            $this->table(
                ['Field', 'Value'],
                collect($result)->map(fn ($v, $k) => [$k, (string) $v])->values()->toArray()
            );

            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
