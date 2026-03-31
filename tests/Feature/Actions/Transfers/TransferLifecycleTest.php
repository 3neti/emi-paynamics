<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\CancelTransfer;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\PreTransfer;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transfers\SettleTransfer;

it('sends a pre-transfer request with correct signature fields', function () {
    Http::fake([
        '*/transfer_pre' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => 'REQ-001',
                'status' => 'WITHHELD',
                'amount' => '500.00',
                'remaining_wallet_limit' => '49500.00',
            ],
        ]),
    ]);

    $result = PreTransfer::run([
        'amount' => '500.00',
        'source_wallet_id' => 'CNSTWLLT000001',
        'destination_wallet_id' => 'CNSTWLLT000002',
        'request_id' => 'REQ-001',
        'remarks' => 'Test transfer',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['status'])->toBe('WITHHELD');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'transfer_pre')
            && $request['source_wallet_id'] === 'CNSTWLLT000001'
            && $request['signature'] !== null;
    });
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
