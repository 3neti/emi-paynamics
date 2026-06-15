# Getting Started With Paynamics Constellation

This guide is the operational checklist for using `lbhurtado/emi-paynamics-constellation` from a host Laravel/x-change app. The README is the package reference; this file is the step-by-step memory aid for setup, probing, wallet transfers, and disbursements.

## 1. Install And Publish Config

In the host app:

```bash
composer require lbhurtado/emi-paynamics-constellation
php artisan vendor:publish --tag=constellation-config
php artisan migrate
```

Clear cached config after changing `.env`:

```bash
php artisan optimize:clear
```

Use fake mode before touching live Paynamics:

```bash
php artisan constellation:probe --fake
php artisan constellation:wallet-balance ANY_WALLET --fake
```

## 2. Credentials To Get From Paynamics

Ask Paynamics for these Constellation v1.42 sandbox/live values:

```env
CONSTELLATION_BASE_URL=https://asterism.payserv.net/v1
CONSTELLATION_USERNAME=
CONSTELLATION_PASSWORD=
CONSTELLATION_MERCHANT_KEY=
CONSTELLATION_NOTIFICATION_URL=https://your-host.test/webhooks/constellation
```

Meaning:

- `CONSTELLATION_BASE_URL` is the Constellation API endpoint. Sandbox/live may differ by tenant.
- `CONSTELLATION_USERNAME` and `CONSTELLATION_PASSWORD` are the HTTP Basic Auth credentials.
- `CONSTELLATION_MERCHANT_KEY` is the signing key. Paynamics/Postman may call this the integration key.
- `CONSTELLATION_NOTIFICATION_URL` is the public webhook/postback URL.

Also ask Paynamics/operations for:

- Allowed merchant/customer profile IDs, usually discovered by `constellation:probe`.
- Supported bank list and whether each entry is InstaPay/PesoNet.
- Which wallet types are allowed to do cash-out. Do not assume a merchant wallet can disburse to an outside bank.
- Tenant-required KYC/KYB fields and redirect URLs.

## 3. Minimum Host `.env`

Start with:

```env
CONSTELLATION_BASE_URL=https://asterism.payserv.net/v1
CONSTELLATION_USERNAME=...
CONSTELLATION_PASSWORD=...
CONSTELLATION_MERCHANT_KEY=...
CONSTELLATION_NOTIFICATION_URL=https://your-app.com/webhooks/constellation

CONSTELLATION_LOG_CHANNEL=constellation
CONSTELLATION_OTP_RESOLVER=interactive
```

After infrastructure wallet setup, add:

```env
CONSTELLATION_SETTLEMENT_WALLET_ID=CNSTWLLT...
CONSTELLATION_REVENUE_WALLET_ID=CNSTWLLT...
```

For x-change Paynamics payout mode:

```env
XCHANGE_PAYOUT_PROVIDER='LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider'
XCHANGE_WITHDRAWAL_OTP_DRIVER=paynamics
X_CHANGE_CLAIM_APPROVAL_OTP_DRIVER=withdrawal_otp
```

Optional fees and bank mapping:

```env
CONSTELLATION_INSTAPAY_FEE=0
CONSTELLATION_PESONET_FEE=0
```

If the host app has `config/emi.php`, it may also support provider labels:

```env
EMI_PAYOUT_PROVIDER=paynamics
```

The concrete values come from the host app config:

- `XCHANGE_PAYOUT_PROVIDER` is a class name. Use `LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider`.
- `XCHANGE_WITHDRAWAL_OTP_DRIVER` is an x-change withdrawal OTP driver. Use `paynamics` when the Paynamics adapter should request provider OTP.
- `X_CHANGE_CLAIM_APPROVAL_OTP_DRIVER` is an x-change claim approval driver. Use `withdrawal_otp` so a Paynamics pending OTP can be completed by the claim approval flow.
- `EMI_PAYOUT_PROVIDER=paynamics` is a label only if `config/emi.php` maps `paynamics` to `ConstellationPayoutProvider`.

## 4. Merchant Account Vs Customer Account

Merchant wallets are corporate/business wallets. In x-change terms, these are used for platform infrastructure and B2B issuers:

- Settlement wallet: platform escrow/source for payouts.
- Revenue wallet: platform fee/revenue destination.
- Issuer merchant wallet: B2B merchant that funds/generates Pay Codes.

Customer wallets are consumer/end-user wallets. They are for subscribers who need to hold a Paynamics wallet balance.

Redeemers usually do not need a Paynamics wallet. For ordinary Pay Code redemption, the redeemer provides bank details and the platform settlement wallet performs a cash-out to the bank.

Important operational reminder: merchant wallets often cannot disburse directly to outside/non-Paynamics bank accounts, depending on tenant configuration. They can usually transfer to another Paynamics wallet. Always validate disbursement permission with a real small cash-out test before assuming wallet-to-bank payouts work.

## 5. Setup Flow

Probe credentials:

```bash
php artisan constellation:probe
```

This confirms Basic Auth/signing can reach Paynamics and shows available consumer/merchant profiles plus supported bank count.

Create or attach infrastructure wallets:

```bash
php artisan constellation:setup
```

or, if Paynamics already created them:

```bash
php artisan constellation:setup \
  --settlement-wallet-id=CNSTWLLT... \
  --revenue-wallet-id=CNSTWLLT...
```

Then put the printed wallet IDs in `.env` and verify:

```bash
php artisan optimize:clear
php artisan constellation:setup --verify
```

For wallet creation gotchas:

- TIN must be 15 characters or fewer.
- TIN and merchant email must be unique per merchant wallet.
- `account_middle_name` may be required even if it is an empty string.
- Merchant wallet passwords commonly need at least 12 chars with upper/lower/number/special.
- Sandbox may auto-approve KYC and return an empty capture link.

## 6. Probe Wallets, Banks, And IDs

Wallet details:

```bash
php artisan constellation:wallet-details CNSTWLLT...
```

Record these fields from the output:

- `wallet_id`: used for balance checks, wallet-to-wallet transfer, transaction lookup, and non-registered cash-out source wallet.
- `account_id`: used by cash-out OTP and cash-out commands.
- `account_no`: useful for audit/debugging; not the same as a bank account number.
- `status`, `verification_status`, `compliance_level`: confirm the wallet is usable.

Balance and limits:

```bash
php artisan constellation:wallet-balance CNSTWLLT...
```

Supported banks:

```bash
php artisan constellation:supported-banks
```

Record the bank `code`/`id` from Paynamics output. For non-registered cash-out, the command asks for `Bank ID`; use the Paynamics bank identifier expected by the endpoint. In x-change provider mode, `ConstellationPayoutProvider` maps the request bank code through `config('constellation.bank_map')` when present, otherwise it sends the bank code as-is.

## 7. Wallet-To-Wallet Transfer

Use transfer when moving value between Paynamics wallets.

Pre-transfer withholds funds:

```bash
php artisan constellation:pre-transfer SOURCE_WALLET_ID DESTINATION_WALLET_ID 75.00
```

The command prompts for:

- `Request ID`: choose a unique reference, for example `TRF-20260616-001`.
- `Remarks`: short audit description.

Expected success code for pre-transfer is `GR005`.

Confirm withheld funds:

```bash
php artisan constellation:withheld SOURCE_WALLET_ID
php artisan constellation:transaction REQUEST_ID
```

Settle the transfer:

```bash
php artisan constellation:settle-transfer REQUEST_ID
```

Expected success code for settlement is `GR006`.

Cancel instead of settling:

```bash
php artisan constellation:cancel-transfer REQUEST_ID
```

Recent v1.42 lesson: `transfer_pre` requires `request_id` and `remarks` inside a nested `payload` object. The action keeps the public PHP/console input flat, but sends the correct provider body internally.

## 8. Bank Disbursement / Cash-Out

The provider-backed x-change flow uses `ConstellationPayoutProvider`, which cashes out from `CONSTELLATION_SETTLEMENT_WALLET_ID`.

Manual command flow for a non-registered bank account:

```bash
php artisan constellation:wallet-details CONSTELLATION_SETTLEMENT_WALLET_ID
php artisan constellation:supported-banks
php artisan constellation:request-otp
php artisan constellation:cash-out-nr ACCOUNT_ID 75.00
php artisan constellation:cash-out-status REQUEST_ID
```

`constellation:request-otp` prompts for:

- `Account ID`: from wallet details, usually the settlement wallet account ID.
- `Bank account number`: beneficiary bank account number.
- `Request ID`: unique payout reference.
- `Reason`: payout reason.
- `Amount`: amount as a decimal string.

`constellation:cash-out-nr {accountId} {amount}` prompts for:

- `Request ID`: same payout reference you want to track.
- `Destination account number`: beneficiary bank account number.
- `Bank ID`: Paynamics bank identifier.
- `Beneficiary first name`
- `Beneficiary last name`
- `Beneficiary address`
- `Reason`
- `Wallet ID`: source Paynamics wallet ID, usually the settlement wallet.

Check status by request ID:

```bash
php artisan constellation:cash-out-status REQUEST_ID
php artisan constellation:transaction REQUEST_ID
```

In x-change automated payout mode, the payout request must include or resolve:

- `reference`: unique payout request ID.
- `amount`
- `bank_code`
- `account_number`
- beneficiary name/address in metadata when available.

The adapter resolves:

- source wallet from `CONSTELLATION_SETTLEMENT_WALLET_ID`.
- source account ID by calling wallet details.
- bank ID from `constellation.bank_map.{bank_code}` or the bank code itself.
- OTP using `CONSTELLATION_OTP_RESOLVER` and/or the x-change deferred OTP drivers.

## 9. OTP Modes

Package-level Paynamics OTP resolver:

```env
CONSTELLATION_OTP_RESOLVER=interactive
```

Supported package resolver values:

- `interactive`: prompts in console for the provider OTP.
- `deferred`: records pending OTP state for approval/replay flows.
- `null`: test/no-op resolver.

For x-change claim approval with Paynamics:

```env
XCHANGE_WITHDRAWAL_OTP_DRIVER=paynamics
X_CHANGE_CLAIM_APPROVAL_OTP_DRIVER=withdrawal_otp
```

This models Paynamics payout OTP as an approval state:

```text
Paynamics asks for payout OTP
-> claim returns approval_required
-> approval OTP is submitted
-> claim replay resumes payout
-> reconciliation follows provider status
```

If you switch back to Netbank, remove/comment Paynamics-specific values:

```env
# XCHANGE_PAYOUT_PROVIDER='LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider'
# XCHANGE_WITHDRAWAL_OTP_DRIVER=paynamics
# X_CHANGE_CLAIM_APPROVAL_OTP_DRIVER=withdrawal_otp
```

## 10. Useful Debug Commands

```bash
php artisan constellation
php artisan constellation:probe
php artisan constellation:wallet-details CNSTWLLT...
php artisan constellation:wallet-balance CNSTWLLT...
php artisan constellation:supported-banks
php artisan constellation:withheld CNSTWLLT...
php artisan constellation:transaction REQUEST_ID
php artisan constellation:transactions CNSTWLLT...
php artisan constellation:cash-out-status REQUEST_ID
```

Use `--fake` on any command for local dry runs.

Audit logs are written to the configured `CONSTELLATION_LOG_CHANNEL`, defaulting to `storage/logs/constellation/constellation.log` as a daily log channel. Sensitive fields such as password, PIN, OTP, and signature are redacted by the command logger.

## 11. Known Good Mental Model

- Paynamics credentials authenticate and sign API calls.
- Paynamics wallet IDs identify value stores.
- Paynamics account IDs identify the owner/account behind a wallet and are needed for cash-out OTP.
- Wallet-to-wallet transfer is a two-step lifecycle: pre-transfer then settle/cancel.
- Bank disbursement is a cash-out lifecycle and commonly requires OTP.
- x-change `XCHANGE_PAYOUT_PROVIDER` chooses the payout implementation.
- x-change withdrawal/claim OTP drivers decide whether Paynamics OTP is handled inline or as approval-required claim state.
- A successful JSON response shape varies by endpoint. Always check the endpoint-specific business code, not just whether JSON was returned.
