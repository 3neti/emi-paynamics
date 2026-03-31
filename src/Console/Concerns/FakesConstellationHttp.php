<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Concerns;

use Illuminate\Support\Facades\Http;

trait FakesConstellationHttp
{
    protected function fakeIfRequested(): void
    {
        if (! $this->option('fake')) {
            return;
        }

        Http::fake([
            '*/get_available_profiles' => Http::response(['success' => true, 'data' => ['consumer_profiles' => [['id' => 'DEFAULT_CONSUMER', 'max_kyc_level' => 2]], 'merchant_profiles' => [['id' => 'DEFAULT_MERCHANT', 'max_kyc_level' => 4]]]]),
            '*/get_all_supported_banks' => Http::response(['success' => true, 'data' => [['code' => 'BDO', 'name' => 'BDO Unibank', 'id' => 'bank-001']]]),
            '*/get_all_active_pchannels' => Http::response(['success' => true, 'data' => [['code' => 'INSTAPAY', 'name' => 'InstaPay']]]),
            '*/merchant_wallet/add' => Http::response(['success' => true, 'data' => ['wallet_id' => 'CNSTWLLTFAKE01', 'account_id' => 'CNSTMRCHFAKE01', 'account_no' => '123456789012', 'capture_link' => 'https://capture.kyc.idfy.com/fake', 'status' => 'Active', 'compliance_level' => '0', 'verification_status' => 'PENDING']]),
            '*/customer_wallet/add' => Http::response(['success' => true, 'data' => ['wallet_id' => 'CNSTWLLTFAKE02', 'account_id' => 'CNSTCUSTFAKE02', 'account_no' => '987654321098', 'capture_link' => 'https://capture.kyc.idfy.com/fake', 'status' => 'Active', 'compliance_level' => '0', 'verification_status' => 'PENDING']]),
            '*/phantom_wallet/add' => Http::response(['success' => true, 'data' => ['response_code' => 'GR171', 'wallet_id' => 'CNSTPHNTMFAKE1', 'response_message' => 'Phantom Wallet created.']]),
            '*/view_wallet/*' => Http::response(['success' => true, 'data' => ['wallet_id' => 'CNSTWLLTFAKE01', 'account_id' => 'CNSTMRCHFAKE01', 'status' => 'Active', 'balance' => '1000.00', 'compliance_level' => '1', 'verification_status' => 'APPROVED', 'currency' => 'PHP']]),
            '*/check_balance/*' => Http::response(['wallet_id' => 'CNSTWLLTFAKE01', 'wallet_balance' => '1000.00', 'current_balance' => '1000.00', 'remaining_wallet_limit' => '49000.00', 'remaining_inflow_limit' => '90000.00', 'remaining_annual_inflow_limit' => '500000.00', 'remaining_outflow_limit' => '49000.00', 'currency' => 'PHP']),
            '*/edit_wallet/*' => Http::response(['success' => true, 'data' => ['wallet_id' => 'CNSTWLLTFAKE01', 'status' => 'Active']]),
            '*/threshold/*' => Http::response(['code' => 'GR039', 'message' => 'Threshold successfully set.']),
            '*/lock_wallet' => Http::response(['code' => 'GR020', 'message' => 'Wallet successfully locked.']),
            '*/unlock_wallet' => Http::response(['code' => 'GR021', 'message' => 'Wallet successfully unlocked.']),
            '*/kyc_request' => Http::response(['success' => true, 'data' => ['response_code' => 'GR169', 'capture_link' => 'https://capture.kyc.idfy.com/fake', 'response_message' => 'Wallet KYC Request Pending']]),
            '*/cashin/create' => Http::response(['success' => true, 'data' => ['response_code' => 'GR158', 'response_message' => 'Cash In Pending', 'request_id' => 'FAKE-CI-001', 'payment_action_info' => ['url' => 'https://pay.example.com/fake']]]),
            '*/cashin/get_cashin_by_reqid/*' => Http::response(['success' => true, 'data' => ['request_id' => 'FAKE-CI-001', 'status' => 'PENDING']]),
            '*/transfer_pre' => Http::response(['code' => 'GR005', 'message' => 'Request for Pre-transfer success.', 'request_id' => 'FAKE-TRF-001', 'remaining_wallet_limit' => '48500.00']),
            '*/transfer_settle' => Http::response(['code' => 'GR006', 'message' => 'Request for transfer success.', 'request_id' => 'FAKE-TRF-001']),
            '*/transfer_cancel' => Http::response(['code' => 'GR007', 'message' => 'Cancelled Pre-transfer request.', 'request_id' => 'FAKE-TRF-001']),
            '*/bank_account/create' => Http::response(['success' => true, 'data' => ['bank_account_id' => 'FAKEBA001']]),
            '*/bank_account/fetch_by_mid/*' => Http::response(['success' => true, 'data' => [['bank_account_id' => 'FAKEBA001', 'bank_code' => 'BDO']]]),
            '*/request_otp' => Http::response(['success' => true, 'data' => 'OTP successfully sent to 639170000000']),
            '*/transaction/verify_request' => Http::response(['success' => true, 'data' => ['response_code' => 'GR175', 'response_message' => 'Verification Success.']]),
            '*/withdraw_request' => Http::response(['response_code' => 'GR162', 'response_message' => 'Cash Out Pending', 'request_id' => 'FAKE-CO-001', 'amount' => '500.00']),
            '*/withdraw/get_by_request_id/*' => Http::response(['success' => true, 'data' => ['request_id' => 'FAKE-CO-001', 'status' => 'PENDING']]),
            '*/elastic_trx/get_by_request_id/*' => Http::response(['success' => true, 'data' => [['request_id' => 'FAKE-TRF-001', 'status' => 'SETTLED']]]),
            '*/elastic_trx/get_by_wallet_id/*' => Http::response(['success' => true, 'data' => []]),
            '*/withhelds/wallet/*' => Http::response(['success' => true, 'data' => []]),
            '*/withhelds/phantom_wallet/*' => Http::response(['success' => true, 'data' => []]),
            '*/get_products' => Http::response(['success' => true, 'data' => [['sku' => 'GLOBE100', 'name' => 'Globe 100', 'amount' => '100.00']]]),
            '*/airtime_load' => Http::response(['success' => true, 'data' => ['request_id' => 'FAKE-AT-001', 'status' => 'PENDING']]),
            '*/get_billers' => Http::response(['success' => true, 'data' => [['biller_code' => 'MERALCO', 'name' => 'Meralco']]]),
            '*/bills_payment' => Http::response(['success' => true, 'data' => ['request_id' => 'FAKE-BP-001', 'status' => 'PENDING']]),
            '*' => Http::response(['success' => true, 'data' => []]),
        ]);

        $this->components->warn('Running in FAKE mode — no live API calls');
    }
}
