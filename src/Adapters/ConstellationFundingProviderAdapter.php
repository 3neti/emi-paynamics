<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Adapters;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use LBHurtado\EmiCore\Contracts\FundingProviderAdapter;
use LBHurtado\EmiCore\Contracts\ProviderFundingDestinationResolver;
use LBHurtado\EmiCore\Data\Funding\FundingDestinationData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionRequestData;
use LBHurtado\EmiCore\Data\Funding\FundingInstructionsData;
use LBHurtado\EmiCore\Data\Funding\FundingVerificationData;
use LBHurtado\EmiCore\Data\Funding\ProviderEventHintData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingDestinationLinkData;
use LBHurtado\EmiCore\Data\Funding\ProviderFundingObservationData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookReceiptData;
use LBHurtado\EmiCore\Data\Funding\ProviderWebhookRequestData;
use LBHurtado\EmiCore\Data\Funding\WebhookAuthenticationData;
use LBHurtado\EmiCore\Exceptions\ProviderFundingDestinationUnavailable;
use LBHurtado\EmiCore\Exceptions\ProviderFundingNotObserved;
use LBHurtado\EmiCore\Exceptions\ProviderFundingVerificationIndeterminate;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashIn\CreateCashIn;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashIn\GetCashInByRequestId;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSignatureVerifier;
use Throwable;

class ConstellationFundingProviderAdapter implements FundingProviderAdapter, ProviderFundingDestinationResolver
{
    private const PROVIDER = 'paynamics_constellation';

    public function __construct(
        private readonly CreateCashIn $createCashIn,
        private readonly GetCashInByRequestId $getCashIn,
        private readonly ConstellationSignatureVerifier $signatures,
    ) {}

    public function providerCode(): string
    {
        return self::PROVIDER;
    }

    public function sharedFundingDestination(string $accountReference): FundingDestinationData
    {
        $wallet = config('constellation.funding.wallet_id');
        if (! is_string($wallet) || trim($wallet) === '') {
            throw new ProviderFundingDestinationUnavailable('Shared funding destination wallet is unavailable.');
        }
        $wallet = trim($wallet);

        return new FundingDestinationData(
            provider: self::PROVIDER, mode: 'shared', destinationType: 'wallet', accountReference: $accountReference,
            displayReference: '•••• '.mb_substr($wallet, -6), fingerprint: hash('sha256', self::PROVIDER.'|'.$wallet),
            verificationStatus: 'platform_configured', providerWalletId: $wallet,
        );
    }

    public function dedicatedFundingDestination(ProviderFundingDestinationLinkData $link, string $accountReference): FundingDestinationData
    {
        if ($link->verificationStatus !== 'ownership_verified') {
            throw new ProviderFundingDestinationUnavailable('Paynamics dedicated funding requires authoritative wallet ownership verification.');
        }
        $wallet = trim((string) $link->providerWalletId);
        if ($wallet === '') {
            throw new ProviderFundingDestinationUnavailable('The dedicated Paynamics destination has no wallet ID.');
        }

        return new FundingDestinationData(
            provider: self::PROVIDER, mode: 'dedicated', destinationType: 'wallet', accountReference: $accountReference,
            displayReference: $link->displayReference ?? '•••• '.mb_substr($wallet, -6),
            fingerprint: $link->fingerprint ?? hash('sha256', self::PROVIDER.'|'.$wallet),
            verificationStatus: 'ownership_verified', providerAccountId: $link->providerAccountId, providerWalletId: $wallet,
        );
    }

    public function createFundingInstructions(FundingInstructionRequestData $request): FundingInstructionsData
    {
        $this->assertInstructionRequest($request);

        $walletId = $this->fundingWalletId($request->destination);
        $requestId = 'XF'.strtoupper(substr(hash('sha256', $request->fundingReference), 0, 24));
        $response = $this->createCashIn->handle([
            'request_id' => $requestId,
            'wallet_id' => $walletId,
            'pmethod' => $this->requiredConfig('payment_method'),
            'pchannel' => $this->requiredConfig('payment_channel'),
            'amount' => $this->majorAmount($request->amountMinor),
            'response_url' => $this->requiredConfig('response_url'),
            'cancel_url' => $this->requiredConfig('cancel_url'),
            'meta_data' => [
                'funding_reference' => $request->fundingReference,
            ],
            'device_information' => [
                'device_id' => 'x-change-funding',
                'os_version' => PHP_OS,
            ],
            'network_information' => [
                'ip_address' => '127.0.0.1',
                'network_type' => 'server',
            ],
        ]);
        $data = $this->responseData($response, 'Cash-in instruction creation');
        $providerRequestId = $this->requiredString($data, ['request_id']);
        $actionUrl = $this->actionUrl($data['payment_action_info'] ?? null);

        if ($providerRequestId !== $requestId || $actionUrl === null) {
            throw new ProviderFundingVerificationIndeterminate(
                'Paynamics returned incomplete or mismatched cash-in instructions.',
            );
        }

        return new FundingInstructionsData(
            provider: self::PROVIDER,
            providerReference: $providerRequestId,
            amountMinor: $request->amountMinor,
            currency: $request->currency,
            expiresAt: $this->dateTime($data, ['expiry_limit', 'expires_at']) ?? $request->expiresAt,
            fundingAddress: $walletId,
            actionUrl: $actionUrl,
            displayData: [
                'payment_method' => $this->requiredConfig('payment_method'),
                'payment_channel' => $this->requiredConfig('payment_channel'),
                'provider_request_id' => $providerRequestId,
            ],
        );
    }

    public function authenticateWebhook(ProviderWebhookRequestData $request): WebhookAuthenticationData
    {
        if (strtolower($request->provider) !== self::PROVIDER) {
            return new WebhookAuthenticationData(false, 'paynamics-sha512-body', 'Provider mismatch.');
        }

        $payload = $this->decodeJson($request->rawBody);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : null;
        $merchantKey = config('constellation.merchant_key');

        if ($data === null || ! is_string($merchantKey) || trim($merchantKey) === '') {
            return new WebhookAuthenticationData(false, 'paynamics-sha512-body', 'Signed callback data is unavailable.');
        }

        $authenticated = $this->signatures->verifySignature($data, $merchantKey);

        return new WebhookAuthenticationData(
            authenticated: $authenticated,
            method: 'paynamics-sha512-body',
            reason: $authenticated ? null : 'Paynamics callback signature mismatch.',
        );
    }

    public function normalizeWebhook(ProviderWebhookReceiptData $receipt): ProviderEventHintData
    {
        $payload = $this->decodeJson($receipt->rawBody);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return new ProviderEventHintData(
            providerEventId: $this->nullableString($payload, ['postback_id']),
            eventType: $this->nullableString($payload, ['originating_flow']),
            requestId: $this->nullableString($data, ['request_id']),
        );
    }

    public function verifyFunding(FundingVerificationData $verification): ProviderFundingObservationData
    {
        if (strtolower($verification->provider) !== self::PROVIDER) {
            throw new InvalidArgumentException('Paynamics funding verification provider mismatch.');
        }

        $requestId = trim((string) $verification->providerRequestId);

        if ($requestId === '') {
            throw new ProviderFundingVerificationIndeterminate('Paynamics funding verification requires a request ID.');
        }

        try {
            $response = $this->getCashIn->handle($requestId);
        } catch (Throwable $exception) {
            throw new ProviderFundingVerificationIndeterminate(
                'Paynamics cash-in status could not be queried.',
                previous: $exception,
            );
        }

        if (($response['success'] ?? null) !== true) {
            $code = strtoupper((string) data_get($response, 'data.response_code', data_get($response, 'response_code', '')));
            $notObservedCodes = array_map('strtoupper', (array) config(
                'constellation.funding.not_observed_response_codes',
                [],
            ));

            if ($code !== '' && in_array($code, $notObservedCodes, true)) {
                throw new ProviderFundingNotObserved('Paynamics has not observed the cash-in request.');
            }

            throw new ProviderFundingVerificationIndeterminate('Paynamics returned an unsuccessful cash-in status response.');
        }

        $data = $this->responseData($response, 'Cash-in status');
        $observedRequestId = $this->requiredString($data, ['request_id']);

        if (! hash_equals($requestId, $observedRequestId)) {
            throw new ProviderFundingVerificationIndeterminate('Paynamics returned a mismatched cash-in request ID.');
        }

        $rawStatus = strtoupper($this->requiredString($data, ['status', 'transaction_status']));
        $providerStatus = $this->providerStatus($rawStatus);
        $grossAmountMinor = $this->minorAmount($this->requiredString($data, [
            'amount',
            'gross_amount',
            'transaction_amount',
        ]));
        $feeAmountMinor = $this->optionalMinorAmount($data, ['fee_amount', 'fee', 'service_fee']);
        $netAmountMinor = $this->optionalMinorAmount($data, ['net_amount']) ?? ($grossAmountMinor - $feeAmountMinor);
        $walletId = $this->requiredString($data, ['wallet_id', 'destination_wallet_id']);
        $destinationVerified = hash_equals($this->fundingWalletId($verification->destination), $walletId);
        $settledAt = $providerStatus === 'settled'
            ? $this->dateTime($data, ['settled_at', 'completed_at', 'transaction_date', 'timestamp'])
            : null;

        if ($grossAmountMinor <= 0 || $netAmountMinor <= 0 || $netAmountMinor > $grossAmountMinor) {
            throw new ProviderFundingVerificationIndeterminate('Paynamics returned invalid cash-in amounts.');
        }

        if ($providerStatus === 'settled' && $settledAt === null) {
            throw new ProviderFundingVerificationIndeterminate('Paynamics settled cash-in evidence has no authoritative timestamp.');
        }

        return new ProviderFundingObservationData(
            provider: self::PROVIDER,
            providerTransactionId: $this->requiredString($data, ['transaction_id', 'response_id', 'request_id']),
            grossAmountMinor: $grossAmountMinor,
            feeAmountMinor: $feeAmountMinor,
            netAmountMinor: $netAmountMinor,
            currency: strtoupper($this->nullableString($data, ['currency']) ?? $verification->currency),
            providerStatus: $providerStatus,
            verificationSource: 'paynamics-cashin-status-api',
            payloadHash: $this->payloadHash($response),
            providerOperationId: $this->nullableString($data, ['operation_id', 'postback_id']),
            requestId: $observedRequestId,
            fundingAddress: $walletId,
            providerAccountReference: $walletId,
            occurredAt: $this->dateTime($data, ['created_at', 'transaction_date', 'timestamp']),
            settledAt: $settledAt,
            webhookReceiptId: $verification->webhookReceiptId,
            metadata: [
                'destination_verified' => $destinationVerified,
                'raw_status' => $rawStatus,
                'response_code' => $this->nullableString($data, ['response_code']),
            ],
        );
    }

    private function assertInstructionRequest(FundingInstructionRequestData $request): void
    {
        if (strtolower($request->provider) !== self::PROVIDER
            || $request->currency !== 'PHP'
            || $request->amountMinor <= 0) {
            throw new InvalidArgumentException('Paynamics funding requires a positive PHP Funding Intent.');
        }
    }

    private function fundingWalletId(?FundingDestinationData $destination = null): string
    {
        if ($destination === null) {
            return $this->requiredConfig('wallet_id');
        }

        if (strtolower($destination->provider) !== self::PROVIDER) {
            throw new InvalidArgumentException('Paynamics funding destination provider mismatch.');
        }

        if ($destination->destinationType !== 'wallet') {
            throw new InvalidArgumentException('The Paynamics funding destination must be a wallet.');
        }

        if ($destination->mode === 'dedicated' && $destination->verificationStatus !== 'ownership_verified') {
            throw new ProviderFundingVerificationIndeterminate(
                'Paynamics dedicated funding requires authoritative wallet ownership verification.',
            );
        }

        $walletId = trim((string) $destination->providerWalletId);

        if ($walletId === '') {
            throw new ProviderFundingVerificationIndeterminate(
                'Paynamics funding destination has no provider wallet ID.',
            );
        }

        return $walletId;
    }

    private function requiredConfig(string $key): string
    {
        $value = config("constellation.funding.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw new ProviderFundingVerificationIndeterminate("Paynamics funding {$key} is not configured.");
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function responseData(array $response, string $operation): array
    {
        if (($response['success'] ?? null) !== true || ! is_array($response['data'] ?? null)) {
            throw new ProviderFundingVerificationIndeterminate("Paynamics {$operation} response was not successful.");
        }

        return $response['data'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $rawBody): array
    {
        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function requiredString(array $data, array $keys): string
    {
        $value = $this->nullableString($data, $keys);

        if ($value === null) {
            throw new ProviderFundingVerificationIndeterminate(
                'Paynamics evidence is missing '.implode(' or ', $keys).'.',
            );
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function nullableString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if ((is_string($value) || is_numeric($value)) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function providerStatus(string $status): string
    {
        if (in_array($status, (array) config('constellation.funding.settled_statuses', []), true)) {
            return 'settled';
        }

        if (in_array($status, (array) config('constellation.funding.pending_statuses', []), true)) {
            return 'pending';
        }

        if (in_array($status, (array) config('constellation.funding.reversed_statuses', []), true)) {
            return 'reversed';
        }

        throw new ProviderFundingVerificationIndeterminate("Unrecognized Paynamics cash-in status [{$status}].");
    }

    private function majorAmount(int $amountMinor): string
    {
        return sprintf('%d.%02d', intdiv($amountMinor, 100), $amountMinor % 100);
    }

    private function minorAmount(string $amount): int
    {
        $normalized = str_replace(',', '', trim($amount));

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $matches) !== 1) {
            throw new ProviderFundingVerificationIndeterminate('Paynamics returned an invalid monetary amount.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function optionalMinorAmount(array $data, array $keys): ?int
    {
        $amount = $this->nullableString($data, $keys);

        return $amount === null ? null : $this->minorAmount($amount);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     */
    private function dateTime(array $data, array $keys): ?DateTimeImmutable
    {
        $value = $this->nullableString($data, $keys);

        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            throw new ProviderFundingVerificationIndeterminate('Paynamics returned an invalid evidence timestamp.');
        }
    }

    private function actionUrl(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['url'] ?? null;
        }

        return is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false
            ? $value
            : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadHash(array $payload): string
    {
        try {
            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        } catch (JsonException $exception) {
            throw new ProviderFundingVerificationIndeterminate(
                'Paynamics evidence could not be hashed.',
                previous: $exception,
            );
        }
    }
}
