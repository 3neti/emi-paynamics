<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets\GetWithheldByAccountId;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class WithheldByAccountCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:withheld-by-account {accountId} {--fake}';

    protected $description = 'Get withheld funds by account ID';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $accountId = $this->argument('accountId');
        $context = ['account_id' => $accountId];
        $this->logBefore($context);

        try {
            $result = GetWithheldByAccountId::run($accountId);
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
