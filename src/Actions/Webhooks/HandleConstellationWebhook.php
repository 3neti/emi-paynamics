<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Webhooks;

use DateTimeImmutable;
use JsonException;
use LBHurtado\EmiCore\Actions\Funding\StoreProviderWebhookReceipt;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationFundingProviderAdapter;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleConstellationWebhook
{
    use AsAction;

    public function __construct(
        private readonly ConstellationFundingProviderAdapter $funding,
        private readonly StoreProviderWebhookReceipt $storeReceipt,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function handle(array $payload): WebhookReceipt
    {
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $request = new ProviderWebhookRequestData(
            provider: $this->funding->providerCode(),
            rawBody: $rawBody,
            contentType: 'application/json',
            headers: [],
            sourceIp: null,
            receivedAt: new DateTimeImmutable,
            signature: is_string($data['signature'] ?? null) ? $data['signature'] : null,
        );
        $authentication = $this->funding->authenticateWebhook($request);
        $event = $this->funding->normalizeWebhook(
            ProviderWebhookReceiptData::fromRequest($request, $authentication),
        );

        return $this->storeReceipt->handle($request, $authentication, $event);
    }
}
