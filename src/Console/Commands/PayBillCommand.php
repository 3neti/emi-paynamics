<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\BillsPayment;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class PayBillCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:pay-bill {--fake}';

    protected $description = 'Pay a bill via bills payment';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'request_id' => text('Request ID', default: 'BP-'.Str::uuid()->toString()),
            'biller_code' => text('Biller code'),
            'biller_fee' => text('Biller fee'),
            'fee' => text('Service fee', default: '0.00'),
            'payee_name' => text('Payee name'),
            'payee_mobile' => text('Payee mobile'),
            'payee_email' => text('Payee email'),
            'partner_id' => text('Partner ID', default: ''),
        ];

        $context = ['request_id' => $data['request_id'], 'biller_code' => $data['biller_code']];
        $this->logBefore($context);

        try {
            $result = BillsPayment::run($data);
            $this->components->twoColumnDetail('Request ID', $result['data']['request_id'] ?? $data['request_id']);
            $this->components->twoColumnDetail('Status', $result['data']['status'] ?? '');
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
