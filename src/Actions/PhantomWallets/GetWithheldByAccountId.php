<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\PhantomWallets;

use LBHurtado\EmiPaynamicsConstellation\Http\ConstellationClient;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWithheldByAccountId
{
    use AsAction;

    public function __construct(private ConstellationClient $client) {}

    /** @return array<string, mixed> */
    public function handle(string $accountId): array
    {
        return $this->client->get("/integration/withhelds/merchant/{$accountId}")->json();
    }
}
