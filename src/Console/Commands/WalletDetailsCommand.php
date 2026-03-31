<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class WalletDetailsCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:wallet-details {walletId : Wallet ID or external_uid} {--fake}';

    protected $description = 'Get wallet details from Constellation';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $walletId = $this->argument('walletId');
        $context = ['wallet_id' => $walletId];
        $this->logBefore($context);

        try {
            $result = GetWalletDetails::run($walletId);

            if (! ($result['success'] ?? false)) {
                $this->components->error($result['data']['response_message'] ?? 'Request failed');
                $this->logAfter($context, $result);

                return self::FAILURE;
            }

            $this->table(
                ['Field', 'Value'],
                collect($result['data'])->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->toArray()
            );

            $this->logAfter($context, $result);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
