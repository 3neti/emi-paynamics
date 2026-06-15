<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\PreTransfer;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class PreTransferCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:pre-transfer {sourceWalletId} {destWalletId} {amount} {--fake}';

    protected $description = 'Pre-transfer (withhold) funds between wallets';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'amount' => $this->argument('amount'),
            'source_wallet_id' => $this->argument('sourceWalletId'),
            'destination_wallet_id' => $this->argument('destWalletId'),
            'request_id' => text('Request ID', default: 'TRF-'.Str::uuid()->toString()),
            'remarks' => text('Remarks', default: 'Pre-transfer via console'),
            'device_information' => ['device_id' => 'console', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];

        $context = ['source' => $data['source_wallet_id'], 'destination' => $data['destination_wallet_id'], 'amount' => $data['amount'], 'request_id' => $data['request_id']];
        $this->logBefore($context);

        try {
            $result = PreTransfer::run($data);

            if (($result['code'] ?? null) !== 'GR005') {
                $this->components->error('Pre-transfer failed.');
                $this->components->twoColumnDetail('Response Code', (string) (data_get($result, 'data.response_code') ?? $result['response_code'] ?? $result['code'] ?? ''));
                $this->components->twoColumnDetail('Response Message', (string) (data_get($result, 'data.response_message') ?? $result['response_message'] ?? $result['message'] ?? ''));
                $this->components->twoColumnDetail('Response Advise', (string) (data_get($result, 'data.response_advise') ?? $result['response_advise'] ?? ''));
                $this->components->twoColumnDetail('Request ID', (string) (data_get($result, 'data.request_id') ?? $result['request_id'] ?? $data['request_id']));
                $this->logAfter($context, $result);

                return self::FAILURE;
            }

            $this->components->twoColumnDetail('Request ID', $result['request_id'] ?? $data['request_id']);
            $this->components->twoColumnDetail('Message', $result['message'] ?? '');
            $this->components->twoColumnDetail('Remaining Limit', $result['remaining_wallet_limit'] ?? '');
            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
