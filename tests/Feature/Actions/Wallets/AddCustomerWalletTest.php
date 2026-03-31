<?php

use Illuminate\Support\Facades\Http;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddCustomerWallet;

beforeEach(function () {
    Http::fake([
        '*/integration/corp_wallet/customer_wallet/add' => Http::response([
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTABCDEF',
                'account_id' => 'CNSTCSTMR12345',
                'account_no' => '071127093068',
                'wallet_type' => 'Personal',
                'status' => 'Active',
                'balance' => '0.00',
                'currency' => 'PHP',
                'compliance_level' => '0',
                'required_compliance' => 'level 1',
                'verification_status' => 'PENDING',
                'external_uid' => 'test-ext-uid-123',
                'capture_link' => 'https://capture.kyc.idfy.com/captures?t=abc123',
                'notification_url' => 'https://example.com/webhook',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan@example.com',
                'phone_number' => '639171234567',
                'address' => '123 Main St',
                'city' => 'Makati',
                'state' => 'Metro Manila',
                'zip' => '1200',
                'country' => 'PH',
            ],
        ]),
    ]);
});

it('sends a customer wallet creation request with signature', function () {
    $result = AddCustomerWallet::run([
        'first_name' => 'Juan',
        'middle_name' => '',
        'last_name' => 'Dela Cruz',
        'email' => 'juan@example.com',
        'mobile_no' => '639171234567',
        'address' => '123 Main St',
        'zip' => '1200',
        'city' => 'Makati',
        'state' => 'Metro Manila',
        'country' => 'PH',
        'username' => 'juan.delacruz',
        'password' => 'SecurePass@123',
        'birthdate' => '1999-03-17',
        'nationality' => 'Filipino',
        'source_of_funds' => 'Employment',
        'profile_type' => 'DEFAULT_CONSUMER',
        'external_uid' => 'test-ext-uid-123',
        'notification_url' => 'https://example.com/webhook',
        'success_url' => 'https://example.com/kyc/success',
        'failed_url' => 'https://example.com/kyc/failed',
        'device_information' => ['device_id' => 'test', 'os_version' => '1.0'],
        'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'WiFi'],
    ]);

    expect($result['success'])->toBeTrue()
        ->and($result['data']['wallet_id'])->toBe('CNSTWLLTABCDEF')
        ->and($result['data']['account_id'])->toBe('CNSTCSTMR12345')
        ->and($result['data']['status'])->toBe('Active');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'customer_wallet/add')
            && $request['signature'] !== null
            && $request['first_name'] === 'Juan'
            && $request['profile_type'] === 'DEFAULT_CONSUMER';
    });
});

it('returns wallet identifiers from the response', function () {
    $result = AddCustomerWallet::run([
        'first_name' => 'Test',
        'middle_name' => '',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'mobile_no' => '639170000000',
        'address' => 'Addr',
        'zip' => '1000',
        'city' => 'City',
        'state' => 'State',
        'country' => 'PH',
        'profile_type' => 'DEFAULT_CONSUMER',
        'device_information' => ['device_id' => 'x', 'os_version' => '1'],
        'network_information' => ['ip_address' => '1.1.1.1', 'network_type' => 'LTE'],
    ]);

    expect($result['data'])
        ->toHaveKeys(['wallet_id', 'account_id', 'account_no', 'capture_link', 'external_uid']);
});
