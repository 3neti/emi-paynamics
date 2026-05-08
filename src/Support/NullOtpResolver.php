<?php

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;

class NullOtpResolver implements ConstellationOtpResolver
{
    public function resolve(array $otpRequestPayload): string
    {
        return '';
    }
}
