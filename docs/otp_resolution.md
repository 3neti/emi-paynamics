# Paynamics OTP Resolution Architecture

## Purpose

This document describes the Paynamics OTP payout architecture implemented in `emi-paynamics` and how orchestrators such as `x-change` should invoke it.

The objective is to support both:

```text
1. Interactive OTP entry (CLI / Lifecycle Scenario Runner)
2. Deferred OTP entry (Web Claim Approval UI)
```

without changing the payout provider implementation.

---

# Core Principle

The payout provider should not know:

```text
- where the OTP came from
- who entered the OTP
- whether the flow is CLI or Web
```

The provider only knows:

```text
I need an OTP before I can submit the payout.
```

Therefore OTP acquisition is delegated to:

```php
ConstellationOtpResolver
```

---

# Provider Flow

The Paynamics payout provider executes the following sequence:

```text
ConstellationPayoutProvider::disburse()
    ↓
resolve settlement wallet
    ↓
resolve bank account
    ↓
request OTP through ConstellationOtpResolver
    ↓
receive OTP
    ↓
submit cash-out request
    ↓
return payout result
```

Simplified:

```text
Payout
    ↓
OTP Resolver
    ↓
Cash Out
```

The payout provider never reads from:

```text
STDIN
Session
Cache
HTTP Request
Vue UI
```

Those concerns belong to the resolver implementation.

---

# OTP Resolver Contract

```php
interface ConstellationOtpResolver
{
    public function resolve(array $otpRequestPayload): string;
}
```

Responsibilities:

```text
1. Request OTP from Paynamics
2. Obtain OTP value
3. Return OTP string
```

The payout provider waits for:

```php
$otp = $this->otpResolver->resolve(...);
```

before continuing.

---

# Interactive OTP Resolver

Implementation:

```php
InteractiveOtpResolver
```

Used by:

```text
Lifecycle Scenario Runner
CLI tools
Manual testing
```

Flow:

```text
Payout Provider
    ↓
InteractiveOtpResolver
    ↓
CreateCashOutOtp
    ↓
Prompt user
    ↓
User enters OTP
    ↓
OTP returned
    ↓
Cash-out continues
```

Sequence:

```text
request OTP
→ display prompt
→ wait for input
→ return OTP
```

This is the current working implementation used by the lifecycle scenario runner.

---

# Deferred OTP Resolver

Implementation:

```php
DeferredOtpResolver
```

Purpose:

```text
Web flows cannot block waiting for user input.
```

Therefore:

```text
request OTP
→ persist pending OTP context
→ throw pending OTP exception
```

instead of waiting.

Flow:

```text
Payout Provider
    ↓
DeferredOtpResolver
    ↓
CreateCashOutOtp
    ↓
Store pending OTP request
    ↓
Throw PendingConstellationOtpException
```

The payout is intentionally paused.

---

# Pending OTP Exception

Class:

```php
PendingConstellationOtpException
```

Purpose:

```text
Signal that payout execution requires OTP approval.
```

The exception contains:

```text
request_id
provider
target
approval metadata
```

allowing the caller to render an approval UI.

Example:

```php
throw PendingConstellationOtpException::fromPayload(
    $otpRequestPayload,
    $otpRequestResult
);
```

---

# Pending OTP Store

Contract:

```php
PendingOtpStore
```

Purpose:

```text
Bridge deferred OTP flows.
```

Responsibilities:

```text
Store pending OTP challenge
Retrieve submitted OTP
```

The resolver does not care where storage occurs.

Possible implementations:

```text
Cache
Database
Session
Approval Workflow Store
```

This storage is intentionally externalized.

---

# Resolver Selection

Resolver selection is controlled through:

```php
ConstellationOtpResolver::class
```

binding.

Current service provider:

```php
ConstellationServiceProvider
```

supports resolver drivers.

Example:

```php
'otp' => [
    'resolver' => 'interactive',

    'resolvers' => [
        'interactive' => InteractiveOtpResolver::class,
        'deferred' => DeferredOtpResolver::class,
        'null' => NullOtpResolver::class,
    ],
],
```

---

# CLI Invocation

Lifecycle runner uses:

```text
InteractiveOtpResolver
```

Flow:

```text
disburse()
    ↓
OTP requested
    ↓
operator enters OTP
    ↓
payout continues
```

No approval UI is involved.

---

# Web Invocation

Web applications should use:

```text
DeferredOtpResolver
```

Flow:

```text
disburse()
    ↓
OTP requested
    ↓
PendingConstellationOtpException
    ↓
approval_required state
    ↓
user enters OTP
    ↓
OTP persisted
    ↓
retry payout
    ↓
resolver returns OTP
    ↓
cash-out proceeds
```

The payout provider itself remains unchanged.

---

# Architectural Boundary

## emi-paynamics Owns

```text
ConstellationOtpResolver
InteractiveOtpResolver
DeferredOtpResolver
PendingConstellationOtpException
CreateCashOutOtp
ConstellationPayoutProvider
```

## Orchestrator Owns

Examples:

```text
x-change
Lifecycle Scenario Runner
Future payout orchestrators
```

Responsibilities:

```text
Resolver selection
Pending OTP persistence
Approval UI
OTP submission UX
Retry execution
```

The orchestrator decides how humans provide OTPs.

The provider only requires a resolver.

---

# Result

A single payout provider implementation supports:

```text
CLI OTP approval
Web OTP approval
Future API OTP approval
```

without modifying:

```php
ConstellationPayoutProvider
```

The resolver abstraction becomes the sole OTP integration seam.
