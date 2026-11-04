<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiCore\Contracts\PendingPayoutOtpStore;
use LBHurtado\EmiPaynamicsConstellation\Contracts\PendingOtpStore;

final readonly class LegacyPendingOtpStoreAdapter implements PendingOtpStore
{
    public function __construct(private PendingPayoutOtpStore $store) {}

    public function getSubmittedOtp(array $otpRequestPayload): ?string
    {
        return $this->store->getSubmittedOtp($otpRequestPayload);
    }

    public function putPendingOtp(array $otpRequestPayload, array $otpRequestResult): void
    {
        $this->store->putPendingOtp($otpRequestPayload, $otpRequestResult);
    }
}
