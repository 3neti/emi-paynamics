<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutOtp;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;
use LBHurtado\EmiPaynamicsConstellation\Exceptions\PendingConstellationOtpException;
use RuntimeException;

final class DeferredOtpResolver implements ConstellationOtpResolver
{
    public function __construct(
        private readonly CreateCashOutOtp $createCashOutOtp,
        private readonly PendingOtpStore $store,
    ) {}

    /**
     * @param  array<string, mixed>  $otpRequestPayload
     */
    public function resolve(array $otpRequestPayload): string
    {
        $submittedOtp = $this->store->getSubmittedOtp($otpRequestPayload);

        if (is_string($submittedOtp) && trim($submittedOtp) !== '') {
            return trim($submittedOtp);
        }

        $result = $this->createCashOutOtp->handle($otpRequestPayload);

        if (! ($result['success'] ?? false)) {
            $message = $result['data']['response_message']
                ?? $result['data']['response_advise']
                ?? $result['data']
                ?? 'Unknown OTP error';

            throw new RuntimeException("OTP request failed: {$message}");
        }

        $this->store->putPendingOtp($otpRequestPayload, $result);

        throw PendingConstellationOtpException::fromPayload(
            otpRequestPayload: $otpRequestPayload,
            otpRequestResult: $result,
        );
    }
}
