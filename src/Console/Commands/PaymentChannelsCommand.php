<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashIn\GetPaymentChannels;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class PaymentChannelsCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:payment-channels {--fake}';

    protected $description = 'List available payment channels for cash-in';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $context = ['action' => 'payment-channels'];
        $this->logBefore($context);

        try {
            $result = GetPaymentChannels::run();
            $channels = $result['data'] ?? [];

            $this->table(
                ['Channel ID', 'Name', 'Method', 'Fee', 'Min', 'Max'],
                collect($channels)->map(fn ($c) => [
                    $c['pchannel_id'] ?? '',
                    $c['name'] ?? '',
                    $c['pmethod'] ?? '',
                    $c['fee'] ?? '',
                    $c['minimum'] ?? '-',
                    $c['maximum'] ?? '-',
                ])->toArray()
            );

            $this->components->info(count($channels).' channels found.');

            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
