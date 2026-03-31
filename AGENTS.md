# emi-paynamics-constellation — AI Agent Context

## Package Identity
- **Composer**: `lbhurtado/emi-paynamics-constellation`
- **Namespace**: `LBHurtado\EmiPaynamicsConstellation`
- **Depends on**: `lbhurtado/emi-core`
- **Purpose**: Concrete Paynamics Constellation EMI adapter — all HTTP, signing, and provider-specific logic
- **API Version**: Constellation v1.42
- **Base URL**: `https://asterism.payserv.net/v1`
- **Auth**: HTTP Basic Auth (username/password)

## Directory Layout
```
src/
  Actions/
    Wallets/          — AddMerchantWallet, AddCustomerWallet, AddPhantomWallet, EditWallet, GetWalletDetails, GetWalletBalance, GenerateKycKybLink
    Transfers/        — PreTransfer, SettleTransfer, CancelTransfer
    CashIn/           — CreateCashIn
    CashOut/          — CreateCashOut, CreateCashOutOtp, VerifyTransaction
    BankAccounts/     — AddBankAccount
    PhantomWallets/   — GetWithheldByWalletId, GetWithheldByPhantomWalletId
    ValueAddedServices/ — AirtimeLoad, BillsPayment
    Transactions/     — GetTransactionByRequestId, GetTransactionByWalletId
    Webhooks/         — HandleConstellationWebhook
  Http/
    ConstellationClient.php — HTTP wrapper (baseUrl, basicAuth, JSON)
  Support/
    ConstellationSigner.php — SHA512 request signature: implode(fields) + merchant_key
    ConstellationSignatureVerifier.php — postback verification: code + message + advise + timestamp + key
config/
  constellation.php — base_url, username, password, merchant_key, notification_url
routes/
  constellation.php — POST /webhooks/constellation
tests/
  TestCase.php — extends Orchestra\Testbench\TestCase, registers both ServiceProviders
  Pest.php
  Unit/Support/ — signer and verifier unit tests with known fixtures
  Feature/Actions/ — all action tests using Http::fake()
  Feature/Webhooks/ — idempotency, signature rejection, raw storage tests
  Feature/Package/ — boot, config, contract binding tests
```

## Signature Formulas (SHA512 hex)
Each action concatenates specific fields + `merchant_key`:
- **AddMerchantWallet**: company_name + tin + email + website + username + password + account_first_name + account_last_name + profile_type + mobile_no
- **AddCustomerWallet**: first_name + middle_name + last_name + email + mobile_no + address + zip + city + state + country + username + password + profile_type
- **AddPhantomWallet**: external_uid + expiration + profile_type
- **EditWallet**: json_encode(data) (PATCH semantics)
- **CashIn**: request_id + wallet_id + pmethod + pchannel + amount + response_url + cancel_url
- **PreTransfer**: amount + source_wallet_id + destination_wallet_id + request_id + remarks
- **SettleTransfer / CancelTransfer**: request_id + remarks
- **CashOut**: account_id + bank_account_no + request_id + amount
- **CashOutOtp**: account_id + bank_account_no + request_id + reason + amount
- **VerifyTransaction**: request_id + wallet_id + pin + timestamp
- **AddBankAccount**: 16 fields (see action source)
- **AirtimeLoad**: request_id + amount + sku + recipient_name + recipient_mobile
- **BillsPayment**: request_id + biller_code + biller_fee + payee_name + payee_mobile + payee_email
- **Postback verification**: code + message + advise + timestamp + integration_key

## Webhook Processing
`HandleConstellationWebhook::run($payload)`:
1. Check idempotency by `postback_id` — return existing if already processed
2. Store raw payload in `WebhookReceipt` immediately (before any mutation)
3. Verify signature using `ConstellationSignatureVerifier`
4. Update receipt: `signature_verified`, `processing_status`, `processed_at`
5. On invalid signature: mark `signature_failed`, store `error_message`

## ServiceProvider Bindings
- `SignsProviderPayloads` → `ConstellationSigner`
- `VerifiesProviderPostbacks` → `ConstellationSignatureVerifier`

## Config Keys
All read from `config('constellation.*')`:
- `base_url` — Constellation API base (default: `https://asterism.payserv.net/v1`)
- `username` — Basic Auth username
- `password` — Basic Auth password
- `merchant_key` — SHA512 signature key (also called `integration_key` in Postman)
- `notification_url` — default webhook callback URL

## Testing Conventions
- Run via host: `composer test:constellation`
- All HTTP calls use `Http::fake()` — zero live API calls in tests
- Signature tests use deterministic fixture values
- Webhook tests verify: raw storage before mutation, signature rejection, idempotency by postback_id

## Adding a New Endpoint Action
1. Create action class in the appropriate `Actions/` subdirectory
2. Use `AsAction` trait, inject `ConstellationClient` and `ConstellationSigner`
3. Build signature from the correct field order (check Postman collection pre-request script)
4. Call `$this->client->post/get/patch()` with the endpoint path
5. Return `$response->json()`
6. Write test with `Http::fake()` asserting: correct URL, signature present, response mapping

## Field Requirements (from live API testing)
These fields were discovered to be required/important during live Constellation API testing:
**AddMerchantWallet — all required by this tenant:**
- `company_name`, `tin` (max 15 chars, must be unique per wallet), `email` (unique per merchant), `mobile_no`
- `username`, `password` (min 12 chars, upper+lower+number+special) — conditional per tenant but required for ours
- `account_first_name`, `account_middle_name` (required even if empty), `account_last_name`
- `birthdate` (yyyy-MM-dd), `nationality`, `source_of_funds`
- `business_address`, `business_zip`, `business_city`, `business_state`, `business_country`
- `profile_type`, `external_uid`, `notification_url`
- `success_url`, `failed_url` (KYC redirect URLs — conditional per tenant)
- `device_information` (device_id, os_version), `network_information` (ip_address, network_type)
**AddCustomerWallet — mandatory fields:**
- `first_name`, `last_name`, `email`, `mobile_no`, `profile_type`
- `device_information`, `network_information`
- Optional but recommended: `middle_name`, `address`, `zip`, `city`, `state`, `country`, `username`, `password`, `birthdate`, `nationality`, `source_of_funds`, `success_url`, `failed_url`
**Gotchas discovered:**
- TIN must be ≤ 15 characters (use base TIN like `777-324-175`, not full branch code)
- TIN must be unique per merchant wallet — cannot reuse for Settlement + Revenue
- Email must be unique per merchant wallet
- `account_middle_name` is required for merchant wallets even if value is empty string
- Sandbox may auto-approve KYC (compliance_level 0.5, verification_status APPROVED, empty capture_link)
## Actor Hierarchy
- **Platform Operator** → `constellation:setup` (creates Settlement + Revenue infrastructure wallets, run once)
- **Issuer (B2B merchant)** → `constellation:create-merchant` (per client, low volume)
- **Customer (end user)** → `constellation:create-customer` (per subscriber, high volume)
## Reference
- **Postman Collection**: `~/Downloads/Share - Constellation v1.42.postman_collection 3 (1).json`
- Signature formulas are in the collection's pre-request scripts
- Response formats: `{ "success": bool, "data": { ... } }` for success; `{ "success": false, "data": { "response_code": "GR...", "response_message": "...", "response_advise": "..." } }` for failure
