# lbhurtado/emi-paynamics-constellation

Concrete Paynamics Constellation adapter for `lbhurtado/emi-core`. Implements wallet management, fund transfers, cash-in/out, OTP verification, phantom wallets, and value-added services against the Constellation v1.42 API.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- `lbhurtado/emi-core`

## Installation

```bash
composer require lbhurtado/emi-paynamics-constellation
```

The package auto-discovers its service provider. Publish the config:

```bash
php artisan vendor:publish --tag=constellation-config
```

## Configuration

Add to your `.env`:

```
CONSTELLATION_BASE_URL=https://asterism.payserv.net/v1
CONSTELLATION_USERNAME=your_username
CONSTELLATION_PASSWORD=your_password
CONSTELLATION_MERCHANT_KEY=your_merchant_key
CONSTELLATION_NOTIFICATION_URL=https://your-app.com/webhooks/constellation
```

## API Coverage

All actions use the Laravel Actions pattern (`Lorisleiva\Actions`) and can be called via `Action::run(...)`.

### Wallet Management
- `AddMerchantWallet::run($data)` — create merchant wallet
- `AddCustomerWallet::run($data)` — create customer wallet
- `AddPhantomWallet::run($data)` — create phantom wallet
- `EditWallet::run($walletId, $data)` — PATCH semantics, only sends provided fields
- `GetWalletDetails::run($walletIdOrExternalUid)` — fetch wallet details
- `GetWalletBalance::run($walletId)` — fetch balance + limit fields
- `GenerateKycKybLink::run($data)` — generate KYC/KYB capture link

### Fund Transfers (staged lifecycle)
- `PreTransfer::run($data)` — reserve/withhold funds
- `SettleTransfer::run($data)` — commit withheld transfer
- `CancelTransfer::run($data)` — release withheld transfer

### Cash In
- `CreateCashIn::run($data)` — initiate cash-in with payment method/channel

### Cash Out
- `CreateCashOut::run($data)` — initiate cash-out to registered bank
- `CreateCashOutOtp::run($data)` — request OTP for cash-out
- `VerifyTransaction::run($data)` — verify with PIN/OTP

### Bank Accounts
- `AddBankAccount::run($data)` — register a bank account

### Phantom Wallets & Withheld Funds
- `GetWithheldByWalletId::run($walletId)`
- `GetWithheldByPhantomWalletId::run($phantomWalletId)`

### Value Added Services
- `AirtimeLoad::run($data)` — airtime/load purchase
- `BillsPayment::run($data)` — bills payment

### Transaction Queries
- `GetTransactionByRequestId::run($requestId)`
- `GetTransactionByWalletId::run($walletId)`

### Webhooks
- `HandleConstellationWebhook::run($payload)` — idempotent postback handler with signature verification

## Signature Handling

All signed requests use SHA512 hex-encoded signatures. The `ConstellationSigner` concatenates specific fields per endpoint + the merchant key. The `ConstellationSignatureVerifier` validates postback signatures using `code + message + advise + timestamp + merchant_key`.

## Who Uses What

| Actor | Role | Command | Volume |
|---|---|---|---|
| **Platform Operator** | Runs x-change (creates Settlement + Revenue wallets) | `constellation:setup` | Once |
| **Issuer (B2B)** | Merchant subscribing to x-change to generate Pay Codes | `constellation:create-merchant` | Low (per client) |
| **Customer (End User)** | Subscriber who wants to hold a balance | `constellation:create-customer` | Optional |
| **Redeemer** | Receives Pay Code, redeems to bank account | None — just bank details | N/A |

- `constellation:setup` creates the **infrastructure wallets** (Settlement + Revenue) that the platform itself uses for escrow settlement and fee collection. Run once during system deployment.
- `constellation:create-merchant` onboards a **B2B issuer** — a company that tops up their wallet and generates Pay Codes for distribution.
- `constellation:create-customer` onboards an **end-user subscriber** who wants to hold a balance. **Optional** — not needed for basic Pay Code redemption.
- **Redeemers don't need a Constellation wallet.** They just provide bank details (account number + bank code) and cash-out goes directly from the platform's settlement wallet to their bank via InstaPay/PesoNet.

## Artisan Commands

The package ships with 48 artisan commands (hub + setup + 46 endpoint commands), auto-registered via the ServiceProvider. All commands support a `--fake` flag for offline testing and include structured audit logging.

### Interactive Hub

```bash
php artisan constellation          # Interactive launcher — select any command from a grouped menu
php artisan constellation --fake   # Same, but all selected commands use fake HTTP responses
```

### Onboarding & Wallet Lifecycle

```bash
php artisan constellation:probe                          # Smoke test — verify API credentials
php artisan constellation:create-merchant                # Interactive — create merchant wallet
php artisan constellation:create-customer                # Interactive — create customer wallet
php artisan constellation:create-phantom                 # Create phantom wallet
php artisan constellation:wallet-details {walletId}      # Get wallet details
php artisan constellation:wallet-balance {walletId}      # Get balance + limits
php artisan constellation:edit-wallet {walletId}         # Interactive PATCH — update wallet fields
php artisan constellation:kyc-link {accountId} {level}   # Generate KYC/KYB capture link
php artisan constellation:lock-wallet {walletId}         # Lock wallet
php artisan constellation:unlock-wallet {walletId}       # Unlock wallet
php artisan constellation:set-threshold {walletId} {amt} # Set minimum balance threshold
```

### Cash In

```bash
php artisan constellation:payment-channels               # List available payment channels
php artisan constellation:cash-in {walletId} {amount}    # Interactive — initiate cash-in
php artisan constellation:cash-in-status {requestId}     # Check cash-in status by request ID
php artisan constellation:cash-ins {accountId}           # List cash-ins by account ID
```

### Fund Transfer

```bash
php artisan constellation:pre-transfer {src} {dst} {amt} # Pre-transfer (withhold)
php artisan constellation:settle-transfer {requestId}    # Settle a pre-transfer
php artisan constellation:cancel-transfer {requestId}    # Cancel a pre-transfer
```

### Cash Out

```bash
php artisan constellation:supported-banks                # List supported banks
php artisan constellation:add-bank-account {accountId}   # Interactive — register bank account
php artisan constellation:edit-bank-account {id}         # Interactive — edit bank account
php artisan constellation:remove-bank-account {id}       # Remove a bank account
php artisan constellation:bank-accounts {accountId}      # List registered bank accounts
php artisan constellation:request-otp                    # Interactive — request OTP for cash-out
php artisan constellation:resend-otp {acctId} {reqId}    # Resend cash-out OTP
php artisan constellation:verify-transaction             # Interactive — verify with PIN
php artisan constellation:cash-out {accountId} {amount}  # Interactive — cash out (registered bank)
php artisan constellation:cash-out-nr {acctId} {amount}  # Interactive — cash out (non-registered bank)
php artisan constellation:cash-out-status {requestId}    # Check cash-out status
php artisan constellation:cash-outs {accountId}          # List cash-outs by account ID
```

### Transactions & Withheld

```bash
php artisan constellation:transaction {requestId}        # Get transaction by request ID
php artisan constellation:transactions {walletId}        # Get transactions by wallet ID
php artisan constellation:withheld {walletId}            # Withheld funds by wallet ID
php artisan constellation:withheld-by-account {acctId}   # Withheld funds by account ID
php artisan constellation:withheld-phantom {phantomId}   # Withheld funds by phantom wallet ID
```

### Value Added Services

```bash
php artisan constellation:airtime-products               # List airtime products
php artisan constellation:airtime-load                   # Interactive — purchase airtime
php artisan constellation:airtime-status {requestId}     # Check airtime load status
php artisan constellation:airtime-history                # Interactive — airtime history by partner
php artisan constellation:billers                        # List billers
php artisan constellation:biller-details {billerCode}    # Get biller details
php artisan constellation:biller-fee                     # Interactive — get biller fee
php artisan constellation:biller-request {billerCode}    # Generate biller payment request
php artisan constellation:pay-bill                       # Interactive — pay a bill
php artisan constellation:bill-status {requestId}        # Check bill payment status
php artisan constellation:bill-history                   # Interactive — bill history by partner
```

### The `--fake` Flag

Every command supports `--fake` to run without hitting the live Paynamics API:

```bash
php artisan constellation:probe --fake                   # Uses fixture responses, zero network calls
php artisan constellation:wallet-balance FAKE01 --fake   # Works with any wallet ID in fake mode
```

Fake mode uses realistic fixture responses matching the Constellation v1.42 API format. Commands display a warning when running in fake mode.

### Audit Logging

All commands log structured JSON to `storage/logs/constellation/constellation-YYYY-MM-DD.log`:

```json
{"command":"constellation:probe","is_fake":false,"input":{"action":"probe"},"operator":"rli","timestamp":"2026-03-24T22:56:28+00:00"}
{"command":"constellation:probe","is_fake":false,"success":true,"duration_ms":1006,"timestamp":"2026-03-24T22:56:29+00:00"}
```

Sensitive fields (password, pin, otp, signature) are automatically redacted in logs. The log channel is configurable via `CONSTELLATION_LOG_CHANNEL` in `.env`.

## Architecture

```
ConstellationClient           — HTTP wrapper (Basic Auth, JSON, base URL from config)
ConstellationSigner           — SHA512 request signature generation
ConstellationSignatureVerifier — postback signature verification
ConstellationServiceProvider  — binds contracts, loads config/migrations/routes/commands

Actions/                      — 46 actions covering all Constellation v1.42 endpoints
  Wallets/                    — 11 actions (Add/Edit/Get/Lock/Unlock/Threshold/KYC/Profiles)
  Transfers/                  — 3 actions (Pre/Settle/Cancel)
  CashIn/                     — 4 actions (Create + queries)
  CashOut/                    — 8 actions (Create/OTP/Verify + queries)
  BankAccounts/               — 4 actions (Add/Edit/Remove/Get)
  PhantomWallets/             — 3 query actions
  ValueAddedServices/         — 11 actions (Airtime + Bills + queries)
  Transactions/               — 2 query actions
  Webhooks/                   — 1 idempotent handler

Console/
  Commands/                   — 47 artisan commands (hub + 46 endpoint commands)
  Concerns/
    FakesConstellationHttp    — --fake flag trait with fixture responses
    LogsConstellationActivity — Structured JSON audit logging trait
```

## Testing

```bash
# From the monorepo host
composer test:constellation
```

All action tests use `Http::fake()` — no live API calls during testing.

## License

Proprietary — Lester Hurtado
