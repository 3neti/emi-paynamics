<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\SettleTransfer;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class SettleTransferCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:settle-transfer {requestId} {--fake}';

    protected $description = 'Settle a pre-transfer';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $data = [
            'request_id' => $this->argument('requestId'),
            'remarks' => text('Remarks', default: 'Settle via console'),
        ];
        $this->logBefore($data);

        try {
            $result = SettleTransfer::run($data);
            $this->components->info($result['message'] ?? json_encode($result));
            $this->logAfter($data, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($data, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
