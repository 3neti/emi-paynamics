<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddMerchantWallet;

beforeEach(function () {
    Http::fake([
        '*/integration/corp_wallet/merchant_wallet/add' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTTEST01',
                'account_id' => 'CNSTMRCHTEST01',
                'account_no' => '692408370175',
                'wallet_type' => 'Business',
                'status' => 'Active',
                'balance' => '0.00',
                'currency' => 'PHP',
                'compliance_level' => '0.5',
                'verification_status' => 'APPROVED',
                'external_uid' => 'test-merchant',
                'capture_link' => '',
                'notification_url' => 'https://example.com/webhook',
                'first_name' => 'Lester',
                'last_name' => 'Hurtado',
                'email' => 'test@example.com',
                'phone_number' => '639173011987',
                'nationality' => 'Filipino',
                'source_of_funds' => 'Family Savings',
                'address' => 'E1504 PSE Centre',
                'city' => 'Pasig City',
                'state' => 'Metro Manila',
                'zip' => '1605',
                'country' => 'PH',
            ],
        ]),
    ]);
});

it('sends a merchant wallet creation request with all required fields', function () {
    $result = AddMerchantWallet::run([
        'company_name' => '3neti Research and Development OPC',
        'tin' => '777-324-175',
        'email' => 'test@example.com',
        'mobile_no' => '639173011987',
        'website' => 'https://example.com',
        'username' => 'test.merchant',
        'password' => 'TestPass@12345',
        'account_first_name' => 'Lester',
        'account_middle_name' => 'Biadora',
        'account_last_name' => 'Hurtado',
        'birthdate' => '1970-04-21',
        'nationality' => 'Filipino',
        'source_of_funds' => 'Family Savings',
        'business_address' => 'E1504 PSE Centre',
        'business_zip' => '1605',
        'business_city' => 'Pasig City',
        'business_state' => 'Metro Manila',
        'business_country' => 'PH',
        'profile_type' => 'DEFAULT_MERCHANT',
        'external_uid' => 'test-merchant',
        'notification_url' => 'https://example.com/webhook',
        'success_url' => 'https://example.com/kyc/success',
        'failed_url' => 'https://example.com/kyc/failed',
        'device_information' => ['device_id' => 'test', 'os_version' => '1.0'],
        'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['wallet_id'])->toBe('CNSTWLLTTEST01')
        ->and($result['data']['wallet_type'])->toBe('Business')
        ->and($result['data']['status'])->toBe('Active')
        ->and($result['data']['nationality'])->toBe('Filipino');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'merchant_wallet/add')
            && $request['signature'] !== null
            && $request['company_name'] === '3neti Research and Development OPC'
            && $request['account_middle_name'] === 'Biadora'
            && $request['nationality'] === 'Filipino'
            && $request['source_of_funds'] === 'Family Savings'
            && $request['birthdate'] === '1970-04-21'
            && $request['username'] === 'test.merchant';
    });
});
