<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\GetCashOutByAccountId;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class CashOutsCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:cash-outs {accountId} {--fake}';

    protected $description = 'List cash-outs by account ID';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $accountId = $this->argument('accountId');
        $context = ['account_id' => $accountId];
        $this->logBefore($context);

        try {
            $result = GetCashOutByAccountId::run($accountId);
            $this->table(['Field', 'Value'], collect($result['data'] ?? $result)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->toArray());
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
