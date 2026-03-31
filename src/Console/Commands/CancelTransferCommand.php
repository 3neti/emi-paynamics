<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\CancelTransfer;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class CancelTransferCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:cancel-transfer {requestId} {--fake}';

    protected $description = 'Cancel a pre-transfer';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $data = [
            'request_id' => $this->argument('requestId'),
            'remarks' => text('Remarks', default: 'Cancel via console'),
        ];
        $this->logBefore($data);

        try {
            $result = CancelTransfer::run($data);
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
