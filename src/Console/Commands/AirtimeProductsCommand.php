<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\GetProducts;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

class AirtimeProductsCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:airtime-products {--fake}';

    protected $description = 'List available airtime products';

    public function handle(): int
    {
        $this->fakeIfRequested();
        $context = ['action' => 'airtime-products'];
        $this->logBefore($context);

        try {
            $result = GetProducts::run();
            $products = $result['data'] ?? [];

            if (empty($products)) {
                $this->components->warn('No products found. '.($result['error'] ?? 'VAS may not be enabled for this account.'));
            } else {
                $this->table(['SKU', 'Name', 'Amount'], collect($products)->map(fn ($p) => [$p['sku'] ?? '', $p['name'] ?? '', $p['amount'] ?? ''])->toArray());
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
