<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiCore\Contracts\PendingPayoutOtpInterpreter;

final class ConstellationPendingPayoutOtpInterpreter implements PendingPayoutOtpInterpreter
{
    public function providerCode(): string
    {
        return 'paynamics_constellation';
    }

    public function reference(array $request): ?string
    {
        $reference = data_get($request, 'request_id');

        return is_string($reference) && trim($reference) !== '' ? trim($reference) : null;
    }

    public function pendingMetadata(array $request, array $result): array
    {
        return [
            'provider' => 'paynamics',
            'authorization_type' => 'otp',
            'reference_id' => $this->reference($request),
            'amount' => data_get($request, 'amount'),
            'bank_account_no' => data_get($request, 'bank_account_no'),
            'bank_id' => data_get($request, 'bank_id'),
            'reason' => data_get($request, 'reason'),
            'target' => is_string(data_get($result, 'data')) ? data_get($result, 'data') : null,
            'otp_required' => true,
            'polling_required' => false,
            'manual_review' => false,
            'message' => 'Paynamics payout OTP is pending.',
            'payload' => $request,
            'result' => $result,
        ];
    }
}
