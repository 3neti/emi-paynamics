<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\EditWallet;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class EditWalletCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:edit-wallet {walletId} {--fake}';

    protected $description = 'Edit wallet fields (PATCH semantics)';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $walletId = $this->argument('walletId');

        $fields = ['address', 'city', 'zip', 'state', 'country', 'nationality', 'source_of_funds', 'mobile_no', 'pin'];

        $data = [];
        foreach ($fields as $field) {
            $value = text("{$field} (leave empty to skip)", default: '');
            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        if (empty($data)) {
            $this->components->warn('No fields provided — nothing to update.');

            return self::SUCCESS;
        }

        $context = array_merge(['wallet_id' => $walletId], $data);
        $this->logBefore($context);

        try {
            $result = EditWallet::run($walletId, $data);
            $this->components->info('Wallet updated.');
            $this->table(
                ['Field', 'Value'],
                collect($result['data'] ?? [])->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->toArray()
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
