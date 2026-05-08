<?php

namespace LBHurtado\EmiPaynamicsConstellation;

use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use LBHurtado\EmiCore\Contracts\SignsProviderPayloads;
use LBHurtado\EmiCore\Contracts\SystemReadiness;
use LBHurtado\EmiCore\Contracts\VerifiesProviderPostbacks;
use LBHurtado\EmiPaynamicsConstellation\Console\Commands as Cmd;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSignatureVerifier;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSigner;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSystemReadiness;
use LBHurtado\EmiPaynamicsConstellation\Support\InteractiveOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Support\NullOtpResolver;

class ConstellationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/constellation.php',
            'constellation'
        );

        $this->app->bind(SignsProviderPayloads::class, ConstellationSigner::class);
        $this->app->bind(VerifiesProviderPostbacks::class, ConstellationSignatureVerifier::class);
        $this->app->bind(SystemReadiness::class, ConstellationSystemReadiness::class);

        $this->app->bind(ConstellationOtpResolver::class, function ($app) {
            $driver = config('constellation.otp.resolver', 'interactive');

            $resolvers = config('constellation.otp.resolvers', [
                'interactive' => InteractiveOtpResolver::class,
                'null' => NullOtpResolver::class,
            ]);

            $class = $resolvers[$driver] ?? null;

            if (! $class || ! class_exists($class)) {
                throw new InvalidArgumentException("Unsupported Constellation OTP resolver: [{$driver}].");
            }

            return $app->make($class);
        });
    }

    public function boot(): void
    {
        $this->registerLogChannel();
        $this->warnIfInfrastructureWalletsMissing();
        $this->registerCommands();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/constellation.php');

        $this->publishes([
            __DIR__.'/../config/constellation.php' => config_path('constellation.php'),
        ], 'constellation-config');

        $this->publishes([
            __DIR__.'/../docs/' => base_path('docs/constellation/'),
        ], 'constellation-docs');
    }

    protected function warnIfInfrastructureWalletsMissing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $missing = [];
        if (empty(config('constellation.settlement_wallet_id'))) {
            $missing[] = 'CONSTELLATION_SETTLEMENT_WALLET_ID';
        }
        if (empty(config('constellation.revenue_wallet_id'))) {
            $missing[] = 'CONSTELLATION_REVENUE_WALLET_ID';
        }

        if (! empty($missing)) {
            $vars = implode(', ', $missing);
            $this->app->make('log')->channel(config('constellation.log_channel', 'constellation'))
                ->warning("Constellation infrastructure wallets not configured: {$vars}. Run: php artisan constellation:setup");
        }
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Cmd\ConstellationHub::class,
                Cmd\ProbeCommand::class,
                Cmd\CreateMerchantCommand::class,
                Cmd\CreateCustomerCommand::class,
                Cmd\CreatePhantomCommand::class,
                Cmd\WalletDetailsCommand::class,
                Cmd\WalletBalanceCommand::class,
                Cmd\EditWalletCommand::class,
                Cmd\KycLinkCommand::class,
                Cmd\LockWalletCommand::class,
                Cmd\UnlockWalletCommand::class,
                Cmd\SetThresholdCommand::class,
                Cmd\PaymentChannelsCommand::class,
                Cmd\CashInCommand::class,
                Cmd\CashInStatusCommand::class,
                Cmd\PreTransferCommand::class,
                Cmd\SettleTransferCommand::class,
                Cmd\CancelTransferCommand::class,
                Cmd\SupportedBanksCommand::class,
                Cmd\AddBankAccountCommand::class,
                Cmd\BankAccountsCommand::class,
                Cmd\CashOutCommand::class,
                Cmd\CashOutStatusCommand::class,
                Cmd\WithheldCommand::class,
                Cmd\TransactionCommand::class,
                Cmd\TransactionsCommand::class,
                Cmd\AirtimeProductsCommand::class,
                Cmd\AirtimeLoadCommand::class,
                Cmd\BillersCommand::class,
                Cmd\PayBillCommand::class,
                Cmd\EditBankAccountCommand::class,
                Cmd\RemoveBankAccountCommand::class,
                Cmd\CashInsCommand::class,
                Cmd\CashOutNrCommand::class,
                Cmd\CashOutsCommand::class,
                Cmd\ResendOtpCommand::class,
                Cmd\RequestOtpCommand::class,
                Cmd\VerifyTransactionCommand::class,
                Cmd\WithheldByAccountCommand::class,
                Cmd\WithheldPhantomCommand::class,
                Cmd\AirtimeStatusCommand::class,
                Cmd\AirtimeHistoryCommand::class,
                Cmd\BillerFeeCommand::class,
                Cmd\BillerDetailsCommand::class,
                Cmd\BillerRequestCommand::class,
                Cmd\BillStatusCommand::class,
                Cmd\BillHistoryCommand::class,
                Cmd\SetupCommand::class,
            ]);
        }
    }

    protected function registerLogChannel(): void
    {
        $channelName = config('constellation.log_channel', 'constellation');

        if (! $this->app['config']->has("logging.channels.{$channelName}")) {
            $this->app['config']->set("logging.channels.{$channelName}", [
                'driver' => 'daily',
                'path' => storage_path('logs/constellation/constellation.log'),
                'level' => 'debug',
                'days' => 30,
            ]);
        }
    }
}
