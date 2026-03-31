<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\GenerateBillerRequest;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class BillerRequestCommand extends Command
{
    use FakesConstellationHttp, LogsConstellationActivity;

    protected $signature = 'constellation:biller-request {billerCode} {--fake}';

    protected $description = 'Generate a biller payment request';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $code = $this->argument('billerCode');
        $context = ['biller_code' => $code];
        $this->logBefore($context);

        try {
            $result = GenerateBillerRequest::run($code);
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
