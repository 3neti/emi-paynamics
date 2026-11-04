<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use Closure;
use LBHurtado\EmiCore\Contracts\ProviderPayoutApprovalReplayPolicy;
use Throwable;

final class ConstellationPayoutApprovalReplayPolicy implements ProviderPayoutApprovalReplayPolicy
{
    public function providerCode(): string
    {
        return 'paynamics_constellation';
    }

    public function supportsProvider(string $provider): bool
    {
        return $provider === 'paynamics';
    }

    public function shouldResume(array $payload, Closure $metadata, Throwable $exception): bool
    {
        if (data_get($payload, 'approval.provider') !== 'paynamics' || data_get($payload, 'otp.verified') !== true) {
            return false;
        }

        $message = mb_strtolower($exception->getMessage());
        $class = mb_strtolower($exception::class);
        if (str_contains($message, 'already been redeemed') || str_contains($class, 'voucherredeemed')) {
            return true;
        }
        if (! str_contains($message, 'failed to redeem voucher')) {
            return false;
        }
        $metadata = $metadata();
        $reference = data_get($payload, 'approval.reference_id') ?? data_get($payload, 'reference_id');
        $metadataReference = data_get($metadata, 'disbursement.transaction_id')
            ?? data_get($metadata, 'disbursement.reference_id')
            ?? data_get($metadata, 'disbursement.provider_reference')
            ?? data_get($metadata, 'disbursement.provider_tx')
            ?? data_get($metadata, 'disbursement.request_id');
        if (is_string($reference) && trim($reference) !== '' && is_string($metadataReference)
            && trim($metadataReference) !== '' && trim($reference) !== trim($metadataReference)) {
            return false;
        }
        $disbursement = mb_strtolower(json_encode(data_get($metadata, 'disbursement', []), JSON_THROW_ON_ERROR));

        return str_contains($disbursement, 'otp') && str_contains($disbursement, 'pending');
    }
}
