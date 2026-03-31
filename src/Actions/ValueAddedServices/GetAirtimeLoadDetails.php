<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAirtimeLoadDetails
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $requestId): array
    {
        $response = $this->client->get("/digitalgoods/transaction/airtime_load_details/{$requestId}");

        return $response->json() ?? ['success' => false, 'data' => [], 'error' => 'Empty response (HTTP '.$response->status().')'];
    }
}
