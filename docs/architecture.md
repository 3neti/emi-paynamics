# EMI-Backed Pay Code Architecture

## 1. System Actors & Glossary

| Actor | Role | Constellation Wallet |
|---|---|---|
| **Issuer** | Entity that generates Pay Codes (merchant/corporation) | Merchant Wallet |
| **Redeemer** | Receives and redeems Pay Codes (beneficiary) | **None required** — bank details only |
| **Platform (x-change)** | Orchestrates issuance, escrow, settlement | Treasury/Settlement Wallet |
| **Revenue Wallet** | Collects fees and instruction prices | Merchant Wallet (fee bucket) |
| **Phantom Wallet** | Per-voucher escrow — holds funds between issuance and redemption | Phantom Wallet (with expiry) |
| **Paynamics Constellation** | EMI provider — holds real money in real wallets | All wallets live here |
| **Destination Bank** | Where redeemed funds are disbursed | External — via InstaPay/PesoNet |

**Key term**: A **Pay Code** is a bank-issued digital settlement instrument represented by a short code. It is backed by real EMI wallet funds held in escrow via a phantom wallet.

### Actor Hierarchy

```
  ACTOR                  ROLE                      WALLET TYPE       VOLUME      COMMAND
  ═════                  ════                      ═══════════       ══════      ═══════
  Platform Operator      Runs x-change system      Merchant          Once        constellation:setup
                         (Settlement + Revenue)     (infrastructure)

  Issuer (B2B)           Subscribes to x-change     Merchant          Low         constellation:create-merchant
                         to generate Pay Codes      (per issuer)

  Customer (End User)    Subscriber who holds       Consumer          Optional    constellation:create-customer
                         a balance in the system    (per person)

  Redeemer               Receives Pay Code +        NONE              N/A         (just provides bank details)
                         redeems to bank account    (no wallet)
```

**Merchant wallets serve two distinct purposes:**
1. **Infrastructure** (Settlement + Revenue) — owned by the platform operator, created once via `constellation:setup`, stored in `.env`
2. **Issuer onboarding** — one per B2B merchant client, created via `constellation:create-merchant`

### Redeemers Do NOT Need a Constellation Wallet

This is a critical design simplification. The basic Pay Code redemption flow is:

```
  Redeemer presents Pay Code + bank details (account number, bank code)
           │
           │  x-change validates code
           │  SettleTransfer (phantom → settlement wallet)
           │  CreateCashOut (settlement wallet → redeemer's BANK ACCOUNT)
           ▼
  Money arrives in redeemer's bank account via InstaPay/PesoNet
```

The cash-out originates from the **platform's settlement wallet**, not from a customer wallet. The redeemer is just a bank account number. They never interact with Constellation directly.

**A customer wallet is only needed if:**
- The redeemer wants to **hold a balance** instead of immediate bank disbursement
- The redeemer wants to **receive wallet-to-wallet transfers**
- The platform wants to **top up a customer wallet** as an alternative to bank cash-out

For the vast majority of Pay Code redemptions, no customer wallet creation is needed. This keeps the high-volume redemption path lightweight — no Constellation onboarding, no KYC, no wallet provisioning.

---

## 1.1 Wallet Lifecycle Classification

Not all wallets are created equal. They differ in **when** they're created, **how long** they live, and **who** manages them.

```
  LIFECYCLE        WALLET                CREATED BY         WHEN CREATED          PERSISTENCE
  ═══════════      ══════════════════    ═══════════════    ═══════════════════   ═══════════════════
  Infrastructure   Settlement Wallet     System operator    System setup (once)   Permanent
  Infrastructure   Revenue Wallet        System operator    System setup (once)   Permanent
  Onboarding       Issuer Wallet         Onboarding flow    Per merchant          Permanent
  Onboarding       Customer Wallet       Onboarding flow    Per customer          Permanent (optional — only
                                                                                   if user needs balance)
  Transient        Phantom Wallet        Pay Code gen       Per voucher batch     Deactivated after use
```

### Infrastructure Wallets (created once, live forever)

**Settlement Wallet** and **Revenue Wallet** are created during initial system setup — before any issuer onboards or any Pay Code is generated. They are prerequisites for the system to function.

- Created manually by the system operator via `constellation:create-merchant`
- Their `wallet_id` values are stored in system config or the `ProviderAccount` record
- x-change **must enforce** their existence before allowing Pay Code generation
- These wallets are never deleted, locked, or recycled

```
  System Setup Checklist:
  ┌──────────────────────────────────────────────────────────┐
  │  1. Create Settlement Wallet  → store wallet_id in config│
  │  2. Create Revenue Wallet     → store wallet_id in config│
  │  3. Complete KYC for both     → compliance_level ≥ 1     │
  │  4. Top up Settlement Wallet  → operational float        │
  │  5. System is ready                                      │
  └──────────────────────────────────────────────────────────┘
```

### Onboarding Wallets (created per actor, live forever)

**Issuer Wallets** are created when a merchant/corporation onboards onto x-change. Each issuer gets exactly one wallet.

- Created via `constellation:create-merchant` + KYC (`constellation:kyc-link`)
- The issuer tops up this wallet via `constellation:cash-in`
- This wallet is the source of funds for all Pay Code generation
- Issuer wallets are permanent — they can be locked (`constellation:lock-wallet`) but not deleted

**Customer Wallets** (optional) are only created if a user needs to hold a balance or receive wallet-to-wallet transfers. **Not required for basic Pay Code redemption** — redeemers just provide their bank details and cash-out goes directly from the settlement wallet to their bank.

### Transient Wallets (created per operation, deactivated after use)

**Phantom Wallets** are the escrow mechanism. They are created fresh for each voucher batch and deactivated after all funds are settled or cancelled.

**Strategy: One phantom wallet per voucher batch (1:1 mapping)**

This is the recommended approach for auditability:
- Each Pay Code generation creates exactly one phantom wallet
- The phantom's `external_uid` links to the voucher batch ID
- All withheld funds in that phantom belong to that specific batch
- Reconciliation is trivial: phantom balance should be zero after all codes are redeemed

**Phantom wallet lifecycle:**

```
  CREATED              ACTIVE                    DRAINED              DEACTIVATED
  ═══════              ══════                    ═══════              ═══════════
  AddPhantomWallet     PreTransfer withholds     SettleTransfer or    All transfers
  with expiration      funds here                CancelTransfer       settled/cancelled
  date                                           drains funds         → phantom balance = 0
                                                                      → x-change marks
                                                                        local Wallet model
                                                                        status = 'closed'
```

**Why phantom wallets live forever in Constellation:**
- Constellation has no "delete wallet" API endpoint
- Expired/empty phantom wallets are inert — zero balance, no operational impact
- x-change tracks their state locally via the `Wallet` model (`status = closed`)
- The expiration date set at creation prevents new transfers into expired phantoms
- No cleanup needed on Constellation's side — just stop referencing them

**What x-change must enforce:**
1. Settlement Wallet and Revenue Wallet exist before any generation
2. Issuer wallet has sufficient balance before phantom creation
3. Each voucher batch gets its own phantom wallet
4. Phantom wallet `external_uid` is the voucher batch identifier
5. After all codes in a batch are redeemed/cancelled, mark phantom as closed locally
6. Never reuse a phantom wallet for a different batch

---

## 1.2 Credential vs Wallet Distinction

Paynamics Constellation credentials and wallets are **separate concepts**:

```
  CREDENTIAL                          PURPOSE                     IS A WALLET?
  ══════════                          ═══════                     ════════════
  Username / Password                 API authentication           NO — tenant access
  Merchant Key (integration_key)      SHA512 request signing        NO — signing key
  Wallet ID (CNSTWLLT...)             Identifies a wallet           YES
  Account ID (CNSTMRCH... / CNSTCUST...)  Identifies a wallet owner YES
```

**Key finding**: When Paynamics provides integration credentials (username, password, merchant key), **no wallet is auto-provisioned**. The credentials authenticate you as a tenant/integration partner, but wallets must be explicitly created via the API.

This means:
- API credentials = access to the Constellation platform (stored in `.env`)
- Infrastructure wallets = explicitly created via `constellation:setup`, KYC'd, and funded
- No hidden state — everything is traceable from the setup command onwards

---

## 1.3 System Readiness Enforcement

Three layers ensure infrastructure wallets exist before operations:

```
  LAYER       WHEN              COST          WHAT IT CHECKS
  ═════       ════              ════          ════════════════
  1. Config   App boot          Zero          Are wallet IDs in .env?
     Guard    (once per          (no HTTP)     Logs warning if missing.
              process)

  2. System   Pre-generation    Cached        Are wallets Active?
     Ready    (before Pay       (5 min TTL,   compliance_level ≥ 1?
     Service  Code gen)         then API)     Short-circuits on failure.

  3. Setup    One-time          Interactive   Creates wallets, shows
     Command  (operator runs)   (live API)    .env lines + KYC links.
```

**Config keys** (in `.env`):
- `CONSTELLATION_SETTLEMENT_WALLET_ID` — settlement wallet for cash-out operations
- `CONSTELLATION_REVENUE_WALLET_ID` — revenue wallet for fee collection

**Setup flow**:
```
  php artisan constellation:setup          # Create wallets interactively
  → displays: wallet IDs, KYC capture links, .env lines to add
  → logs: all IDs and capture links to storage/logs/constellation/

  (operator completes KYC via capture links, adds IDs to .env)

  php artisan constellation:setup --verify # Confirm everything works
  → checks: config present, wallets Active, compliance OK
```

**Contract**: `LBHurtado\EmiCore\Contracts\SystemReadiness`
**Implementation**: `LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSystemReadiness`
**Command**: `constellation:setup` / `constellation:setup --verify`

---

## 2. DFD-0 — Context Diagram

```
                              ┌───────────────────────────┐
                              │                           │
  [Issuer] ──── onboard ─────>│                           │
           ──── top-up ──────>│                           │
           ──── generate ────>│     x-change Platform     │──── disburse ────> [Destination Bank]
                              │     (Pay Code System)     │
  [Redeemer] ── redeem ──────>│                           │<─── postback ───── [Paynamics Constellation]
                              │                           │
  [QR Ph / InstaPay] ── pay ─>│                           │──── cash-in ─────> [Paynamics Constellation]
                              │                           │──── transfer ────> [Paynamics Constellation]
                              └───────────────────────────┘──── cash-out ────> [Paynamics Constellation]
```

**Data flows**:
- Onboarding: Issuer → Platform → Constellation (wallet creation + KYC)
- Top-up: Payment channel → Platform → Constellation (cash-in)
- Generation: Issuer → Platform → Constellation (pre-transfer to phantom)
- Redemption: Redeemer → Platform → Constellation (settle + cash-out)
- Settlement: Constellation → Destination Bank (InstaPay/PesoNet)
- Postback: Constellation → Platform (status confirmation)

---

## 3. DFD-1 — Functional Decomposition

```
  ┌────────────────────────────────────────────────────────────────────┐
  │                        x-change Platform                           │
  │                                                                    │
  │  ┌─────────────────┐   ┌─────────────────┐   ┌──────────────────┐  │
  │  │ 1.0 Onboarding  │   │ 2.0 Wallet      │   │ 3.0 Pay Code     │  │
  │  │                 │   │     Top-Up      │   │     Generation   │  │
  │  │ • AddCustomer   │   │                 │   │                  │  │
  │  │   Wallet        │   │ • GetPayment    │   │ • GetBalance     │  │
  │  │ • AddMerchant   │   │   Channels      │   │ • PreTransfer    │  │
  │  │   Wallet        │   │ • CreateCashIn  │   │   (→ phantom)    │  │
  │  │ • GenerateKyc   │   │ • Postback      │   │ • PreTransfer    │  │
  │  │   KybLink       │   │   confirmation  │   │   (→ revenue)    │  │
  │  └─────────────────┘   └─────────────────┘   │ • Issue code     │  │
  │                                              └──────────────────┘  │
  │  ┌─────────────────┐   ┌─────────────────┐   ┌──────────────────┐  │
  │  │ 4.0 Pay Code    │   │ 5.0 Reconcil-   │   │ 6.0 Reporting    │  │
  │  │     Redemption  │   │     iation      │   │                  │  │
  │  │                 │   │                 │   │ • GetTransaction │  │
  │  │ • Validate code │   │ • HandleWebhook │   │   ByRequestId    │  │
  │  │ • SettleTransfer│   │ • Signature     │   │ • GetTransaction │  │
  │  │   (phantom →    │   │   verification  │   │   ByWalletId     │  │
  │  │    settlement)  │   │ • Idempotent    │   │ • GetWithheld    │  │
  │  │ • CreateCashOut │   │   processing    │   │   ByPhantomId    │  │
  │  │ • Postback      │   │ • Reconciliation│   │ • GetWallet      │  │
  │  │   confirmation  │   │   Entry         │   │   Balance        │  │
  │  └─────────────────┘   └─────────────────┘   └──────────────────┘  │
  └────────────────────────────────────────────────────────────────────┘
```

---

## 4. Current State vs EMI State

```
  redeem-x (current)                    x-change + EMI (target)
  ════════════════════                  ═══════════════════════════
  Shared bank account                   Per-wallet real EMI money
  Bavix ledger balance                  Constellation-enforced balance
  No escrow                             Phantom wallet escrow (per-voucher)
  "Pray bank has funds"                 Guaranteed by PreTransfer withholding
  Netbank single rail                   InstaPay + PesoNet + OTC channels
  No KYC                                BSP-compliant KYC via capture_link
  No structured audit trail             JSON audit logs + postback receipts
  Manual reconciliation                 Postback-driven + reconciliation entries
  CheckFundsAvailability (HTTP)         GetWalletBalance (real-time EMI)
  DisburseCash → PaymentGateway         SettleTransfer → CreateCashOut
  WithdrawCash (after disbursement)     Funds already escrowed before code issued
```

**The fundamental shift**: In redeem-x, money leaves the system *after* redemption and you hope the bank has it. In x-change + EMI, money is escrowed *before* the code is issued, and disbursement is guaranteed.

---

## 5. Wallet Topology

```
  ┌────────────────────────────────────────────────────────────────┐
  │                    Paynamics Constellation                     │
  │                                                                │
  │  ┌─────────────-──┐   ┌───────────────┐   ┌────────────────┐   │
  │  │  Issuer        │   │  Revenue      │   │  Settlement    │   │
  │  │  Wallet        │   │  Wallet       │   │  Wallet        │   │
  │  │  (Merchant)    │   │  (Merchant)   │   │  (Merchant)    │   │
  │  │                │   │               │   │                │   │
  │  │  Holds issuer  │   │  Collects     │   │  Receives from │   │
  │  │  operating     │   │  instruction  │   │  phantom for   │   │
  │  │  balance       │   │  fees/markup  │   │  cash-out      │   │
  │  └──────┬─────────┘   └───────▲───────┘   └───────▲────────┘   │
  │         │                     │                   │            │
  │         │ PreTransfer         │ PreTransfer       │ Settle     │
  │         │ (voucher amt)       │ (fee amt)         │ Transfer   │
  │         ▼                     │                   │            │
  │  ┌──────────────────────────────────┐             │            │
  │  │       Phantom Wallet(s)          │─────────────┘            │
  │  │    (one per voucher batch)       │                          │
  │  │                                  │                          │
  │  │  • Created with expiration       │                          │
  │  │  • Funds WITHHELD here           │                          │
  │  │  • Queryable via GetWithheld     │                          │
  │  │  • Settled → settlement wallet   │                          │
  │  │  • Or cancelled → back to issuer │                          │
  │  └──────────────────────────────────┘                          │
  │                                                                │
  │  ┌───────────────┐                                             │
  │  │  Customer     │  (optional — for wallet-to-wallet           │
  │  │  Wallet       │   value transfers, not required             │
  │  │  (Consumer)   │   for basic Pay Code redemption)            │
  │  └───────────────┘                                             │
  └────────────────────────────────────────────────────────────────┘
                              │
                              │ CreateCashOut
                              ▼
                    ┌───────────────────-┐
                    │  Destination Bank  │
                    │  (via InstaPay /   │
                    │   PesoNet)         │
                    └──────────────────-─┘
```

---

## 6. Pay Code Generation Flow

```
  Issuer requests Pay Code generation
  │
  ├─ 1. GetWalletBalance(issuer_wallet_id)
  │     → returns: wallet_balance, remaining_wallet_limit, remaining_outflow_limit
  │     → total_cost = voucher_amount + instruction_price
  │     → if wallet_balance < total_cost → REJECT "Insufficient funds"
  │
  ├─ 2. AddPhantomWallet(external_uid, expiration)
  │     → creates escrow bucket for this voucher batch
  │     → returns: phantom_wallet_id
  │     Action: Wallets/AddPhantomWallet
  │     Command: constellation:create-phantom
  │
  ├─ 3. PreTransfer(issuer_wallet → phantom_wallet, voucher_amount)
  │     → funds WITHHELD in phantom wallet
  │     → returns: request_id, status=WITHHELD, remaining limits
  │     → request_id stored in voucher metadata for traceability
  │     Action: Transfers/PreTransfer
  │     Command: constellation:pre-transfer
  │
  ├─ 4. PreTransfer(issuer_wallet → revenue_wallet, instruction_price)
  │     → fee/markup captured in separate wallet
  │     → can be settled immediately or withheld
  │     Action: Transfers/PreTransfer
  │
  ├─ 5. SettleTransfer(fee_request_id)
  │     → immediately settle the fee transfer
  │     Action: Transfers/SettleTransfer
  │     Command: constellation:settle-transfer
  │
  ├─ 6. Generate Pay Code record
  │     → store: code, phantom_wallet_id, escrow_request_id, fee_request_id
  │     → code is now backed by real escrowed funds
  │
  └─ 7. Return Pay Code to issuer
       → escrow visible: constellation:withheld-phantom {phantom_wallet_id}
       → issuer balance reduced by total_cost
```

---

## 7. Pay Code Redemption Flow

```
  Redeemer presents Pay Code + bank details
  │
  ├─ 1. Validate Pay Code
  │     → exists? not expired? not already redeemed?
  │     → retrieve phantom_wallet_id and escrow_request_id from metadata
  │
  ├─ 2. SettleTransfer(escrow_request_id)
  │     → funds move from phantom → settlement_wallet
  │     → status: SETTLED
  │     → phantom escrow released
  │     Action: Transfers/SettleTransfer
  │     Command: constellation:settle-transfer
  │
  ├─ 3. CreateCashOut(settlement_wallet → destination_bank, voucher_amount)
  │     → initiates bank disbursement via InstaPay/PesoNet
  │     → returns: request_id, status=PENDING
  │     → OTP flow if required (request-otp → verify-transaction)
  │     Action: CashOut/CreateCashOut
  │     Command: constellation:cash-out
  │
  ├─ 4. Await postback confirmation
  │     → HandleConstellationWebhook processes postback
  │     → signature verified, receipt stored
  │     → status updated: SETTLED (money delivered)
  │     Action: Webhooks/HandleConstellationWebhook
  │
  ├─ 5. Create ReconciliationEntry
  │     → local_status vs provider_status recorded
  │     → drift detection for audit
  │
  └─ 6. Mark Pay Code as fully redeemed
       → voucher record updated
       → audit log entry written

  ABORT PATH (if redemption fails or is cancelled):
  │
  └─ CancelTransfer(escrow_request_id)
       → funds returned from phantom → issuer_wallet
       → Pay Code remains unredeemed
       Action: Transfers/CancelTransfer
       Command: constellation:cancel-transfer
```

---

## 8. Action Mapping Table

### Onboarding (Process 1.0)

| Step | Action Class | Command | Constellation Endpoint |
|---|---|---|---|
| Create merchant wallet | `Wallets/AddMerchantWallet` | `constellation:create-merchant` | POST /merchant_wallet/add |
| Create customer wallet | `Wallets/AddCustomerWallet` | `constellation:create-customer` | POST /customer_wallet/add |
| Create phantom wallet | `Wallets/AddPhantomWallet` | `constellation:create-phantom` | POST /phantom_wallet/add |
| Generate KYC link | `Wallets/GenerateKycKybLink` | `constellation:kyc-link` | POST /kyc_request |
| Get wallet details | `Wallets/GetWalletDetails` | `constellation:wallet-details` | GET /view_wallet/{id} |

### Top-Up (Process 2.0)

| Step | Action Class | Command | Constellation Endpoint |
|---|---|---|---|
| List payment channels | `CashIn/GetPaymentChannels` | `constellation:payment-channels` | GET /get_all_active_pchannels |
| Initiate cash-in | `CashIn/CreateCashIn` | `constellation:cash-in` | POST /cashin/create |
| Check cash-in status | `CashIn/GetCashInByRequestId` | `constellation:cash-in-status` | GET /cashin/get_cashin_by_reqid/{id} |

### Generation (Process 3.0)

| Step | Action Class | Command | Constellation Endpoint |
|---|---|---|---|
| Check balance | `Wallets/GetWalletBalance` | `constellation:wallet-balance` | GET /check_balance/{id} |
| Escrow to phantom | `Transfers/PreTransfer` | `constellation:pre-transfer` | POST /transfer_pre |
| Fee to revenue | `Transfers/PreTransfer` | `constellation:pre-transfer` | POST /transfer_pre |
| Settle fee | `Transfers/SettleTransfer` | `constellation:settle-transfer` | POST /transfer_settle |

### Redemption (Process 4.0)

| Step | Action Class | Command | Constellation Endpoint |
|---|---|---|---|
| Settle escrow | `Transfers/SettleTransfer` | `constellation:settle-transfer` | POST /transfer_settle |
| Cancel escrow | `Transfers/CancelTransfer` | `constellation:cancel-transfer` | POST /transfer_cancel |
| Cash out to bank | `CashOut/CreateCashOut` | `constellation:cash-out` | POST /withdraw_request |
| Request OTP | `CashOut/CreateCashOutOtp` | `constellation:request-otp` | POST /request_otp |
| Verify PIN | `CashOut/VerifyTransaction` | `constellation:verify-transaction` | POST /transaction/verify_request |
| Check cash-out status | `CashOut/GetCashOutByRequestId` | `constellation:cash-out-status` | GET /withdraw/get_by_request_id/{id} |

### Reconciliation (Process 5.0)

| Step | Action Class | Command | Constellation Endpoint |
|---|---|---|---|
| Process postback | `Webhooks/HandleConstellationWebhook` | (route) | POST /webhooks/constellation |
| Query transaction | `Transactions/GetTransactionByRequestId` | `constellation:transaction` | GET /elastic_trx/get_by_request_id/{id} |
| Query withheld | `PhantomWallets/GetWithheldByPhantomWalletId` | `constellation:withheld-phantom` | GET /withhelds/phantom_wallet/{id} |

---

## 9. Pay Code Lifecycle — Complete Picture

```
  ISSUANCE                          CIRCULATION              REDEMPTION
  ════════                          ═══════════              ══════════

  ┌─────────-┐    ┌──────────┐    ┌──────────────┐    ┌──────────────┐
  │  Issuer  │───>│ Phantom  │───>│  Pay Code    │───>│  Redeemer    │
  │  tops up │    │ escrow   │    │  distributed │    │  presents    │
  │  wallet  │    │ created  │    │  to redeemer │    │  code + bank │
  └────────-─┘    └──────────┘    └──────────────┘    └──────┬───────┘
       │              │                                      │
       │         funds WITHHELD                         SettleTransfer
       │         in phantom                             + CreateCashOut
       │              │                                      │
       │              │                                      ▼
       │              │                              ┌──────────────┐
       │              └─── on cancel ───────────────>│  Destination │
       │                   funds return              │  Bank acct   │
       │                   to issuer                 │  (InstaPay)  │
       │                                             └──────────────┘
       │
  total_cost deducted:
  voucher_amount → phantom
  instruction_price → revenue wallet
```

---

## 10. Package & Model Reference

### emi-core Models

| Model | Role in Pay Code Flow |
|---|---|
| `ProviderAccount` | Stores Constellation API credentials |
| `Wallet` | Local mirror of Constellation wallets (issuer, revenue, settlement, phantom) |
| `WalletProfile` | KYC identity fields for wallet holders |
| `Transaction` | Master record for every transfer/cash-in/cash-out (indexed by request_id) |
| `Transfer` | Pre-transfer/settle/cancel lifecycle detail |
| `CashIn` | Cash-in detail (payment method, channel) |
| `CashOut` | Cash-out detail (bank account, OTP status) |
| `WebhookReceipt` | Raw postback payload + signature verification result |
| `ReconciliationEntry` | Local vs provider status drift detection |

### emi-paynamics-constellation Components

| Component | Purpose |
|---|---|
| `ConstellationClient` | HTTP wrapper (Basic Auth, JSON) |
| `ConstellationSigner` | SHA512 request signature generation |
| `ConstellationSignatureVerifier` | Postback signature verification |
| `FakesConstellationHttp` | `--fake` flag for offline command testing |
| `LogsConstellationActivity` | Structured JSON audit logging |
| 46 Action classes | One per Constellation API endpoint |
| 47 Console Commands | CLI interface for all actions |
