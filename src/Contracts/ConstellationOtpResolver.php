<?php

namespace LBHurtado\EmiPaynamicsConstellation\Contracts;

interface ConstellationOtpResolver
{
    /**
     * Request and resolve an OTP for a cash-out transaction.
     *
     * The implementation is responsible for:
     * 1. Triggering the OTP request to the Paynamics API
     * 2. Obtaining the OTP code (via CLI prompt, callback, config, etc.)
     *
     * @param  array<string, mixed>  $otpRequestPayload  Must contain account_id, bank_account_no, bank_id, request_id, reason, amount
     * @return string The OTP code
     */
    public function resolve(array $otpRequestPayload): string;
}
