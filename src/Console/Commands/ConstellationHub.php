<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\select;

class ConstellationHub extends Command
{
    protected $signature = 'constellation {--fake : Use fake HTTP responses}';

    protected $description = 'Interactive launcher for all Constellation commands';

    /** @var array<string, array<string, string>> */
    protected array $commands = [
        'System' => [
            'constellation:setup' => 'Guided setup of infrastructure wallets',
            'constellation:probe' => 'Smoke test — verify API credentials',
        ],
        'Onboarding & Wallet' => [
            'constellation:create-merchant' => 'Create a merchant wallet',
            'constellation:create-customer' => 'Create a customer wallet',
            'constellation:create-phantom' => 'Create a phantom wallet',
            'constellation:wallet-details' => 'Get wallet details',
            'constellation:wallet-balance' => 'Get wallet balance & limits',
            'constellation:edit-wallet' => 'Edit wallet fields (PATCH)',
            'constellation:kyc-link' => 'Generate KYC/KYB capture link',
            'constellation:lock-wallet' => 'Lock a wallet',
            'constellation:unlock-wallet' => 'Unlock a wallet',
            'constellation:set-threshold' => 'Set wallet threshold',
        ],
        'Cash In' => [
            'constellation:payment-channels' => 'List payment channels',
            'constellation:cash-in' => 'Initiate a cash-in',
            'constellation:cash-in-status' => 'Check cash-in status',
            'constellation:cash-ins' => 'List cash-ins by account',
        ],
        'Fund Transfer' => [
            'constellation:pre-transfer' => 'Pre-transfer (withhold funds)',
            'constellation:settle-transfer' => 'Settle a pre-transfer',
            'constellation:cancel-transfer' => 'Cancel a pre-transfer',
        ],
        'Cash Out' => [
            'constellation:supported-banks' => 'List supported banks',
            'constellation:add-bank-account' => 'Register a bank account',
            'constellation:edit-bank-account' => 'Edit a bank account',
            'constellation:remove-bank-account' => 'Remove a bank account',
            'constellation:bank-accounts' => 'List bank accounts',
            'constellation:request-otp' => 'Request OTP for cash-out',
            'constellation:resend-otp' => 'Resend cash-out OTP',
            'constellation:verify-transaction' => 'Verify transaction with PIN',
            'constellation:cash-out' => 'Cash out (registered bank)',
            'constellation:cash-out-nr' => 'Cash out (non-registered bank)',
            'constellation:cash-out-status' => 'Check cash-out status',
            'constellation:cash-outs' => 'List cash-outs by account',
        ],
        'Transactions & Withheld' => [
            'constellation:transaction' => 'Get transaction by request ID',
            'constellation:transactions' => 'Get transactions by wallet ID',
            'constellation:withheld' => 'Withheld funds by wallet',
            'constellation:withheld-by-account' => 'Withheld funds by account',
            'constellation:withheld-phantom' => 'Withheld funds by phantom wallet',
        ],
        'Value Added Services' => [
            'constellation:airtime-products' => 'List airtime products',
            'constellation:airtime-load' => 'Purchase airtime load',
            'constellation:airtime-status' => 'Check airtime load status',
            'constellation:airtime-history' => 'Airtime load history',
            'constellation:billers' => 'List billers',
            'constellation:biller-details' => 'Get biller details',
            'constellation:biller-fee' => 'Get biller fee',
            'constellation:biller-request' => 'Generate biller request',
            'constellation:pay-bill' => 'Pay a bill',
            'constellation:bill-status' => 'Check bill payment status',
            'constellation:bill-history' => 'Bill payment history',
        ],
    ];

    public function handle(): int
    {
        $options = [];
        foreach ($this->commands as $group => $cmds) {
            foreach ($cmds as $cmd => $desc) {
                $options["{$cmd}"] = "[{$group}] {$desc}";
            }
        }

        $selected = select(
            label: 'Select a Constellation command to run',
            options: $options,
        );

        $params = $this->option('fake') ? ['--fake' => true] : [];

        return $this->call($selected, $params);
    }
}
