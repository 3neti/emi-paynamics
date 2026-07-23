<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationFundingProviderAdapter;

beforeEach(function () {
    config()->set('constellation.funding.wallet_id', 'CNSTWLLT000001');
    config()->set('constellation.funding.payment_method', 'onlinebanktransfer');
    config()->set('constellation.funding.payment_channel', 'ubp_online');
    config()->set('constellation.funding.response_url', 'https://x-change.test/funding/return');
    config()->set('constellation.funding.cancel_url', 'https://x-change.test/funding/cancel');
});

it('exposes the Paynamics funding adapter through the provider tag', function () {
    $adapters = collect(app()->tagged('emi.funding-provider-adapters'));
    $adapter = $adapters->first(
        fn (mixed $candidate): bool => $candidate instanceof FundingProviderAdapter
            && $candidate->providerCode() === 'paynamics_constellation',
    );

    expect($adapter)->toBeInstanceOf(ConstellationFundingProviderAdapter::class);
});

it('creates signed cash-in instructions for the configured corporate wallet', function () {
    Http::fake([
        '*/cashin/create' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => paynamicsFundingRequestId(),
                'payment_action_info' => 'https://pay.example.com/checkout/123',
                'expiry_limit' => '2026-07-23T12:30:00+08:00',
            ],
        ]),
    ]);

    $instructions = app(ConstellationFundingProviderAdapter::class)
        ->createFundingInstructions(paynamicsFundingInstructionRequest());

    expect($instructions->provider)->toBe('paynamics_constellation')
        ->and($instructions->providerReference)->toBe(paynamicsFundingRequestId())
        ->and($instructions->amountMinor)->toBe(25_000)
        ->and($instructions->fundingAddress)->toBe('CNSTWLLT000001')
        ->and($instructions->actionUrl)->toBe('https://pay.example.com/checkout/123');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/cashin/create')
        && $request['request_id'] === paynamicsFundingRequestId()
        && $request['wallet_id'] === 'CNSTWLLT000001'
        && $request['amount'] === '250.00'
        && $request['signature'] === hash(
            'sha512',
            paynamicsFundingRequestId()
                .'CNSTWLLT000001'
                .'onlinebanktransfer'
                .'ubp_online'
                .'250.00'
                .'https://x-change.test/funding/return'
                .'https://x-change.test/funding/cancel'
                .'TEST_MERCHANT_KEY',
        ));
});

it('creates cash-in instructions for an ownership-verified dedicated wallet', function () {
    Http::fake([
        '*/cashin/create' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => paynamicsFundingRequestId(),
                'payment_action_info' => 'https://pay.example.com/checkout/dedicated',
            ],
        ]),
    ]);

    $request = paynamicsFundingInstructionRequest();
    $request->destination = paynamicsDedicatedDestination();
    $instructions = app(ConstellationFundingProviderAdapter::class)
        ->createFundingInstructions($request);

    expect($instructions->fundingAddress)->toBe('CNSTWLLT-DEDICATED');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/cashin/create')
        && $request['wallet_id'] === 'CNSTWLLT-DEDICATED');
});

it('blocks a reachable dedicated wallet without authoritative ownership proof', function () {
    $request = paynamicsFundingInstructionRequest();
    $request->destination = paynamicsDedicatedDestination('reachable');

    expect(fn () => app(ConstellationFundingProviderAdapter::class)->createFundingInstructions($request))
        ->toThrow(
            ProviderFundingVerificationIndeterminate::class,
            'authoritative wallet ownership verification',
        );

    Http::assertNothingSent();
});

it('authenticates and normalizes the signed Paynamics callback body', function () {
    $rawBody = json_encode(paynamicsFundingCallback(), JSON_THROW_ON_ERROR);
    $request = new ProviderWebhookRequestData(
        provider: 'paynamics_constellation',
        rawBody: $rawBody,
        contentType: 'application/json',
        headers: [],
        sourceIp: '203.0.113.10',
        receivedAt: new DateTimeImmutable,
    );
    $adapter = app(ConstellationFundingProviderAdapter::class);
    $authentication = $adapter->authenticateWebhook($request);
    $hint = $adapter->normalizeWebhook(ProviderWebhookReceiptData::fromRequest($request, $authentication));

    expect($authentication->authenticated)->toBeTrue()
        ->and($authentication->method)->toBe('paynamics-sha512-body')
        ->and($hint->providerEventId)->toBe('PB-FUNDING-001')
        ->and($hint->eventType)->toBe('SN0004')
        ->and($hint->requestId)->toBe(paynamicsFundingRequestId());
});

it('rejects callbacks whose body signature is invalid', function () {
    $payload = paynamicsFundingCallback();
    $payload['data']['signature'] = str_repeat('0', 128);

    $authentication = app(ConstellationFundingProviderAdapter::class)->authenticateWebhook(
        new ProviderWebhookRequestData(
            provider: 'paynamics_constellation',
            rawBody: json_encode($payload, JSON_THROW_ON_ERROR),
            contentType: 'application/json',
            headers: [],
            sourceIp: '203.0.113.10',
            receivedAt: new DateTimeImmutable,
        ),
    );

    expect($authentication->authenticated)->toBeFalse()
        ->and($authentication->reason)->toContain('signature mismatch');
});

it('maps an independently queried settled cash-in to exact minor-unit evidence', function () {
    Http::fake([
        '*/cashin/get_cashin_by_reqid/*' => Http::response(paynamicsCashInStatus()),
    ]);

    $observation = app(ConstellationFundingProviderAdapter::class)
        ->verifyFunding(paynamicsFundingVerification());

    expect($observation->providerTransactionId)->toBe('PAYNAMICS-TXN-001')
        ->and($observation->grossAmountMinor)->toBe(25_000)
        ->and($observation->feeAmountMinor)->toBe(50)
        ->and($observation->netAmountMinor)->toBe(24_950)
        ->and($observation->currency)->toBe('PHP')
        ->and($observation->providerStatus)->toBe('settled')
        ->and($observation->settledAt)->not->toBeNull()
        ->and($observation->metadata['destination_verified'])->toBeTrue()
        ->and($observation->webhookReceiptId)->toBe(91);
});

it('verifies a dedicated cash-in against its snapshotted wallet', function () {
    Http::fake([
        '*/cashin/get_cashin_by_reqid/*' => Http::response(paynamicsCashInStatus([
            'wallet_id' => 'CNSTWLLT-DEDICATED',
        ])),
    ]);

    $verification = paynamicsFundingVerification();
    $verification->destination = paynamicsDedicatedDestination();
    $observation = app(ConstellationFundingProviderAdapter::class)
        ->verifyFunding($verification);

    expect($observation->providerAccountReference)->toBe('CNSTWLLT-DEDICATED')
        ->and($observation->metadata['destination_verified'])->toBeTrue();
});

it('keeps independently queried pending cash-ins pending', function () {
    Http::fake([
        '*/cashin/get_cashin_by_reqid/*' => Http::response(paynamicsCashInStatus([
            'status' => 'PENDING',
            'completed_at' => null,
        ])),
    ]);

    $observation = app(ConstellationFundingProviderAdapter::class)
        ->verifyFunding(paynamicsFundingVerification());

    expect($observation->providerStatus)->toBe('pending')
        ->and($observation->settledAt)->toBeNull();
});

it('classifies configured not-found responses separately from indeterminate evidence', function () {
    config()->set('constellation.funding.not_observed_response_codes', ['NOT_FOUND']);
    Http::fake([
        '*/cashin/get_cashin_by_reqid/*' => Http::response([
            'success' => false,
            'data' => ['response_code' => 'NOT_FOUND'],
        ]),
    ]);

    expect(fn () => app(ConstellationFundingProviderAdapter::class)
        ->verifyFunding(paynamicsFundingVerification()))
        ->toThrow(ProviderFundingNotObserved::class);
});

it('fails closed when settled evidence omits or changes the destination wallet', function () {
    Http::fake([
        '*/cashin/get_cashin_by_reqid/*' => Http::response(paynamicsCashInStatus([
            'wallet_id' => 'CNSTWLLT-OTHER',
        ])),
    ]);

    $observation = app(ConstellationFundingProviderAdapter::class)
        ->verifyFunding(paynamicsFundingVerification());

    expect($observation->metadata['destination_verified'])->toBeFalse();
});

it('rejects settled evidence without an authoritative settlement timestamp', function () {
    Http::fake([
        '*/cashin/get_cashin_by_reqid/*' => Http::response(paynamicsCashInStatus([
            'completed_at' => null,
        ])),
    ]);

    expect(fn () => app(ConstellationFundingProviderAdapter::class)
        ->verifyFunding(paynamicsFundingVerification()))
        ->toThrow(ProviderFundingVerificationIndeterminate::class, 'authoritative timestamp');
});

function paynamicsFundingInstructionRequest(): FundingInstructionRequestData
{
    return new FundingInstructionRequestData(
        provider: 'paynamics_constellation',
        fundingReference: '01K10YV9H3XPG9T4K2FH09D6MW',
        amountMinor: 25_000,
        currency: 'PHP',
        accountReference: 'wallet:account-1001',
        expiresAt: new DateTimeImmutable('2026-07-23T12:30:00+08:00'),
    );
}

function paynamicsFundingRequestId(): string
{
    return 'XF'.strtoupper(substr(hash('sha256', '01K10YV9H3XPG9T4K2FH09D6MW'), 0, 24));
}

function paynamicsDedicatedDestination(string $verificationStatus = 'ownership_verified'): FundingDestinationData
{
    return new FundingDestinationData(
        provider: 'paynamics_constellation',
        mode: 'dedicated',
        destinationType: 'wallet',
        accountReference: 'wallet:account-1001',
        displayReference: '•••• CATED',
        fingerprint: hash('sha256', 'paynamics|CNSTWLLT-DEDICATED'),
        verificationStatus: $verificationStatus,
        providerWalletId: 'CNSTWLLT-DEDICATED',
    );
}

/**
 * @return array<string, mixed>
 */
function paynamicsFundingCallback(): array
{
    $data = [
        'code' => 'GR001',
        'message' => 'Success',
        'advise' => 'Approved',
        'timestamp' => '2026-07-23T12:00:00+08:00',
        'request_id' => paynamicsFundingRequestId(),
    ];
    $data['signature'] = hash(
        'sha512',
        $data['code'].$data['message'].$data['advise'].$data['timestamp'].'TEST_MERCHANT_KEY',
    );

    return [
        'wallet_id' => 'CNSTWLLT000001',
        'postback_id' => 'PB-FUNDING-001',
        'originating_flow' => 'SN0004',
        'data' => $data,
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function paynamicsCashInStatus(array $overrides = []): array
{
    return [
        'success' => true,
        'data' => array_replace([
            'request_id' => paynamicsFundingRequestId(),
            'transaction_id' => 'PAYNAMICS-TXN-001',
            'wallet_id' => 'CNSTWLLT000001',
            'amount' => '250.00',
            'fee_amount' => '0.50',
            'net_amount' => '249.50',
            'currency' => 'PHP',
            'status' => 'SUCCESS',
            'created_at' => '2026-07-23T11:58:00+08:00',
            'completed_at' => '2026-07-23T12:00:00+08:00',
            'response_code' => 'GR001',
        ], $overrides),
    ];
}

function paynamicsFundingVerification(): FundingVerificationData
{
    return new FundingVerificationData(
        provider: 'paynamics_constellation',
        fundingIntentReference: '01K10YV9H3XPG9T4K2FH09D6MW',
        expectedAmountMinor: 25_000,
        currency: 'PHP',
        providerRequestId: paynamicsFundingRequestId(),
        fundingAddress: 'CNSTWLLT000001',
        webhookReceiptId: 91,
    );
}
