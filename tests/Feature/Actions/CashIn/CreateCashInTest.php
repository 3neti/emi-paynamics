<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashIn\CreateCashIn;

it('sends a cash-in request with correct signature', function () {
    Http::fake([
        '*/cashin/create' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => 'CI-001',
                'payment_action_info' => ['url' => 'https://pay.example.com/checkout'],
                'status' => 'PENDING',
            ],
        ]),
    ]);

    $result = CreateCashIn::run([
        'request_id' => 'CI-001',
        'wallet_id' => 'CNSTWLLT000001',
        'pmethod' => 'bank_transfer',
        'pchannel' => 'instapay',
        'amount' => '1000.00',
        'response_url' => 'https://example.com/success',
        'cancel_url' => 'https://example.com/cancel',
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['request_id'])->toBe('CI-001')
        ->and($result['data']['payment_action_info'])->toBeArray();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'cashin/create')
            && $request['signature'] !== null
            && $request['wallet_id'] === 'CNSTWLLT000001';
    });
});
