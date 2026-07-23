<?php

use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\EmiPaynamicsConstellation\Actions\Webhooks\HandleConstellationWebhook;

function buildValidPostbackPayload(string $key = 'TEST_MERCHANT_KEY'): array
{
    $data = [
        'code' => 'GR001',
        'message' => 'Success',
        'advise' => 'Approved',
        'timestamp' => '2025-06-15T10:00:00Z',
        'request_id' => 'REQ-POSTBACK-001',
    ];
    $data['signature'] = hash('sha512', $data['code'].$data['message'].$data['advise'].$data['timestamp'].$key);

    return [
        'wallet_id' => 'CNSTWLLT000001',
        'postback_id' => 'PB-'.uniqid(),
        'data' => $data,
        'originating_flow' => 'SN0004',
    ];
}

it('stores the raw payload as encrypted immutable evidence', function () {
    $payload = buildValidPostbackPayload();

    $receipt = HandleConstellationWebhook::run($payload);

    expect($receipt)->toBeInstanceOf(WebhookReceipt::class)
        ->and(json_decode($receipt->raw_body, true, 512, JSON_THROW_ON_ERROR)['wallet_id'])
        ->toBe('CNSTWLLT000001')
        ->and($receipt->body_sha256)->toBe(hash('sha256', json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
        )));
});

it('verifies the signature and leaves authenticated evidence ready for processing', function () {
    $payload = buildValidPostbackPayload();

    $receipt = HandleConstellationWebhook::run($payload);

    expect($receipt->signature_verified)->toBeTrue()
        ->and($receipt->authentication_status)->toBe('authenticated')
        ->and($receipt->processing_status)->toBe('received')
        ->and($receipt->processed_at)->toBeNull();
});

it('rejects invalid signature', function () {
    $payload = buildValidPostbackPayload();
    $payload['data']['signature'] = 'invalid_garbage_signature';

    $receipt = HandleConstellationWebhook::run($payload);

    expect($receipt->signature_verified)->toBeFalse()
        ->and($receipt->authentication_status)->toBe('rejected')
        ->and($receipt->processing_status)->toBe('rejected')
        ->and($receipt->error_message)->toContain('signature mismatch');
});

it('is idempotent - same postback_id returns existing receipt', function () {
    $payload = buildValidPostbackPayload();
    $payload['postback_id'] = 'PB-IDEMPOTENT-001';

    $first = HandleConstellationWebhook::run($payload);
    $second = HandleConstellationWebhook::run($payload);

    expect($first->id)->toBe($second->id);
    expect(WebhookReceipt::where('postback_id', 'PB-IDEMPOTENT-001')->count())->toBe(1);
});

it('stores postback_id and event_type', function () {
    $payload = buildValidPostbackPayload();
    $payload['originating_flow'] = 'SN0017';

    $receipt = HandleConstellationWebhook::run($payload);

    expect($receipt->postback_id)->not->toBeNull()
        ->and($receipt->event_type)->toBe('SN0017');
});
