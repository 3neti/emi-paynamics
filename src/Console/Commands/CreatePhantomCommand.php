<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddPhantomWallet;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class CreatePhantomCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:create-phantom
        {externalUid? : Voucher code or batch ID (default: UUID)}
        {--ttl= : Expiry in minutes (default: from config, 30 min)}
        {--fake}';

    protected $description = 'Create a phantom wallet (escrow bucket) in Constellation';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $externalUid = $this->argument('externalUid') ?? (string) Str::uuid();
        $ttl = (int) ($this->option('ttl') ?: config('constellation.phantom_ttl_minutes', 30));
        $expiration = now('Asia/Manila')->addMinutes($ttl)->format('Y-m-d H:i:s');

        $data = [
            'external_uid' => $externalUid,
            'expiration' => $expiration,
            'profile_type' => 'DEFAULT_MERCHANT',
        ];

        $context = ['external_uid' => $externalUid, 'ttl_minutes' => $ttl, 'expiration' => $expiration];
        $this->logBefore($context);

        try {
            $result = AddPhantomWallet::run($data);

            if ($result['success'] ?? false) {
                $this->components->success('Phantom wallet created!');
                $this->components->twoColumnDetail('Wallet ID', $result['data']['wallet_id'] ?? '');
                $this->components->twoColumnDetail('External UID', $externalUid);
                $this->components->twoColumnDetail('Expires', $expiration." ({$ttl} min)");
            } else {
                $this->components->error($result['data']['response_message'] ?? 'Failed');
                $this->components->twoColumnDetail('Advise', $result['data']['response_advise'] ?? '');
            }

            $this->logAfter($context, $result);

            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
