<?php

namespace LBHurtado\EmiPaynamicsConstellation\Adapters;

use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\{CreateCashOutNonRegistered, GetCashOutByRequestId};
use LBHurtado\EmiPaynamicsConstellation\Actions\Transactions\GetTransactionByRequestId;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiCore\Data\{PayoutRequestData, PayoutResultData};
use LBHurtado\EmiCore\Enums\{PayoutStatus, SettlementRail};
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\{Arr, Str};
use RuntimeException;
use stdClass;
use Throwable;

class ConstellationPayoutProvider implements PayoutProvider
{
    public function __construct(
        protected CreateCashOutNonRegistered $createCashOutNonRegistered,
        protected GetCashOutByRequestId $getCashOutByRequestId,
        protected GetTransactionByRequestId $getTransactionByRequestId,
        protected GetWalletDetails $getWalletDetails,
        protected ConstellationOtpResolver $otpResolver,
    ) {}

    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        $walletId = $this->getSettlementWalletId();
        $accountId = $this->resolveSettlementAccountId($walletId);

        $bankId = $this->resolveBankId($request->bank_code);
        $accountNo = preg_replace('/\D+/', '', $request->account_number);
        $amount = $this->normalizeAmount($request->amount);
        $reason = $this->resolveReason($request);

        // Step 1: Request and resolve OTP
        Log::channel(config('constellation.log_channel', 'constellation'))->info(
            '[ConstellationPayoutProvider] requesting OTP',
            ['request_id' => $request->reference, 'time' => now()->toIso8601String()]
        );

        $otp = $this->otpResolver->resolve([
            'account_id' => $accountId,
            'bank_account_no' => $accountNo,
            'bank_id' => $bankId,
            'request_id' => $request->reference,
            'reason' => $reason,
            'amount' => $amount,
        ]);

        Log::channel(config('constellation.log_channel', 'constellation'))->info(
            '[ConstellationPayoutProvider] OTP received, submitting cash-out',
            ['request_id' => $request->reference, 'time' => now()->toIso8601String()]
        );

        // Step 2: Submit cash-out with OTP
        $payload = [
            'account_id' => $accountId,
            'amount' => $amount,
            'request_id' => $request->reference,
            'account_no' => $accountNo,
            'bank_id' => $bankId,
            'ben_fname' => $this->resolveBeneficiaryFirstName($request),
            'ben_lname' => $this->resolveBeneficiaryLastName($request),
            'ben_address' => $this->resolveBeneficiaryAddress($request),
            'reason' => $reason,
            'otp' => $otp,
            'wallet_id' => $walletId,
            'meta_data' => new stdClass,
            'device_information' => [
                'device_id' => (string) data_get($request->metadata, 'device_information.device_id', 'x-change'),
                'os_version' => (string) data_get($request->metadata, 'device_information.os_version', PHP_OS),
            ],
            'network_information' => [
                'ip_address' => (string) data_get($request->metadata, 'network_information.ip_address', '127.0.0.1'),
                'network_type' => (string) data_get($request->metadata, 'network_information.network_type', 'server'),
            ],
        ];

        try {
            $response = $this->createCashOutNonRegistered->handle($payload);

            Log::channel(config('constellation.log_channel', 'constellation'))->info(
                '[ConstellationPayoutProvider] disburse response',
                ['request_id' => $request->reference, 'response' => $response]
            );

            $status = $this->mapDisburseResponseToStatus($response);

            return new PayoutResultData(
                transaction_id: (string) ($response['request_id'] ?? $request->reference),
                uuid: (string) Str::uuid(),
                status: $status,
                provider: 'paynamics',
                metadata: [
                    'request' => $payload,
                    'response' => $response,
                ],
            );
        } catch (Throwable $e) {
            return new PayoutResultData(
                transaction_id: $request->reference,
                uuid: (string) Str::uuid(),
                status: PayoutStatus::FAILED,
                provider: 'paynamics',
                metadata: [
                    'request' => $payload,
                    'error' => $e->getMessage(),
                ],
            );
        }
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        try {
            $cashOut = $this->getCashOutByRequestId->handle($transactionId);

            if ($this->isErrorResponse($cashOut)) {
                return new PayoutResultData(
                    transaction_id: $transactionId,
                    uuid: (string) Str::uuid(),
                    status: PayoutStatus::FAILED,
                    provider: 'paynamics',
                    metadata: [
                        'source' => 'withdraw.get_by_request_id',
                        'response' => $cashOut,
                        'error' => (string) ($cashOut['message'] ?? 'Cash-out status lookup failed.'),
                    ],
                );
            }

            $cashOutStatus = data_get($cashOut, 'data.status');

            if (filled($cashOutStatus)) {
                return new PayoutResultData(
                    transaction_id: $transactionId,
                    uuid: (string) Str::uuid(),
                    status: $this->mapStatus((string) $cashOutStatus),
                    provider: 'paynamics',
                    metadata: [
                        'source' => 'withdraw.get_by_request_id',
                        'response' => $cashOut,
                    ],
                );
            }

            $transaction = $this->getTransactionByRequestId->handle($transactionId);

            if ($this->isErrorResponse($transaction)) {
                return new PayoutResultData(
                    transaction_id: $transactionId,
                    uuid: (string) Str::uuid(),
                    status: PayoutStatus::FAILED,
                    provider: 'paynamics',
                    metadata: [
                        'source' => 'elastic_trx.get_by_request_id',
                        'response' => $transaction,
                        'error' => (string) ($transaction['message'] ?? 'Transaction status lookup failed.'),
                    ],
                );
            }

            $transactionStatus = data_get($transaction, 'data.0.status')
                ?? data_get($transaction, 'data.status')
                ?? data_get($transaction, 'status');

            if (blank($transactionStatus)) {
                return new PayoutResultData(
                    transaction_id: $transactionId,
                    uuid: (string) Str::uuid(),
                    status: PayoutStatus::FAILED,
                    provider: 'paynamics',
                    metadata: [
                        'source' => 'elastic_trx.get_by_request_id',
                        'response' => $transaction,
                        'error' => 'No recognizable status returned by provider.',
                    ],
                );
            }

            return new PayoutResultData(
                transaction_id: $transactionId,
                uuid: (string) Str::uuid(),
                status: $this->mapStatus((string) $transactionStatus),
                provider: 'paynamics',
                metadata: [
                    'source' => 'elastic_trx.get_by_request_id',
                    'response' => $transaction,
                ],
            );
        } catch (Throwable $e) {
            return new PayoutResultData(
                transaction_id: $transactionId,
                uuid: (string) Str::uuid(),
                status: PayoutStatus::FAILED,
                provider: 'paynamics',
                metadata: [
                    'error' => $e->getMessage(),
                ],
            );
        }
    }

    public function getRailFee(SettlementRail $rail): int
    {
        return (int) config("constellation.rail_fees.{$rail->value}", 0);
    }

    protected function getSettlementWalletId(): string
    {
        $walletId = (string) config('constellation.settlement_wallet_id');

        if (blank($walletId)) {
            throw new RuntimeException('Constellation settlement wallet ID is not configured.');
        }

        return $walletId;
    }

    protected function resolveSettlementAccountId(string $walletId): string
    {
        $details = $this->getWalletDetails->handle($walletId);

        $accountId = Arr::get($details, 'data.account_id');

        if (blank($accountId)) {
            throw new RuntimeException("Unable to resolve Constellation account_id for wallet [{$walletId}].");
        }

        return (string) $accountId;
    }

    protected function resolveBankId(string $bankCode): string
    {
        return (string) config("constellation.bank_map.{$bankCode}", $bankCode);
    }

    protected function resolveBeneficiaryFirstName(PayoutRequestData $request): string
    {
        return (string) (
            Arr::get($request->metadata, 'beneficiary.first_name')
            ?? Arr::get($request->metadata, 'beneficiary.firstname')
            ?? config('constellation.company.account_first_name', 'XChange')
        );
    }

    protected function resolveBeneficiaryLastName(PayoutRequestData $request): string
    {
        return (string) (
            Arr::get($request->metadata, 'beneficiary.last_name')
            ?? Arr::get($request->metadata, 'beneficiary.lastname')
            ?? config('constellation.company.account_last_name', 'Redeemer')
        );
    }

    protected function resolveBeneficiaryAddress(PayoutRequestData $request): string
    {
        return (string) (
            Arr::get($request->metadata, 'beneficiary.address')
            ?? config('constellation.company.business_address', 'Philippines')
        );
    }

    protected function resolveReason(PayoutRequestData $request): string
    {
        return (string) (
            Arr::get($request->metadata, 'reason')
            ?? "Voucher payout {$request->reference}"
        );
    }

    protected function normalizeAmount(int|float $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    protected function mapDisburseResponseToStatus(array $response): PayoutStatus
    {
        $success = $response['success'] ?? null;

        $code = strtoupper((string) ($response['response_code'] ?? ''));
        $message = strtoupper((string) ($response['response_message'] ?? $response['message'] ?? ''));
        $dataStatus = strtoupper((string) data_get($response, 'data.status', ''));

        if ($success === false) {
            return PayoutStatus::FAILED;
        }

        if (in_array($dataStatus, ['SETTLED', 'SUCCESS', 'COMPLETED'], true)) {
            return PayoutStatus::COMPLETED;
        }

        if (in_array($dataStatus, ['FAILED', 'ERROR', 'REJECTED', 'CANCELLED', 'CANCELED'], true)) {
            return PayoutStatus::FAILED;
        }

        if (in_array($dataStatus, ['PENDING', 'PROCESSING', 'INPROCESS', 'FORSETTLEMENT'], true)) {
            return PayoutStatus::PENDING;
        }

        if ($code === 'GR162') {
            return PayoutStatus::PENDING;
        }

        if (
            str_contains($message, 'FAILED') ||
            str_contains($message, 'ERROR') ||
            str_contains($message, 'REJECT') ||
            str_contains($message, 'INVALID') ||
            str_contains($message, 'DENIED')
        ) {
            return PayoutStatus::FAILED;
        }

        if (
            str_contains($message, 'SETTLED') ||
            str_contains($message, 'SUCCESS') ||
            str_contains($message, 'COMPLETED')
        ) {
            return PayoutStatus::COMPLETED;
        }

        if (
            str_contains($message, 'PENDING') ||
            str_contains($message, 'PROCESSING')
        ) {
            return PayoutStatus::PENDING;
        }

        return PayoutStatus::FAILED;
    }

    protected function mapStatus(string $status): PayoutStatus
    {
        $normalized = strtoupper(str_replace([' ', '-', '_'], '', $status));

        return match ($normalized) {
            'PENDING' => PayoutStatus::PENDING,
            'PROCESSING', 'INPROCESS', 'FORSETTLEMENT' => PayoutStatus::PROCESSING,
            'SETTLED', 'SUCCESS', 'COMPLETED' => PayoutStatus::COMPLETED,
            'FAILED', 'ERROR', 'REJECTED' => PayoutStatus::FAILED,
            'CANCELLED', 'CANCELED' => PayoutStatus::CANCELLED,
            'REFUNDED' => PayoutStatus::REFUNDED,
            default => PayoutStatus::fromGeneric($status),
        };
    }

    protected function isErrorResponse(array $response): bool
    {
        $success = $response['success'] ?? null;
        $message = strtoupper((string) ($response['message'] ?? $response['response_message'] ?? ''));
        $code = strtoupper((string) ($response['response_code'] ?? ''));

        if ($success === false) {
            return true;
        }

        if (
            str_contains($message, 'ERROR') ||
            str_contains($message, 'FAILED') ||
            str_contains($message, 'INVALID') ||
            str_contains($message, 'DENIED') ||
            str_contains($message, 'UNAUTHORIZED') ||
            str_contains($message, 'EXCEPTION')
        ) {
            return true;
        }

        if (in_array($code, ['500', '400', '401', '403', '404', '422'], true)) {
            return true;
        }

        return false;
    }
}