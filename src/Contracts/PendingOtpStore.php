<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Contracts;

interface PendingOtpStore
{
    /**
     * @param  array<string, mixed>  $otpRequestPayload
     */
    public function getSubmittedOtp(array $otpRequestPayload): ?string;

    /**
     * @param  array<string, mixed>  $otpRequestPayload
     * @param  array<string, mixed>  $otpRequestResult
     */
    public function putPendingOtp(array $otpRequestPayload, array $otpRequestResult): void;
}
