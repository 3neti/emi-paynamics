<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\VerifyTransaction;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class VerifyTransactionCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:verify-transaction {--fake}';

    protected $description = 'Verify a transaction with PIN';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'request_id' => text('Request ID'),
            'wallet_id' => text('Wallet ID'),
            'pin' => text('PIN'),
            'timestamp' => text('Timestamp', default: now()->toIso8601String()),
        ];

        $context = ['request_id' => $data['request_id'], 'wallet_id' => $data['wallet_id']];
        $this->logBefore($context);

        try {
            $result = VerifyTransaction::run($data);
            $this->components->twoColumnDetail('Status', $result['data']['response_message'] ?? json_encode($result));
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
