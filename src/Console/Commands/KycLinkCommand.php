<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GenerateKycKybLink;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class KycLinkCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:kyc-link {accountId} {level} {--fake}';

    protected $description = 'Generate a KYC/KYB capture link';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $data = [
            'account_id' => $this->argument('accountId'),
            'level' => $this->argument('level'),
            'device_information' => ['device_id' => 'console', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];
        $context = ['account_id' => $data['account_id'], 'level' => $data['level']];
        $this->logBefore($context);

        try {
            $result = GenerateKycKybLink::run($data);
            $this->components->twoColumnDetail('Capture Link', $result['data']['capture_link'] ?? '');
            $this->components->twoColumnDetail('Message', $result['data']['response_message'] ?? '');
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
