<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\AirtimeLoad;
use LBHurtado\EmiPaynamicsConstellation\Actions\ValueAddedServices\BillsPayment;

it('sends an airtime load request', function () {
    Http::fake([
        '*/digitalgoods/transaction/airtime_load' => Http::response([
            'success' => true,
            'data' => ['request_id' => 'AT-001', 'status' => 'PENDING'],
        ]),
    ]);

    $result = AirtimeLoad::run([
        'request_id' => 'AT-001',
        'amount' => '100.00',
        'sku' => 'GLOBE100',
        'recipient_name' => 'Juan',
        'recipient_mobile' => '639171234567',
        'fee' => '5.00',
    ]);

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'digitalgoods/transaction/airtime_load') && $r['signature'] !== null);
});

it('sends a bills payment request', function () {
    Http::fake([
        '*/digitalgoods/transaction/bills_payment' => Http::response([
            'success' => true,
            'data' => ['request_id' => 'BP-001', 'status' => 'PENDING'],
        ]),
    ]);

    $result = BillsPayment::run([
        'request_id' => 'BP-001',
        'biller_code' => 'MERALCO',
        'biller_fee' => '15.00',
        'payee_name' => 'Juan Dela Cruz',
        'payee_mobile' => '639171234567',
        'payee_email' => 'juan@example.com',
        'fee' => '10.00',
    ]);

    expect($result['success'])->toBeTrue();
    Http::assertSent(fn ($r) => str_contains($r->url(), 'digitalgoods/transaction/bills_payment') && $r['signature'] !== null);
});
