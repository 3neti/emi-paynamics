<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets\GetWithheldByPhantomWalletId;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class WithheldPhantomCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:withheld-phantom {phantomWalletId} {--fake}';

    protected $description = 'Get withheld funds by phantom wallet ID';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $id = $this->argument('phantomWalletId');
        $context = ['phantom_wallet_id' => $id];
        $this->logBefore($context);

        try {
            $result = GetWithheldByPhantomWalletId::run($id);
            $data = $result['data'] ?? [];
            empty($data) ? $this->components->info('No withheld funds.') : $this->table(['Request ID', 'Amount', 'Status'], collect($data)->map(fn ($w) => [$w['request_id'] ?? '', $w['withheld_amount'] ?? '', $w['status'] ?? ''])->toArray());
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
