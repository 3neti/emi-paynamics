<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transactions\GetTransactionByWalletId;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class TransactionsCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:transactions {walletId} {--fake}';

    protected $description = 'Get transactions by wallet ID';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $walletId = $this->argument('walletId');
        $context = ['wallet_id' => $walletId];
        $this->logBefore($context);

        try {
            $result = GetTransactionByWalletId::run($walletId);
            $data = $result['data'] ?? [];

            if (empty($data)) {
                $this->components->info('No transactions found.');
            } else {
                $this->components->info(count($data).' transaction(s) found.');
                $this->table(['Field', 'Value'], collect($data)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->toArray());
            }
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
