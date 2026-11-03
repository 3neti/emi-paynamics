<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use BackedEnum;
use Illuminate\Support\Str;
use LBHurtado\EmiCore\Contracts\ProviderCustomerProvisioner;
use LBHurtado\EmiCore\Contracts\ProviderDefaultProvisioningMode;
use LBHurtado\EmiCore\Contracts\ProviderProvisioningGateway;
use LBHurtado\EmiCore\Enums\ComplianceLevel;
use LBHurtado\EmiCore\Enums\ProviderCode;
use LBHurtado\EmiCore\Enums\VerificationStatus;
use LBHurtado\EmiCore\Enums\WalletStatus;
use LBHurtado\EmiCore\Enums\WalletType;
use LBHurtado\EmiCore\Models\BankAccount;
use LBHurtado\EmiCore\Models\ProviderAccount;
use LBHurtado\EmiCore\Models\Wallet;
use RuntimeException;

class ConstellationProvisioningGateway implements ProviderDefaultProvisioningMode, ProviderProvisioningGateway
{
    public function defaultProvisioningMode(): string
    {
        return 'wallet_create';
    }

    public function providerCode(): string
    {
        return 'paynamics';
    }

    private function customerProvisioner(): ProviderCustomerProvisioner
    {
        $matches = collect(app()->tagged('emi.provider-customer-provisioners'))
            ->filter(fn (mixed $candidate): bool => $candidate instanceof ProviderCustomerProvisioner
                && $candidate->providerCode() === ProviderCode::PaynamicsConstellation->value);

        if ($matches->count() !== 1) {
            throw new RuntimeException('A unique customer provisioner is required for the provider.');
        }

        return $matches->first();
    }

    public function supports(string $provider, string $mode): bool
    {
        if (strtolower($provider) !== 'paynamics') {
            return false;
        }

        return in_array($mode, [
            'wallet_create',
            'wallet_resolve',
            'bank_account_link',
            'hybrid',
        ], true);
    }

    public function provision(mixed $owner, array $payload): array
    {
        $provider = 'paynamics';
        $mode = (string) data_get($payload, 'mode', 'wallet_create');
        $topology = (string) data_get($payload, 'topology', 'provider_customer_wallet');

        if (! $this->supports($provider, $mode)) {
            throw new RuntimeException("Paynamics provisioning mode [{$mode}] is not supported.");
        }

        $providerAccount = $this->resolveProviderAccount();
        $statusOverride = $this->normalizeLinkStatus(data_get($payload, 'status'));
        $walletContext = $this->provisionWallet($owner, $providerAccount, $payload, $mode, $statusOverride);
        $wallet = $walletContext['wallet'];

        $bankAccountContext = null;

        if ($this->shouldLinkBankAccount($mode, $payload)) {
            $bankAccountContext = $this->linkBankAccount($owner, $wallet, $payload, $statusOverride);
        }

        $status = $this->determineStatus($statusOverride, $wallet, $bankAccountContext['bank_account'] ?? null, $mode);

        if ($statusOverride === 'ready') {
            $wallet = $this->markWalletReady($wallet);
        }

        return [
            'provider' => $provider,
            'topology' => $topology,
            'purpose' => data_get($payload, 'purpose'),
            'mode' => $mode,
            'status' => $status,
            'emi_provider_account_id' => $providerAccount->getKey(),
            'emi_wallet_id' => $wallet->getKey(),
            'emi_bank_account_id' => data_get($bankAccountContext, 'bank_account.id'),
            'provider_account_id' => $wallet->provider_account_id_value,
            'provider_wallet_id' => $wallet->provider_wallet_id,
            'provider_bank_account_id' => data_get($bankAccountContext, 'bank_account.provider_bank_account_id'),
            'external_uid' => $wallet->external_uid,
            'verification_status' => $this->enumValue($wallet->verification_status),
            'identity_level' => $this->enumValue($wallet->compliance_level),
            'capture_url' => $wallet->capture_link,
            'capabilities' => [
                'issue_pay_code' => $status === 'ready',
                'redeem_pay_code' => $status === 'ready',
            ],
            'metadata' => array_filter([
                'fake' => ! $this->usesLiveRequests($payload),
                'wallet_id' => $wallet->provider_wallet_id,
                'account_id' => $wallet->provider_account_id_value,
                'capture_url' => $wallet->capture_link,
                'bank_code' => data_get($bankAccountContext, 'bank_account.bank_code'),
                'account_number_masked' => data_get($bankAccountContext, 'bank_account.account_number_masked'),
            ], static fn (mixed $value): bool => $value !== null),
            'raw' => array_filter([
                'wallet' => data_get($walletContext, 'response'),
                'kyc' => data_get($walletContext, 'kyc_response'),
                'bank_account' => data_get($bankAccountContext, 'response'),
            ], static fn (mixed $value): bool => is_array($value) && $value !== []),
        ];
    }

    public function refresh(mixed $link): array
    {
        $identifier = $this->walletIdentifier([
            'provider_wallet_id' => data_get($link, 'provider_wallet_id'),
            'external_uid' => data_get($link, 'external_uid'),
        ]);

        if ($identifier === null) {
            return [
                'status' => data_get($link, 'status', 'pending'),
                'refreshed' => false,
            ];
        }

        $providerAccount = $this->resolveProviderAccount();
        $response = $this->walletDetailsResponse($identifier, []);
        $wallet = $this->persistWallet(
            null,
            $providerAccount,
            (array) data_get($response, 'data', []),
            [],
            null,
            null,
        );

        return [
            'status' => $this->determineStatus(null, $wallet, null, 'wallet_resolve'),
            'provider_wallet_id' => $wallet->provider_wallet_id,
            'provider_account_id' => $wallet->provider_account_id_value,
            'verification_status' => $this->enumValue($wallet->verification_status),
            'identity_level' => $this->enumValue($wallet->compliance_level),
            'emi_wallet_id' => $wallet->getKey(),
            'refreshed' => true,
        ];
    }

    /**
     * @return array{wallet: Wallet, response: array<string, mixed>, kyc_response: array<string, mixed>|null}
     */
    protected function provisionWallet(
        mixed $owner,
        ProviderAccount $providerAccount,
        array $payload,
        string $mode,
        ?string $statusOverride,
    ): array {
        return match ($mode) {
            'wallet_create' => $this->createWallet($owner, $providerAccount, $payload, $statusOverride),
            'wallet_resolve' => $this->resolveWallet($owner, $providerAccount, $payload, $statusOverride),
            'bank_account_link',
            'hybrid' => $this->ensureWallet($owner, $providerAccount, $payload, $statusOverride),
            default => throw new RuntimeException("Paynamics provisioning mode [{$mode}] is not supported."),
        };
    }

    /**
     * @return array{wallet: Wallet, response: array<string, mixed>, kyc_response: array<string, mixed>|null}
     */
    protected function createWallet(
        mixed $owner,
        ProviderAccount $providerAccount,
        array $payload,
        ?string $statusOverride,
    ): array {
        $request = $this->buildCustomerWalletPayload($owner, $payload);
        $response = $this->customerWalletResponse($request, $payload);
        $walletData = (array) data_get($response, 'data', []);
        $kycResponse = $this->shouldGenerateKycLink($payload, $statusOverride, $walletData)
            ? $this->generateKycResponse($walletData, $payload)
            : null;

        return [
            'wallet' => $this->persistWallet($owner, $providerAccount, $walletData, $request, $statusOverride, $kycResponse),
            'response' => $response,
            'kyc_response' => $kycResponse,
        ];
    }

    /**
     * @return array{wallet: Wallet, response: array<string, mixed>, kyc_response: array<string, mixed>|null}
     */
    protected function resolveWallet(
        mixed $owner,
        ProviderAccount $providerAccount,
        array $payload,
        ?string $statusOverride,
    ): array {
        $localWallet = $this->findLocalWallet($owner, $payload);

        if ($localWallet !== null && ! $this->usesLiveRequests($payload)) {
            return [
                'wallet' => $statusOverride === 'ready' ? $this->markWalletReady($localWallet) : $localWallet,
                'response' => [],
                'kyc_response' => null,
            ];
        }

        $identifier = $this->walletIdentifier($payload)
            ?? $localWallet?->provider_wallet_id;

        if ($identifier === null) {
            return $this->createWallet($owner, $providerAccount, $payload, $statusOverride);
        }

        $response = $this->walletDetailsResponse($identifier, $payload);

        return [
            'wallet' => $this->persistWallet(
                $owner,
                $providerAccount,
                (array) data_get($response, 'data', []),
                [],
                $statusOverride,
                null,
            ),
            'response' => $response,
            'kyc_response' => null,
        ];
    }

    /**
     * @return array{wallet: Wallet, response: array<string, mixed>, kyc_response: array<string, mixed>|null}
     */
    protected function ensureWallet(
        mixed $owner,
        ProviderAccount $providerAccount,
        array $payload,
        ?string $statusOverride,
    ): array {
        $localWallet = $this->findLocalWallet($owner, $payload);

        if ($localWallet !== null) {
            return [
                'wallet' => $statusOverride === 'ready' ? $this->markWalletReady($localWallet) : $localWallet,
                'response' => [],
                'kyc_response' => null,
            ];
        }

        return $this->resolveWallet($owner, $providerAccount, $payload, $statusOverride);
    }

    /**
     * @return array{bank_account: BankAccount, response: array<string, mixed>}
     */
    protected function linkBankAccount(
        mixed $owner,
        Wallet $wallet,
        array $payload,
        ?string $statusOverride,
    ): array {
        $request = $this->buildBankAccountPayload($owner, $wallet, $payload);
        $response = $this->bankAccountResponse($request, $payload);

        return [
            'bank_account' => $this->persistBankAccount(
                $owner,
                $wallet,
                $request,
                (array) data_get($response, 'data', []),
                $statusOverride,
            ),
            'response' => $response,
        ];
    }

    protected function resolveProviderAccount(): ProviderAccount
    {
        return ProviderAccount::query()->updateOrCreate(
            [
                'provider_code' => ProviderCode::PaynamicsConstellation->value,
                'merchant_id' => $this->stringValue(config('constellation.username')),
            ],
            [
                'name' => 'Paynamics Constellation',
                'base_url' => $this->stringValue(config('constellation.base_url')),
                'is_active' => true,
                'config' => array_filter([
                    'notification_url' => $this->stringValue(config('constellation.notification_url')),
                    'customer_profile_type' => $this->customerProfileType(),
                    'kyc_level' => $this->kycLevel(),
                ], static fn (mixed $value): bool => $value !== null),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $walletData
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>|null  $kycResponse
     */
    protected function persistWallet(
        mixed $owner,
        ProviderAccount $providerAccount,
        array $walletData,
        array $request,
        ?string $statusOverride,
        ?array $kycResponse,
    ): Wallet {
        $providerWalletId = (string) data_get($walletData, 'wallet_id', 'CNSTWLLTFAKE02');
        $verificationStatus = $statusOverride === 'ready'
            ? VerificationStatus::Approved->value
            : $this->normalizeVerificationStatus(data_get($walletData, 'verification_status'));
        $complianceLevel = $this->normalizeComplianceLevel(
            data_get($walletData, 'compliance_level'),
            $statusOverride === 'ready'
        );
        $attributes = [
            'provider_account_id' => $providerAccount->getKey(),
            'provider_code' => ProviderCode::PaynamicsConstellation->value,
            'provider_account_id_value' => $this->stringValue(data_get($walletData, 'account_id')),
            'account_no' => $this->stringValue(data_get($walletData, 'account_no')),
            'external_uid' => $this->stringValue(data_get($walletData, 'external_uid'))
                ?? $this->stringValue(data_get($request, 'external_uid'))
                ?? 'xchange-paynamics',
            'wallet_type' => WalletType::Customer->value,
            'status' => $this->normalizeWalletStatus(data_get($walletData, 'status')),
            'compliance_level' => $complianceLevel,
            'verification_status' => $verificationStatus,
            'balance_cached' => (string) data_get($walletData, 'balance', '0.00'),
            'currency' => $this->stringValue(data_get($walletData, 'currency')) ?? 'PHP',
            'notification_url' => $this->stringValue(data_get($walletData, 'notification_url'))
                ?? $this->stringValue(data_get($request, 'notification_url')),
            'capture_link' => $this->stringValue(data_get($kycResponse, 'data.capture_link'))
                ?? $this->stringValue(data_get($walletData, 'capture_link')),
            'meta' => array_filter([
                'fake' => $this->stringValue(data_get($request, '_mode')) === 'fake',
                'required_compliance' => $this->stringValue(data_get($walletData, 'required_compliance')),
                'response_code' => $this->stringValue(data_get($kycResponse, 'data.response_code')),
                'response_message' => $this->stringValue(data_get($kycResponse, 'data.response_message')),
                'profile_type' => $this->stringValue(data_get($request, 'profile_type')),
            ], static fn (mixed $value): bool => $value !== null),
        ];

        $existing = Wallet::query()->where('provider_wallet_id', $providerWalletId)->first();

        if ($existing !== null) {
            $existing->forceFill($attributes)->save();

            return $existing->fresh() ?? $existing;
        }

        if (! is_object($owner) || ! method_exists($owner, 'getKey')) {
            throw new RuntimeException('Paynamics wallet provisioning requires an Eloquent owner when creating a new provider wallet projection.');
        }

        return Wallet::unguarded(function () use ($owner, $providerWalletId, $attributes): Wallet {
            return Wallet::query()->create([
                'holder_type' => $owner::class,
                'holder_id' => $owner->getKey(),
                'name' => 'Paynamics Customer Wallet',
                'slug' => Str::limit(Str::slug('paynamics-'.$providerWalletId, '-'), 255, ''),
                'uuid' => (string) Str::uuid(),
                'description' => 'Provider wallet projection',
                'balance' => 0,
                'decimal_places' => 2,
                'provider_wallet_id' => $providerWalletId,
                ...$attributes,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $responseData
     */
    protected function persistBankAccount(
        mixed $owner,
        Wallet $wallet,
        array $request,
        array $responseData,
        ?string $statusOverride,
    ): BankAccount {
        $maskedAccountNumber = $this->maskAccountNumber(
            $this->stringValue(data_get($request, 'bank_account_no'))
                ?? $this->stringValue(data_get($request, 'account_number_masked'))
                ?? '',
        );

        return BankAccount::query()->updateOrCreate(
            [
                'wallet_id' => $wallet->getKey(),
                'provider_bank_account_id' => $this->stringValue(data_get($responseData, 'bank_account_id'))
                    ?? sprintf('BANKFAKE-%s', substr(md5($maskedAccountNumber), 0, 8)),
            ],
            [
                'bank_code' => $this->stringValue(data_get($request, 'bank_code')),
                'bank_name' => $this->stringValue(data_get($request, 'bank_name')),
                'account_name' => $this->ownerName($owner),
                'account_number_masked' => $maskedAccountNumber,
                'status' => $statusOverride === 'failed' ? 'failed' : 'active',
                'is_registered' => $statusOverride !== 'failed',
                'meta' => array_filter([
                    'fake' => $this->stringValue(data_get($request, '_mode')) === 'fake',
                    'bank_id' => $this->stringValue(data_get($request, 'bank_id')),
                    'account_id' => $this->stringValue(data_get($request, 'account_id')),
                ], static fn (mixed $value): bool => $value !== null),
            ],
        );
    }

    protected function markWalletReady(Wallet $wallet): Wallet
    {
        $wallet->forceFill([
            'verification_status' => VerificationStatus::Approved->value,
            'compliance_level' => $this->normalizeComplianceLevel($wallet->compliance_level, true),
            'status' => WalletStatus::Active->value,
        ])->save();

        return $wallet->fresh() ?? $wallet;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildCustomerWalletPayload(mixed $owner, array $payload): array
    {
        $fullName = $this->ownerName($owner);
        [$firstName, $middleName, $lastName] = $this->nameParts($fullName);
        $externalUid = $this->externalUid($owner, $payload);

        return [
            '_mode' => $this->usesLiveRequests($payload) ? 'live' : 'fake',
            'first_name' => $this->stringValue(data_get($payload, 'first_name')) ?? $firstName,
            'middle_name' => $this->stringValue(data_get($payload, 'middle_name')) ?? $middleName,
            'last_name' => $this->stringValue(data_get($payload, 'last_name')) ?? $lastName,
            'email' => $this->ownerEmail($owner, $externalUid, $payload),
            'mobile_no' => $this->normalizeMobile(
                $this->stringValue(data_get($payload, 'mobile_no'))
                    ?? $this->stringValue(data_get($payload, 'mobile'))
                    ?? $this->stringValue(data_get($payload, 'customer.mobile'))
                    ?? $this->stringValue(data_get($owner, 'mobile'))
                    ?? $this->stringValue(config('constellation.company.mobile_no'))
                    ?? '639170000000'
            ),
            'address' => $this->stringValue(data_get($payload, 'address'))
                ?? $this->stringValue(data_get($payload, 'customer.address'))
                ?? $this->stringValue(config('constellation.company.business_address'))
                ?? 'Pasig City',
            'zip' => $this->stringValue(data_get($payload, 'zip'))
                ?? $this->stringValue(config('constellation.company.business_zip'))
                ?? '1605',
            'city' => $this->stringValue(data_get($payload, 'city'))
                ?? $this->stringValue(config('constellation.company.business_city'))
                ?? 'Pasig City',
            'state' => $this->stringValue(data_get($payload, 'state'))
                ?? $this->stringValue(config('constellation.company.business_state'))
                ?? 'Metro Manila',
            'country' => $this->stringValue(data_get($payload, 'country'))
                ?? $this->stringValue(config('constellation.company.business_country'))
                ?? 'PH',
            'username' => $this->stringValue(data_get($payload, 'username')) ?? $externalUid,
            'password' => $this->stringValue(data_get($payload, 'password')) ?? $this->generatedPassword($externalUid),
            'birthdate' => $this->stringValue(data_get($payload, 'birthdate'))
                ?? $this->stringValue(config('constellation.company.birthdate')),
            'nationality' => $this->stringValue(data_get($payload, 'nationality'))
                ?? $this->stringValue(config('constellation.company.nationality')),
            'source_of_funds' => $this->stringValue(data_get($payload, 'source_of_funds'))
                ?? $this->stringValue(config('constellation.company.source_of_funds')),
            'profile_type' => $this->stringValue(data_get($payload, 'profile_type')) ?? $this->customerProfileType(),
            'external_uid' => $externalUid,
            'notification_url' => $this->stringValue(data_get($payload, 'notification_url'))
                ?? $this->stringValue(config('constellation.notification_url')),
            'success_url' => $this->stringValue(data_get($payload, 'success_url'))
                ?? $this->stringValue(config('constellation.company.success_url')),
            'failed_url' => $this->stringValue(data_get($payload, 'failed_url'))
                ?? $this->stringValue(config('constellation.company.failed_url')),
            'device_information' => data_get($payload, 'device_information', [
                'device_id' => 'xchange',
                'os_version' => 'web',
            ]),
            'network_information' => data_get($payload, 'network_information', [
                'ip_address' => '127.0.0.1',
                'network_type' => 'web',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function buildBankAccountPayload(mixed $owner, Wallet $wallet, array $payload): array
    {
        $fullName = $this->ownerName($owner);
        [$firstName, $middleName, $lastName] = $this->nameParts($fullName);
        $accountNumber = $this->stringValue(data_get($payload, 'account_number'))
            ?? $this->stringValue(data_get($payload, 'bank_account.account_number'))
            ?? $this->stringValue(data_get($payload, 'account_number_masked'))
            ?? $this->stringValue(data_get($payload, 'bank_account.account_number_masked'))
            ?? '';

        return [
            '_mode' => $this->usesLiveRequests($payload) ? 'live' : 'fake',
            'bank_account_no' => $accountNumber,
            'account_number_masked' => $this->maskAccountNumber($accountNumber),
            'acc_currency' => $this->stringValue(data_get($payload, 'currency')) ?? 'PHP',
            'bank_id' => $this->stringValue(data_get($payload, 'bank_id')) ?? $this->bankId($payload),
            'bank_code' => $this->stringValue(data_get($payload, 'bank_code'))
                ?? $this->stringValue(data_get($payload, 'bank_account.bank_code')),
            'bank_name' => $this->stringValue(data_get($payload, 'bank_name'))
                ?? $this->stringValue(data_get($payload, 'bank_account.bank_name')),
            'acct_type' => $this->stringValue(data_get($payload, 'account_type'))
                ?? $this->stringValue(data_get($payload, 'bank_account.account_type'))
                ?? 'Savings',
            'acc_holder_fname' => $firstName,
            'acc_holder_mname' => $middleName,
            'acc_holder_lname' => $lastName,
            'acc_holder_email' => $this->ownerEmail($owner, $wallet->external_uid, $payload),
            'acc_holder_phone' => $this->normalizeMobile(
                $this->stringValue(data_get($payload, 'mobile_no'))
                    ?? $this->stringValue(data_get($owner, 'mobile'))
                    ?? '639170000000'
            ),
            'acc_holder_address' => $this->stringValue(data_get($payload, 'address'))
                ?? $this->stringValue(config('constellation.company.business_address'))
                ?? 'Pasig City',
            'acc_holder_city' => $this->stringValue(data_get($payload, 'city'))
                ?? $this->stringValue(config('constellation.company.business_city'))
                ?? 'Pasig City',
            'acc_state' => $this->stringValue(data_get($payload, 'state'))
                ?? $this->stringValue(config('constellation.company.business_state'))
                ?? 'Metro Manila',
            'country' => $this->stringValue(data_get($payload, 'country'))
                ?? $this->stringValue(config('constellation.company.business_country'))
                ?? 'PH',
            'zip' => $this->stringValue(data_get($payload, 'zip'))
                ?? $this->stringValue(config('constellation.company.business_zip'))
                ?? '1605',
            'alias' => $this->stringValue(data_get($payload, 'bank_account.alias')) ?? 'Primary',
            'account_id' => $wallet->provider_account_id_value,
        ];
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function customerWalletResponse(array $request, array $payload): array
    {
        if ($this->usesLiveRequests($payload)) {
            return $this->customerProvisioner()->createCustomerWallet($request);
        }

        return [
            'success' => true,
            'data' => [
                'wallet_id' => 'CNSTWLLTFAKE02',
                'account_id' => 'CNSTCUSTFAKE02',
                'account_no' => '987654321098',
                'wallet_type' => 'Personal',
                'status' => 'Active',
                'balance' => '0.00',
                'currency' => 'PHP',
                'compliance_level' => data_get($payload, 'status') === 'ready'
                    ? ComplianceLevel::Level1->value
                    : ComplianceLevel::None->value,
                'required_compliance' => 'level 1',
                'verification_status' => data_get($payload, 'status') === 'ready'
                    ? VerificationStatus::Approved->value
                    : VerificationStatus::Pending->value,
                'external_uid' => data_get($request, 'external_uid'),
                'capture_link' => 'https://capture.kyc.idfy.com/fake',
                'notification_url' => data_get($request, 'notification_url'),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function walletDetailsResponse(string $identifier, array $payload): array
    {
        if ($this->usesLiveRequests($payload)) {
            return $this->customerProvisioner()->readWallet($identifier);
        }

        return [
            'success' => true,
            'data' => [
                'wallet_id' => str_starts_with($identifier, 'CNSTWLLT') ? $identifier : 'CNSTWLLTFAKE02',
                'account_id' => 'CNSTCUSTFAKE02',
                'account_no' => '987654321098',
                'wallet_type' => 'Personal',
                'status' => 'Active',
                'balance' => '0.00',
                'currency' => 'PHP',
                'compliance_level' => data_get($payload, 'status') === 'ready'
                    ? ComplianceLevel::Level1->value
                    : ComplianceLevel::None->value,
                'verification_status' => data_get($payload, 'status') === 'ready'
                    ? VerificationStatus::Approved->value
                    : VerificationStatus::Pending->value,
                'external_uid' => str_starts_with($identifier, 'xchange-') ? $identifier : null,
                'capture_link' => 'https://capture.kyc.idfy.com/fake',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function bankAccountResponse(array $request, array $payload): array
    {
        if ($this->usesLiveRequests($payload)) {
            return $this->customerProvisioner()->linkBankAccount($request);
        }

        return [
            'success' => true,
            'data' => [
                'bank_account_id' => sprintf('FAKEBA%s', strtoupper(substr(md5((string) data_get($request, 'bank_account_no')), 0, 6))),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $walletData
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function generateKycResponse(array $walletData, array $payload): array
    {
        $request = [
            'account_id' => data_get($walletData, 'account_id'),
            'level' => data_get($payload, 'kyc_level', $this->kycLevel()),
        ];

        if ($this->usesLiveRequests($payload)) {
            return $this->customerProvisioner()->createIdentityVerificationLink($request);
        }

        return [
            'success' => true,
            'data' => [
                'response_code' => 'GR169',
                'capture_link' => 'https://capture.kyc.idfy.com/fake',
                'response_message' => 'Wallet KYC Request Pending',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $walletData
     */
    protected function shouldGenerateKycLink(array $payload, ?string $statusOverride, array $walletData): bool
    {
        if ($statusOverride === 'ready') {
            return false;
        }

        if ((bool) data_get($payload, 'generate_kyc_link', config('x-change.provider_runtime.providers.paynamics.generate_kyc_link', true)) === false) {
            return false;
        }

        return $this->normalizeVerificationStatus(data_get($walletData, 'verification_status')) !== VerificationStatus::Approved->value
            && filled(data_get($walletData, 'account_id'));
    }

    protected function shouldLinkBankAccount(string $mode, array $payload): bool
    {
        return in_array($mode, [
            'bank_account_link',
            'hybrid',
        ], true) || filled(data_get($payload, 'account_number')) || filled(data_get($payload, 'bank_account.account_number'));
    }

    protected function determineStatus(
        ?string $statusOverride,
        Wallet $wallet,
        ?BankAccount $bankAccount,
        string $mode,
    ): string {
        if ($statusOverride !== null) {
            return $statusOverride;
        }

        $walletReady = $this->enumValue($wallet->verification_status) === VerificationStatus::Approved->value
            && $this->enumValue($wallet->status) === WalletStatus::Active->value;
        $bankRequired = in_array($mode, [
            'bank_account_link',
            'hybrid',
        ], true);

        if (! $walletReady) {
            return 'pending';
        }

        if ($bankRequired && ! $bankAccount?->is_registered) {
            return 'pending';
        }

        return 'ready';
    }

    protected function findLocalWallet(mixed $owner, array $payload): ?Wallet
    {
        $providerWalletId = $this->stringValue(data_get($payload, 'provider_wallet_id'))
            ?? $this->stringValue(data_get($payload, 'wallet_id'));
        $externalUid = $this->externalUid($owner, $payload);

        return Wallet::query()
            ->where('provider_code', ProviderCode::PaynamicsConstellation->value)
            ->when(
                $providerWalletId !== null,
                fn ($query) => $query->where('provider_wallet_id', $providerWalletId),
                fn ($query) => $query->where('external_uid', $externalUid),
            )
            ->latest('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function usesLiveRequests(array $payload): bool
    {
        if (! (bool) config('x-change.provider_runtime.providers.paynamics.live_requests_enabled', false)) {
            return false;
        }

        if ((bool) data_get($payload, 'lifecycle', false)) {
            return (bool) config('x-change.provider_runtime.lifecycle.allow_live_provider_scenarios', false);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function externalUid(mixed $owner, array $payload): string
    {
        $externalUid = $this->stringValue(data_get($payload, 'external_uid'));

        if ($externalUid !== null) {
            return $externalUid;
        }

        $ownerKey = is_object($owner) && method_exists($owner, 'getKey')
            ? (string) $owner->getKey()
            : md5($this->ownerName($owner));

        return sprintf('xchange-paynamics-%s', $ownerKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function walletIdentifier(array $payload): ?string
    {
        return $this->stringValue(data_get($payload, 'provider_wallet_id'))
            ?? $this->stringValue(data_get($payload, 'wallet_id'))
            ?? $this->stringValue(data_get($payload, 'external_uid'));
    }

    protected function ownerName(mixed $owner): string
    {
        return $this->stringValue(data_get($owner, 'name'))
            ?? 'XChange User';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ownerEmail(mixed $owner, string $externalUid, array $payload): string
    {
        return $this->stringValue(data_get($payload, 'email'))
            ?? $this->stringValue(data_get($payload, 'customer.email'))
            ?? $this->stringValue(data_get($owner, 'email'))
            ?? $this->stringValue(config('constellation.company.email'))
            ?? "{$externalUid}@example.test";
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function nameParts(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $firstName = $parts[0] ?? 'XChange';
        $lastName = count($parts) > 1 ? array_pop($parts) : $firstName;
        $middleName = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';

        return [$firstName, $middleName, $lastName];
    }

    protected function generatedPassword(string $externalUid): string
    {
        return substr(hash('sha256', $externalUid.'|xchange'), 0, 12).'Aa1!';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function bankId(array $payload): ?string
    {
        $bankCode = $this->stringValue(data_get($payload, 'bank_code'))
            ?? $this->stringValue(data_get($payload, 'bank_account.bank_code'));

        if ($bankCode === null) {
            return null;
        }

        return $this->stringValue(config("constellation.bank_map.{$bankCode}"));
    }

    protected function normalizeMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if (str_starts_with($digits, '63')) {
            return $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '63'.substr($digits, 1);
        }

        return $digits;
    }

    protected function maskAccountNumber(string $accountNumber): string
    {
        $digits = preg_replace('/\s+/', '', $accountNumber) ?? '';
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($digits, -4);
    }

    protected function normalizeWalletStatus(mixed $status): string
    {
        return match (Str::lower((string) $status)) {
            'locked' => WalletStatus::Locked->value,
            'suspended' => WalletStatus::Suspended->value,
            'closed' => WalletStatus::Closed->value,
            default => WalletStatus::Active->value,
        };
    }

    protected function normalizeVerificationStatus(mixed $status): string
    {
        $status = strtoupper(trim((string) $status));

        return match ($status) {
            VerificationStatus::Approved->value => VerificationStatus::Approved->value,
            VerificationStatus::ForReview->value => VerificationStatus::ForReview->value,
            VerificationStatus::Rejected->value => VerificationStatus::Rejected->value,
            VerificationStatus::Recapture->value => VerificationStatus::Recapture->value,
            default => VerificationStatus::Pending->value,
        };
    }

    protected function normalizeComplianceLevel(mixed $level, bool $promoteReady = false): string
    {
        $level = (string) ($level instanceof BackedEnum ? $level->value : $level);

        if ($promoteReady && ($level === '' || $level === ComplianceLevel::None->value)) {
            return ComplianceLevel::Level1->value;
        }

        return match ($level) {
            ComplianceLevel::Rejected->value => ComplianceLevel::Rejected->value,
            ComplianceLevel::Level1->value => ComplianceLevel::Level1->value,
            ComplianceLevel::Level2->value => ComplianceLevel::Level2->value,
            ComplianceLevel::Level3->value => ComplianceLevel::Level3->value,
            ComplianceLevel::Level4->value => ComplianceLevel::Level4->value,
            default => ComplianceLevel::None->value,
        };
    }

    protected function normalizeLinkStatus(mixed $status): ?string
    {
        $status = $this->stringValue($status);

        return in_array($status, ['pending', 'ready', 'failed'], true) ? $status : null;
    }

    protected function customerProfileType(): string
    {
        return (string) config(
            'x-change.provider_runtime.providers.paynamics.customer_profile_type',
            'DEFAULT_CONSUMER',
        );
    }

    protected function kycLevel(): string
    {
        return (string) config(
            'x-change.provider_runtime.providers.paynamics.kyc_level',
            '1',
        );
    }

    protected function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value) && ! $value instanceof BackedEnum) {
            return null;
        }

        $value = trim((string) $this->enumValue($value));

        return $value !== '' ? $value : null;
    }

    protected function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
