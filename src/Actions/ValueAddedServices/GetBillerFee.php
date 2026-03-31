<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBillerFee
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(array $data): array
    {
        $response = $this->client->post('/digitalgoods/transaction/get_biller_fee', $data);

        return $response->json() ?? ['success' => false, 'data' => [], 'error' => 'Empty response (HTTP '.$response->status().')'];
    }
}
