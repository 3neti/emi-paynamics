<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\CancelTransfer;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\PreTransfer;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\SettleTransfer;

it('sends a pre-transfer request with the v1.42 nested payload and correct signature fields', function () {
    Http::fake([
        '*/transfer_pre' => Http::response([
            'code' => 'GR005',
            'message' => 'Request for Pre-transfer success.',
            'request_id' => 'REQ-001',
            'remaining_wallet_limit' => '49500.00',
        ]),
    ]);

    $result = PreTransfer::run([
        'amount' => '500.00',
        'source_wallet_id' => 'CNSTWLLT000001',
        'destination_wallet_id' => 'CNSTWLLT000002',
        'request_id' => 'REQ-001',
        'remarks' => 'Test transfer',
    ]);

    expect($result['code'])->toBe('GR005')
        ->and($result['request_id'])->toBe('REQ-001');

    $expectedSignature = hash(
        'sha512',
        '500.00'.'CNSTWLLT000001'.'CNSTWLLT000002'.'REQ-001'.'Test transfer'.config('constellation.merchant_key')
    );

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'transfer_pre')
            && $request['source_wallet_id'] === 'CNSTWLLT000001'
            && $request['destination_wallet_id'] === 'CNSTWLLT000002'
            && $request['payload']['request_id'] === 'REQ-001'
            && $request['payload']['remarks'] === 'Test transfer'
            && ! isset($request['request_id'])
            && ! isset($request['remarks'])
            && isset($request['meta_data']);
    });

    Http::assertSent(function ($request) use ($expectedSignature) {
        return str_contains($request->url(), 'transfer_pre')
            && $request['signature'] === $expectedSignature;
    });
});

it('accepts pre-transfer request id and remarks from an existing provider payload', function () {
    Http::fake([
        '*/transfer_pre' => Http::response([
            'code' => 'GR005',
            'message' => 'Request for Pre-transfer success.',
            'request_id' => 'REQ-002',
        ]),
    ]);

    PreTransfer::run([
        'amount' => '125.00',
        'source_wallet_id' => 'CNSTWLLT000001',
        'destination_wallet_id' => 'CNSTWLLT000002',
        'payload' => [
            'request_id' => 'REQ-002',
            'remarks' => 'Nested transfer',
        ],
    ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'transfer_pre')
            && $request['payload']['request_id'] === 'REQ-002'
            && $request['payload']['remarks'] === 'Nested transfer';
    });
});

it('returns a failing exit code when pre-transfer returns a business failure response', function () {
    Http::fake([
        '*/transfer_pre' => Http::response([
            'success' => false,
            'data' => [
                'response_code' => 'GR052',
                'response_message' => 'Invalid transfer request.',
                'response_advise' => 'Check request body.',
                'request_id' => 'IGNORED',
            ],
        ]),
    ]);

    $this->artisan('constellation:pre-transfer', [
        'sourceWalletId' => 'CNSTWLLT000001',
        'destWalletId' => 'CNSTWLLT000002',
        'amount' => '75.00',
    ])
        ->expectsQuestion('Request ID', 'REQ-CMD-001')
        ->expectsQuestion('Remarks', 'Command transfer')
        ->assertExitCode(1);
});

it('settles a pre-transfer by request_id', function () {
    Http::fake([
        '*/transfer_settle' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => 'REQ-001',
                'status' => 'SETTLED',
            ],
        ]),
    ]);

    $result = SettleTransfer::run([
        'request_id' => 'REQ-001',
        'remarks' => 'Settling',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['status'])->toBe('SETTLED');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'transfer_settle') && $r['request_id'] === 'REQ-001');
});

it('cancels a pre-transfer by request_id', function () {
    Http::fake([
        '*/transfer_cancel' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => 'REQ-001',
                'status' => 'CANCELLED',
            ],
        ]),
    ]);

    $result = CancelTransfer::run([
        'request_id' => 'REQ-001',
        'remarks' => 'Cancelling',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['status'])->toBe('CANCELLED');

    Http::assertSent(fn ($r) => str_contains($r->url(), 'transfer_cancel'));
});
