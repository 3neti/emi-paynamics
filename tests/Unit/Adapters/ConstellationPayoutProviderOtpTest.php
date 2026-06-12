<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutNonRegistered;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\GetCashOutByRequestId;
use LBHurtado\EmiPaynamicsConstellation\Actions\Transactions\GetTransactionByRequestId;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;

it('resolves OTP before submitting Paynamics non-registered cash-out', function () {
    $cashOut = Mockery::mock(CreateCashOutNonRegistered::class);
    $getCashOutByRequestId = Mockery::mock(GetCashOutByRequestId::class);
    $getTransactionByRequestId = Mockery::mock(GetTransactionByRequestId::class);
    $getWalletDetails = Mockery::mock(GetWalletDetails::class);
    $otpResolver = Mockery::mock(ConstellationOtpResolver::class);

    config()->set('constellation.settlement_wallet_id', 'CNSTWLLTTWXNQP');
    config()->set('constellation.bank_map.GXI', 'GXI');

    $getWalletDetails->shouldReceive('handle')
        ->once()
        ->with('CNSTWLLTTWXNQP')
        ->andReturn([
            'data' => [
                'account_id' => 'CNSTMRCHWPIG61',
            ],
        ]);

    $request = new PayoutRequestData(
        reference: 'TEST-Z3EL-09173011987-S1',
        amount: 75.00,
        account_number: '09173011987',
        bank_code: 'GXI',
        settlement_rail: 'instapay',
        currency: 'PHP',
        mobile: '639171234567',
        metadata: [
            'reason' => 'Voucher payout TEST-Z3EL-09173011987-S1',
        ],
    );

    $otpResolver->shouldReceive('resolve')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool =>
            $payload['account_id'] === 'CNSTMRCHWPIG61'
            && $payload['bank_account_no'] === '09173011987'
            && $payload['bank_id'] === 'GXI'
            && $payload['request_id'] === 'TEST-Z3EL-09173011987-S1'
            && $payload['reason'] === 'Voucher payout TEST-Z3EL-09173011987-S1'
            && $payload['amount'] === '75.00'
        ))
        ->andReturn('441498');

    $cashOut->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool =>
            $payload['account_id'] === 'CNSTMRCHWPIG61'
            && $payload['amount'] === '75.00'
            && $payload['request_id'] === 'TEST-Z3EL-09173011987-S1'
            && $payload['account_no'] === '09173011987'
            && $payload['bank_id'] === 'GXI'
            && $payload['reason'] === 'Voucher payout TEST-Z3EL-09173011987-S1'
            && $payload['otp'] === '441498'
            && $payload['wallet_id'] === 'CNSTWLLTTWXNQP'
        ))
        ->andReturn([
            'response_code' => 'GR162',
            'response_message' => 'Cash Out Pending',
            'request_id' => 'TEST-Z3EL-09173011987-S1',
            'amount' => '75.00',
        ]);

    $provider = new ConstellationPayoutProvider(
        createCashOutNonRegistered: $cashOut,
        getCashOutByRequestId: $getCashOutByRequestId,
        getTransactionByRequestId: $getTransactionByRequestId,
        getWalletDetails: $getWalletDetails,
        otpResolver: $otpResolver,
    );

    $result = $provider->disburse($request);

    expect($result->status)->toBe(PayoutStatus::PENDING)
        ->and($result->provider)->toBe('paynamics')
        ->and($result->transaction_id)->toBe('TEST-Z3EL-09173011987-S1');
});
