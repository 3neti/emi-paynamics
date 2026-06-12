<?php

use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiCore\Enums\{PayoutStatus, SettlementRail};
use LBHurtado\EmiCore\Data\PayoutRequestData;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('constellation.settlement_wallet_id', 'CNSTWLLT_SETTLEMENT_001');

    config()->set('constellation.company.account_first_name', 'Lester');
    config()->set('constellation.company.account_last_name', 'Hurtado');
    config()->set('constellation.company.business_address', 'Pasig City');

    config()->set('constellation.rail_fees', [
        'INSTAPAY' => 1500,
        'PESONET' => 500,
    ]);

    config()->set('constellation.bank_map', [
        'UBP' => 'UNIONBANK_ID',
        'BDO' => 'BDO_ID',
    ]);

    app()->instance(ConstellationOtpResolver::class, new class implements ConstellationOtpResolver
    {
        public function resolve(array $otpRequestPayload): string
        {
            return '317537';
        }
    });
});

it('implements the payout provider contract', function () {
    expect(app(ConstellationPayoutProvider::class))
        ->toBeInstanceOf(ConstellationPayoutProvider::class);
});

it('disburses a payout through non-registered cash-out and returns pending result', function () {
    Http::fake([
        '*/integration/corp_wallet/view_wallet/*' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLT_SETTLEMENT_001',
                'account_id' => 'CNSTACC_SETTLEMENT_001',
            ],
        ]),
        '*/integration/corp_wallet/withdraw_not_registered' => Http::response([
            'success' => true,
            'response_code' => 'GR162',
            'response_message' => 'Request accepted and pending',
            'data' => [
                'request_id' => 'PAYOUT-001',
                'status' => 'PENDING',
                'amount' => '500.00',
            ],
        ]),
    ]);

    $request = new PayoutRequestData(
        reference: 'PAYOUT-001',
        amount: 500,
        account_number: '1234-5678-90',
        bank_code: 'UBP',
        settlement_rail: 'INSTAPAY',
        metadata: [
            'beneficiary' => [
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'address' => 'Quezon City',
            ],
            'reason' => 'Voucher payout',
            'device_information' => [
                'device_id' => 'device-001',
                'os_version' => 'iOS 18',
            ],
            'network_information' => [
                'ip_address' => '127.0.0.1',
                'network_type' => 'wifi',
            ],
        ],
    );

    $result = app(ConstellationPayoutProvider::class)->disburse($request);

    expect($result->transaction_id)->toBe('PAYOUT-001')
        ->and($result->status)->toBe(PayoutStatus::PENDING)
        ->and($result->provider)->toBe('paynamics')
        ->and($result->metadata)->toHaveKeys(['request', 'response']);

    Http::assertSent(function ($httpRequest) {
        return str_contains($httpRequest->url(), '/integration/corp_wallet/view_wallet/CNSTWLLT_SETTLEMENT_001');
    });

    Http::assertSent(function ($httpRequest) {
        $data = $httpRequest->data();

        return str_contains($httpRequest->url(), '/integration/corp_wallet/withdraw_not_registered')
            && $data['account_id'] === 'CNSTACC_SETTLEMENT_001'
            && $data['wallet_id'] === 'CNSTWLLT_SETTLEMENT_001'
            && $data['request_id'] === 'PAYOUT-001'
            && $data['account_no'] === '1234567890'
            && $data['bank_id'] === 'UNIONBANK_ID'
            && $data['ben_fname'] === 'Juan'
            && $data['ben_lname'] === 'Dela Cruz'
            && $data['ben_address'] === 'Quezon City'
            && $data['reason'] === 'Voucher payout'
            && $data['amount'] === '500.00'
            && $data['otp'] === '317537'
            && ! empty($data['signature']);
    });
});

it('falls back to configured company values when beneficiary metadata is absent', function () {
    Http::fake([
        '*/integration/corp_wallet/view_wallet/*' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLT_SETTLEMENT_001',
                'account_id' => 'CNSTACC_SETTLEMENT_001',
            ],
        ]),
        '*/integration/corp_wallet/withdraw_not_registered' => Http::response([
            'success' => true,
            'response_code' => 'GR162',
            'response_message' => 'Pending',
            'data' => [
                'request_id' => 'PAYOUT-002',
                'status' => 'PENDING',
            ],
        ]),
    ]);

    $request = new PayoutRequestData(
        reference: 'PAYOUT-002',
        amount: 250,
        account_number: '9999999999',
        bank_code: 'BDO',
        settlement_rail: 'PESONET',
        metadata: [],
    );

    app(ConstellationPayoutProvider::class)->disburse($request);

    Http::assertSent(function ($httpRequest) {
        $data = $httpRequest->data();

        return str_contains($httpRequest->url(), '/withdraw_not_registered')
            && $data['ben_fname'] === 'Lester'
            && $data['ben_lname'] === 'Hurtado'
            && $data['ben_address'] === 'Pasig City'
            && $data['bank_id'] === 'BDO_ID';
    });
});

it('returns failed result when disburse throws an exception', function () {
//    Http::fake([
//        '*/integration/corp_wallet/view_wallet/*' => Http::response([
//            'success' => true,
//            'data' => [
//                'wallet_id' => 'CNSTWLLT_SETTLEMENT_001',
//                'account_id' => 'CNSTACC_SETTLEMENT_001',
//            ],
//        ]),
//        '*/integration/corp_wallet/withdraw_not_registered' => Http::response([
//            'message' => 'Provider error',
//        ], 500),
//    ]);
    Http::fake([
        '*/integration/corp_wallet/view_wallet/*' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLT_SETTLEMENT_001',
                'account_id' => 'CNSTACC_SETTLEMENT_001',
            ],
        ]),
        '*/integration/corp_wallet/withdraw_not_registered' => Http::failedConnection(),
    ]);

    $request = new PayoutRequestData(
        reference: 'PAYOUT-003',
        amount: 100,
        account_number: '1234567890',
        bank_code: 'UBP',
        settlement_rail: 'INSTAPAY',
    );

    $result = app(ConstellationPayoutProvider::class)->disburse($request);

    expect($result->transaction_id)->toBe('PAYOUT-003')
        ->and($result->status)->toBe(PayoutStatus::FAILED)
        ->and($result->provider)->toBe('paynamics')
        ->and($result->metadata)->toHaveKey('error');
});

it('checks status from cash-out endpoint when available', function () {
    Http::fake([
        '*/integration/corp_wallet/withdraw/get_by_request_id/*' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => 'PAYOUT-004',
                'status' => 'SETTLED',
            ],
        ]),
    ]);

    $result = app(ConstellationPayoutProvider::class)->checkStatus('PAYOUT-004');

    expect($result->transaction_id)->toBe('PAYOUT-004')
        ->and($result->status)->toBe(PayoutStatus::COMPLETED)
        ->and($result->provider)->toBe('paynamics');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/withdraw/get_by_request_id/PAYOUT-004'));
});

it('falls back to transaction lookup when cash-out status is missing', function () {
    Http::fake([
        '*/integration/corp_wallet/withdraw/get_by_request_id/*' => Http::response([
            'success' => true,
            'data' => [
                'request_id' => 'PAYOUT-005',
            ],
        ]),
        '*/integration/corp_wallet/elastic_trx/get_by_request_id/*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'request_id' => 'PAYOUT-005',
                    'status' => 'PROCESSING',
                ],
            ],
        ]),
    ]);

    $result = app(ConstellationPayoutProvider::class)->checkStatus('PAYOUT-005');

    expect($result->transaction_id)->toBe('PAYOUT-005')
        ->and($result->status)->toBe(PayoutStatus::PROCESSING)
        ->and($result->provider)->toBe('paynamics')
        ->and($result->metadata['source'])->toBe('elastic_trx.get_by_request_id');
});

it('returns failed result when cash-out status lookup returns an error payload', function () {
    Http::fake([
        '*/integration/corp_wallet/withdraw/get_by_request_id/*' => Http::response([
            'message' => 'Boom',
        ], 500),
    ]);

    $result = app(ConstellationPayoutProvider::class)->checkStatus('PAYOUT-006');

    expect($result->transaction_id)->toBe('PAYOUT-006')
        ->and($result->status)->toBe(PayoutStatus::FAILED)
        ->and($result->provider)->toBe('paynamics')
        ->and($result->metadata)->toHaveKey('error');
});

it('returns failed result when status lookup throws an exception', function () {
    Http::fake([
        '*/integration/corp_wallet/withdraw/get_by_request_id/*' => Http::failedConnection(),
    ]);

    $result = app(ConstellationPayoutProvider::class)->checkStatus('PAYOUT-007');

    expect($result->transaction_id)->toBe('PAYOUT-007')
        ->and($result->status)->toBe(PayoutStatus::FAILED)
        ->and($result->provider)->toBe('paynamics')
        ->and($result->metadata)->toHaveKey('error');
});

it('returns configured rail fee for instapay', function () {
    $fee = app(ConstellationPayoutProvider::class)->getRailFee(SettlementRail::INSTAPAY);

    expect($fee)->toBe(1500);
});

it('returns configured rail fee for pesonet', function () {
    $fee = app(ConstellationPayoutProvider::class)->getRailFee(SettlementRail::PESONET);

    expect($fee)->toBe(500);
});

it('returns zero when no rail fee is configured', function () {
    config()->set('constellation.rail_fees', []);

    $fee = app(ConstellationPayoutProvider::class)->getRailFee(SettlementRail::INSTAPAY);

    expect($fee)->toBe(0);
});