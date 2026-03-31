<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetBillerDetails
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $billerCode): array
    {
        $response = $this->client->get("/digitalgoods/transaction/get_biller_details/{$billerCode}");

        return $response->json() ?? ['success' => false, 'data' => [], 'error' => 'Empty response (HTTP '.$response->status().')'];
    }
}
