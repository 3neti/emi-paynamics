<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Exceptions;

use RuntimeException;

final class PendingConstellationOtpException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $otpRequestPayload
     * @param  array<string, mixed>  $otpRequestResult
     */
    public function __construct(
        private readonly array $otpRequestPayload,
        private readonly array $otpRequestResult = [],
        string $message = 'Paynamics payout OTP is pending.',
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, mixed>  $otpRequestPayload
     * @param  array<string, mixed>  $otpRequestResult
     */
    public static function fromPayload(
        array $otpRequestPayload,
        array $otpRequestResult = [],
    ): self {
        return new self(
            otpRequestPayload: $otpRequestPayload,
            otpRequestResult: $otpRequestResult,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function otpRequestPayload(): array
    {
        return $this->otpRequestPayload;
    }

    /**
     * @return array<string, mixed>
     */
    public function otpRequestResult(): array
    {
        return $this->otpRequestResult;
    }

    public function provider(): string
    {
        return 'paynamics';
    }

    public function requestId(): ?string
    {
        return $this->stringValue('request_id');
    }

    public function amount(): ?string
    {
        return $this->stringValue('amount');
    }

    public function bankAccountNo(): ?string
    {
        return $this->stringValue('bank_account_no');
    }

    public function bankId(): ?string
    {
        return $this->stringValue('bank_id');
    }

    public function reason(): ?string
    {
        return $this->stringValue('reason');
    }

    public function target(): ?string
    {
        $data = $this->otpRequestResult['data'] ?? null;

        if (is_string($data) && $data !== '') {
            return $data;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApprovalMetadata(): array
    {
        return [
            'provider' => $this->provider(),
            'authorization_type' => 'otp',
            'reference_id' => $this->requestId(),
            'amount' => $this->amount(),
            'bank_account_no' => $this->bankAccountNo(),
            'bank_id' => $this->bankId(),
            'reason' => $this->reason(),
            'target' => $this->target(),
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => $this->getMessage(),
        ];
    }

    private function stringValue(string $key): ?string
    {
        $value = $this->otpRequestPayload[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
