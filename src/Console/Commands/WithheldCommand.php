<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets\GetWithheldByWalletId;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class WithheldCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:withheld {walletId} {--fake}';

    protected $description = 'Get withheld funds for a wallet';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $walletId = $this->argument('walletId');
        $context = ['wallet_id' => $walletId];
        $this->logBefore($context);

        try {
            $result = GetWithheldByWalletId::run($walletId);
            $data = $result['data'] ?? [];

            if (empty($data)) {
                $this->components->info('No withheld funds.');
            } else {
                $this->table(['Request ID', 'Amount', 'Status'], collect($data)->map(fn ($w) => [$w['request_id'] ?? '', $w['withheld_amount'] ?? '', $w['status'] ?? ''])->toArray());
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
