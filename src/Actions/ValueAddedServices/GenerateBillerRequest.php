<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateBillerRequest
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $billerCode): array
    {
        $response = $this->client->get("/digitalgoods/transaction/generate_request/{$billerCode}");

        return $response->json() ?? ['success' => false, 'data' => [], 'error' => 'Empty response (HTTP '.$response->status().')'];
    }
}
