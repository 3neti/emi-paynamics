# Transfer Console Command Implementation Plan

## Purpose

Make Paynamics wallet-to-wallet transfer work reliably through the Constellation console commands.

Target user flow:

```bash
php artisan constellation:pre-transfer <sourceWalletId> <destinationWalletId> <amount>
php artisan constellation:settle-transfer <requestId>
```

Observed live use case:

```text
source wallet:      CNSTWLLT5BPIUG
destination wallet: CNSTWLLT9GSPQ1
amount:            75.00
```

## Current Finding

Direct API calls using the Postman v1.42 request shape work.

The package console command path does not.

Live direct API result for `200.00`:

```text
transfer_pre    -> GR005 Request for Pre-transfer success.
transfer_settle -> GR006 Request for transfer settlement success.
```

Live console command result for `75.00`:

```bash
php artisan constellation:pre-transfer CNSTWLLT5BPIUG CNSTWLLT9GSPQ1 75.00
```

The command exited with code `0`, but Constellation logging showed:

```text
success: false
response_code: GR052
request_id: IGNORED
```

Follow-up checks confirmed no transfer was created:

```bash
php artisan constellation:withheld CNSTWLLT5BPIUG
```

returned:

```text
No withheld funds.
```

and:

```bash
php artisan constellation:transaction TRF-e834bec0-e2ef-4520-92d6-4086c1951b49
```

returned:

```text
GR015 Reference number not found.
```

## Root Cause Hypothesis

The package implementation is out of sync with the Constellation v1.42 Postman request body for `transfer_pre`.

Current package action:

```php
PreTransfer::handle(array $data)
```

sends:

```json
{
  "amount": "75.00",
  "source_wallet_id": "CNSTWLLT5BPIUG",
  "destination_wallet_id": "CNSTWLLT9GSPQ1",
  "request_id": "TRF-...",
  "remarks": "Pre-transfer via console",
  "device_information": {},
  "network_information": {},
  "signature": "..."
}
```

The Postman v1.42 collection expects:

```json
{
  "amount": "75.00",
  "source_wallet_id": "CNSTWLLT5BPIUG",
  "destination_wallet_id": "CNSTWLLT9GSPQ1",
  "signature": "...",
  "payload": {
    "request_id": "TRF-...",
    "remarks": "Pre-transfer via console"
  },
  "device_information": {},
  "network_information": {},
  "meta_data": {}
}
```

The Postman pre-request signature formula is still:

```text
amount + source_wallet_id + destination_wallet_id + payload.request_id + payload.remarks + merchant_key
```

So the signature formula in the package is directionally correct, but the request body shape is wrong for v1.42.

## Files To Change

Primary files:

```text
src/Actions/Transfers/PreTransfer.php
src/Console/Commands/PreTransferCommand.php
tests/Feature/Actions/Transfers/PreTransferTest.php
```

Possible supporting files:

```text
src/Console/Commands/SettleTransferCommand.php
tests/Feature/Actions/Transfers/SettleTransferTest.php
README.md
docs/architecture.md
AGENTS.md
```

Only update docs if the behavior or command usage changes.

## Implementation Strategy

### 1. Keep The Public Action Input Stable

Callers should still be able to use:

```php
PreTransfer::run([
    'amount' => '75.00',
    'source_wallet_id' => 'CNSTWLLT5BPIUG',
    'destination_wallet_id' => 'CNSTWLLT9GSPQ1',
    'request_id' => 'TRF-...',
    'remarks' => 'Pre-transfer via console',
    'device_information' => [...],
    'network_information' => [...],
]);
```

Do not force callers to know the Paynamics nested `payload` shape.

The action should adapt the stable package input into the provider request.

### 2. Build The Provider Payload In `PreTransfer`

Inside `PreTransfer::handle()`, derive:

```php
$requestId = (string) ($data['request_id'] ?? data_get($data, 'payload.request_id', ''));
$remarks = (string) ($data['remarks'] ?? data_get($data, 'payload.remarks', ''));
```

Generate the signature using:

```php
$signature = $this->signer->generateSignature([
    $data['amount'] ?? '',
    $data['source_wallet_id'] ?? '',
    $data['destination_wallet_id'] ?? '',
    $requestId,
    $remarks,
], config('constellation.merchant_key'));
```

Then send this body:

```php
$payload = [
    'amount' => $data['amount'] ?? '',
    'source_wallet_id' => $data['source_wallet_id'] ?? '',
    'destination_wallet_id' => $data['destination_wallet_id'] ?? '',
    'signature' => $signature,
    'payload' => [
        'request_id' => $requestId,
        'remarks' => $remarks,
    ],
    'device_information' => $data['device_information'] ?? [],
    'network_information' => $data['network_information'] ?? [],
    'meta_data' => $data['meta_data'] ?? new \stdClass,
];
```

Post to:

```text
/integration/corp_wallet/transfer_pre
```

### 3. Normalize The Response Shape

`transfer_pre` returns a flat success payload, not the common `{ success, data }` shape.

Observed success:

```json
{
  "code": "GR005",
  "message": "Request for Pre-transfer success.",
  "request_id": "TRF260615163216551"
}
```

Observed failure from the console path:

```json
{
  "success": false,
  "data": {
    "response_code": "GR052",
    "response_message": "...",
    "request_id": "IGNORED"
  }
}
```

The command must not treat any JSON response as success.

Add a small response check in `PreTransferCommand`:

```php
$isSuccess = ($result['code'] ?? null) === 'GR005';
```

If not success, display:

```text
response_code
response_message
response_advise
request_id
```

from either the flat response or `data`.

Return `self::FAILURE`.

### 4. Make Console Output Operational

On success, show at least:

```text
Request ID
Message
Advise
Remaining Wallet Limit
Remaining Inflow Limit
Remaining Outflow Limit
```

Then tell the operator the next command:

```bash
php artisan constellation:settle-transfer <requestId>
```

Do not auto-settle inside `pre-transfer`; keep the two-step flow explicit.

### 5. Verify `SettleTransfer` Still Matches Postman

Postman v1.42 body:

```json
{
  "signature": "<signature>",
  "request_id": "<request_id>",
  "remarks": "<remarks>"
}
```

Signature formula:

```text
request_id + remarks + merchant_key
```

Current package action appears aligned.

Still add or update a test to assert:

```text
POST /integration/corp_wallet/transfer_settle
body.request_id
body.remarks
body.signature
```

## Tests To Add Or Update

### PreTransfer Action Test

Use `Http::fake()`.

Assert that the outgoing body contains:

```php
$request['amount'] === '75.00'
$request['source_wallet_id'] === 'CNSTWLLT5BPIUG'
$request['destination_wallet_id'] === 'CNSTWLLT9GSPQ1'
$request['payload']['request_id'] === 'TRF-001'
$request['payload']['remarks'] === 'Pre-transfer via console'
$request['request_id'] === null // or missing
$request['remarks'] === null // or missing
$request['signature'] !== null
```

Also assert the signature equals:

```php
hash('sha512', '75.00'.'CNSTWLLT5BPIUG'.'CNSTWLLT9GSPQ1'.'TRF-001'.'Pre-transfer via console'.config('constellation.merchant_key'))
```

### PreTransfer Command Success Test

If the package already has console command tests, add one for:

```text
GR005 response exits success
prints request id
prints settle-transfer next command
```

### PreTransfer Command Failure Test

Add one for:

```text
success=false / GR052 exits failure
prints response_message and response_advise
does not print settle-transfer instruction
```

This protects against the exact live issue where the command exited `0` even though Paynamics rejected the request.

### SettleTransfer Command Test

Assert:

```text
GR006 response exits success
non-GR006 provider response exits failure
```

## Manual Verification Plan

Use a small amount first.

### 1. Check balances

```bash
php artisan constellation:wallet-balance CNSTWLLT5BPIUG
php artisan constellation:wallet-balance CNSTWLLT9GSPQ1
```

### 2. Run pre-transfer

```bash
php artisan constellation:pre-transfer CNSTWLLT5BPIUG CNSTWLLT9GSPQ1 75.00
```

Expected success:

```text
code: GR005
message: Request for Pre-transfer success.
request_id: <actual-request-id>
```

### 3. Confirm withheld funds

```bash
php artisan constellation:withheld CNSTWLLT5BPIUG
```

Expected:

```text
request_id: <actual-request-id>
withheld_amount: 75.00
status: PENDING
transaction_type: TRANSFER
```

### 4. Settle

```bash
php artisan constellation:settle-transfer <actual-request-id>
```

Expected success:

```text
code: GR006
message: Request for transfer settlement success.
```

### 5. Re-check balances

```bash
php artisan constellation:wallet-balance CNSTWLLT5BPIUG
php artisan constellation:wallet-balance CNSTWLLT9GSPQ1
```

Expected:

```text
source wallet decreases by 75.00
destination wallet increases by 75.00
```

## Known Good Direct API Baseline

The following live direct API flow succeeded using the Postman body shape:

```text
source_wallet_id: CNSTWLLT5BPIUG
destination_wallet_id: CNSTWLLT9GSPQ1
amount: 200.00
request_id: TRF260615163216551
```

`transfer_pre` response:

```text
code: GR005
message: Request for Pre-transfer success.
```

`transfer_settle` response:

```text
code: GR006
message: Request for transfer settlement success.
```

Balances after settlement:

```text
CNSTWLLT5BPIUG: 400.00
CNSTWLLT9GSPQ1: 329.80 wallet_balance / 379.80 current_balance
```

## Important Notes

- Amounts must use two decimal places, for example `75.00`.
- Do not use live Paynamics API in automated tests.
- Use `Http::fake()` for all tests.
- Keep the console command two-step: pre-transfer first, settle second.
- Failure responses from Paynamics must produce a non-zero command exit code.
- The Salt & Pepper authentication document is not required for this transfer console fix. It describes a future PIN/biometric authentication replacement flow and does not change the current `transfer_pre` / `transfer_settle` request body.

