<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiCore\Contracts\SystemReadiness;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddMerchantWallet;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetAvailableProfiles;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class SetupCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:setup
        {--verify : Only verify readiness, skip wallet creation}
        {--settlement-wallet-id= : Use existing settlement wallet ID}
        {--revenue-wallet-id= : Use existing revenue wallet ID}
        {--fake}';

    protected $description = 'Guided setup of infrastructure wallets (Settlement + Revenue)';

    public function handle(SystemReadiness $readiness): int
    {
        $this->fakeIfRequested();

        // --verify mode: just check readiness
        if ($this->option('verify')) {
            return $this->runVerification($readiness);
        }

        $context = ['action' => 'setup', 'step' => 'api-probe'];
        $this->logBefore($context);

        // Step 1: Probe credentials
        $this->components->info('Step 1: Probing API credentials...');

        try {
            $profiles = GetAvailableProfiles::run();
            $this->components->success('API credentials valid.');
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error("API probe failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Step 2: Settlement Wallet
        $settlementId = $this->option('settlement-wallet-id');

        if (! $settlementId) {
            $this->components->info('Step 2: Creating Settlement Wallet...');
            $settlementId = $this->createInfrastructureWallet(
                'Settlement',
                config('constellation.settlement.email'),
                'settlement-wallet',
                'settlement',
            );

            if (! $settlementId) {
                return self::FAILURE;
            }
        } else {
            $this->components->info("Step 2: Using existing Settlement Wallet: {$settlementId}");
        }

        // Step 3: Revenue Wallet
        $revenueId = $this->option('revenue-wallet-id');

        if (! $revenueId) {
            $this->components->info('Step 3: Creating Revenue Wallet...');
            $revenueId = $this->createInfrastructureWallet(
                'Revenue',
                config('constellation.revenue.email'),
                'revenue-wallet',
                'revenue',
            );

            if (! $revenueId) {
                return self::FAILURE;
            }
        } else {
            $this->components->info("Step 3: Using existing Revenue Wallet: {$revenueId}");
        }

        // Step 4: Display instructions
        $this->newLine();
        $this->components->info('Setup complete! Add these to your .env:');
        $this->newLine();
        $this->line("  CONSTELLATION_SETTLEMENT_WALLET_ID={$settlementId}");
        $this->line("  CONSTELLATION_REVENUE_WALLET_ID={$revenueId}");
        $this->newLine();
        $this->components->info('Next steps:');
        $this->components->bulletList([
            'Complete KYC for both wallets using the capture links above',
            'Add the wallet IDs to your .env (or deployment dashboard)',
            'Deploy the updated environment',
            'Run: php artisan constellation:setup --verify',
        ]);

        // Log everything for audit trail
        $this->logAfter($context, [
            'settlement_wallet_id' => $settlementId,
            'revenue_wallet_id' => $revenueId,
        ]);

        return self::SUCCESS;
    }

    private function createInfrastructureWallet(string $label, string $email, string $externalUid, string $walletConfigKey): ?string
    {
        $company = config('constellation.company');

        $data = [
            'company_name' => text("{$label} — Company name", default: $company['name']),
            'tin' => text("{$label} — TIN (max 15 chars)", default: $company['tin']),
            'email' => text("{$label} — Email", default: $email),
            'mobile_no' => text("{$label} — Mobile", default: $company['mobile_no']),
            'website' => text("{$label} — Website", default: $company['website']),
            'username' => text("{$label} — Portal username", default: "{$externalUid}.user"),
            'password' => text("{$label} — Portal password (min 12, upper+lower+number+special)", default: ucfirst($externalUid).'@3neti1'),
            'account_first_name' => text("{$label} — First name", default: $company['account_first_name']),
            'account_middle_name' => text("{$label} — Middle name", default: $company['account_middle_name']),
            'account_last_name' => text("{$label} — Last name", default: $company['account_last_name']),
            'birthdate' => text("{$label} — Birthdate (yyyy-MM-dd)", default: $company['birthdate']),
            'nationality' => text("{$label} — Nationality", default: $company['nationality']),
            'source_of_funds' => text("{$label} — Source of funds", default: $company['source_of_funds']),
            'business_address' => text("{$label} — Address", default: $company['business_address']),
            'business_zip' => text("{$label} — ZIP", default: $company['business_zip']),
            'business_city' => text("{$label} — City", default: $company['business_city']),
            'business_state' => text("{$label} — State", default: $company['business_state']),
            'business_country' => text("{$label} — Country", default: $company['business_country']),
            'profile_type' => 'DEFAULT_MERCHANT',
            'external_uid' => $externalUid,
            'notification_url' => config("constellation.{$walletConfigKey}.notification_url", config('constellation.notification_url', '')),
            'success_url' => text("{$label} — KYC Success URL", default: $company['success_url']),
            'failed_url' => text("{$label} — KYC Failed URL", default: $company['failed_url']),
            'device_information' => ['device_id' => 'setup-command', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];

        try {
            $result = AddMerchantWallet::run($data);

            if ($result['success'] ?? false) {
                $walletId = $result['data']['wallet_id'] ?? '';
                $accountId = $result['data']['account_id'] ?? '';
                $captureLink = $result['data']['capture_link'] ?? '';

                $this->components->twoColumnDetail("{$label} Wallet ID", $walletId);
                $this->components->twoColumnDetail("{$label} Account ID", $accountId);
                $this->components->twoColumnDetail("{$label} KYC Capture Link", $captureLink);

                // Persist to audit log
                $this->logAfter(
                    ['action' => "create-{$externalUid}", 'step' => "create-{$label}-wallet",'email' => $email],
                    ['wallet_id' => $walletId, 'account_id' => $accountId, 'capture_link' => $captureLink],
                );

                return $walletId;
            }

            $msg = $result['data']['response_message'] ?? 'Unknown error';
            $this->components->error("{$label} wallet creation failed: {$msg}");

            return null;
        } catch (\Throwable $e) {
            $this->components->error("{$label} wallet creation failed: {$e->getMessage()}");
            $this->logError([
                'action' => "create-{$externalUid}",
                'step' => "create-{$label}-wallet",
                'email' => $email,
            ], $e);

            return null;
        }
    }

    private function runVerification(SystemReadiness $readiness): int
    {
        $this->fakeIfRequested();
        $this->components->info('Verifying system readiness...');

        $result = $readiness->check();

        if ($result->ready) {
            $this->components->success('System is ready. All infrastructure wallets are configured and active.');

            return self::SUCCESS;
        }

        $this->components->error('System is NOT ready:');

        foreach ($result->issues as $issue) {
            $this->components->bulletList([$issue]);
        }

        return self::FAILURE;
    }
}
