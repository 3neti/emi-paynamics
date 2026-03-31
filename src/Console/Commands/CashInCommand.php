<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashIn\CreateCashIn;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletBalance;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CashInCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:cash-in {walletId} {amount} {--fake}';

    protected $description = 'Initiate a cash-in to a wallet';

    /** @var array<string, array{pmethod: string, pchannel: string, label: string}> */
    private array $channels = [
        'gc' => ['pmethod' => 'wallet', 'pchannel' => 'gc', 'label' => 'GCash — wallet, min ₱5, fee 2.80%'],
        'grabpay_ph' => ['pmethod' => 'wallet', 'pchannel' => 'grabpay_ph', 'label' => 'GrabPay — wallet, min ₱5, fee 2.80%'],
        'paymaya_ph' => ['pmethod' => 'wallet', 'pchannel' => 'paymaya_ph', 'label' => 'Maya — wallet, min ₱5, fee 2.80%'],
        'shopeepay_ph' => ['pmethod' => 'wallet', 'pchannel' => 'shopeepay_ph', 'label' => 'ShopeePay — wallet, min ₱5, fee 2.80%'],
        'bpi_online' => ['pmethod' => 'onlinebanktransfer', 'pchannel' => 'bpi_online', 'label' => 'BPI Online — bank transfer, min ₱500, fee 2.24%'],
        'ubp_online' => ['pmethod' => 'onlinebanktransfer', 'pchannel' => 'ubp_online', 'label' => 'UnionBank Online — bank transfer, min ₱500, fee 2.24%'],
        'br_bdo_ph' => ['pmethod' => 'onlinebanktransfer', 'pchannel' => 'br_bdo_ph', 'label' => 'BDO Online — bank transfer, min ₱500, fee 2.24%'],
        'creditcard' => ['pmethod' => 'creditcard', 'pchannel' => 'creditcard', 'label' => 'Credit Card — min ₱5, fee 3.36%'],
        'other' => ['pmethod' => '', 'pchannel' => '', 'label' => 'Other — enter pmethod/pchannel manually'],
    ];

    public function handle(): int
    {
        $this->fakeIfRequested();

        $walletId = $this->argument('walletId');
        $amount = $this->argument('amount');

        // Channel selection
        $channelKey = select(
            label: 'Select payment channel',
            options: collect($this->channels)->mapWithKeys(fn ($ch, $key) => [$key => $ch['label']])->toArray(),
        );

        $channel = $this->channels[$channelKey];

        if ($channelKey === 'other') {
            $channel['pmethod'] = text('Payment method (pmethod)');
            $channel['pchannel'] = text('Payment channel (pchannel)');
        }

        $requestId = 'CI'.now()->format('ymdHis').rand(100, 999);

        $data = [
            'wallet_id' => $walletId,
            'amount' => $amount,
            'request_id' => $requestId,
            'pmethod' => $channel['pmethod'],
            'pchannel' => $channel['pchannel'],
            'response_url' => config('constellation.company.success_url') ?: 'https://example.com/cashin/success',
            'cancel_url' => config('constellation.company.failed_url') ?: 'https://example.com/cashin/cancel',
            'meta_data' => new \stdClass,
            'device_information' => ['device_id' => 'console', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];

        $context = ['wallet_id' => $walletId, 'amount' => $amount, 'request_id' => $requestId, 'channel' => $channelKey];
        $this->logBefore($context);

        try {
            $result = CreateCashIn::run($data);
            $d = $result['data'] ?? [];

            if (! ($result['success'] ?? false)) {
                $this->components->error($d['response_message'] ?? 'Cash-in failed');
                $this->components->twoColumnDetail('Advise', $d['response_advise'] ?? '');
                $this->logAfter($context, $result);

                return self::FAILURE;
            }

            $this->components->success('Cash-in initiated!');
            $this->components->twoColumnDetail('Request ID', $d['request_id'] ?? $requestId);
            $this->components->twoColumnDetail('Status', $d['response_message'] ?? '');
            $this->components->twoColumnDetail('Expires', $d['expiry_limit'] ?? '');

            // payment_action_info is a URL string
            $paymentUrl = $d['payment_action_info'] ?? null;
            if ($paymentUrl) {
                $this->newLine();
                $this->components->info('Open this URL to complete payment:');
                $this->line("  {$paymentUrl}");
            }

            // Show remaining limits
            $this->newLine();
            $this->components->twoColumnDetail('Remaining Wallet Limit', $d['remaining_wallet_limit'] ?? '');
            $this->components->twoColumnDetail('Remaining Inflow Limit', $d['remaining_inflow_limit'] ?? '');

            // Show current balance
            $this->newLine();
            $this->components->info('Current wallet balance:');

            try {
                $balance = GetWalletBalance::run($walletId);
                $this->components->twoColumnDetail('Wallet Balance', $balance['wallet_balance'] ?? '');
                $this->components->twoColumnDetail('Current Balance', $balance['current_balance'] ?? '');
                $this->components->twoColumnDetail('Withdrawable', $balance['withdrawable_balance'] ?? '');
            } catch (\Throwable) {
                $this->components->warn('Could not fetch balance.');
            }

            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
