<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\AirtimeLoad;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class AirtimeLoadCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:airtime-load {--fake}';

    protected $description = 'Purchase airtime load';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'request_id' => text('Request ID', default: 'AT-'.Str::uuid()->toString()),
            'amount' => text('Amount'),
            'fee' => text('Fee', default: '0.00'),
            'sku' => text('SKU'),
            'recipient_name' => text('Recipient name'),
            'recipient_mobile' => text('Recipient mobile'),
            'partner_id' => text('Partner ID', default: ''),
        ];

        $context = ['request_id' => $data['request_id'], 'sku' => $data['sku'], 'amount' => $data['amount']];
        $this->logBefore($context);

        try {
            $result = AirtimeLoad::run($data);
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
