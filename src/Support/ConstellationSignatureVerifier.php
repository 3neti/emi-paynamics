<?php

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiCore\Contracts\VerifiesProviderPostbacks;

class ConstellationSignatureVerifier implements VerifiesProviderPostbacks
{
    /**
     * Verify a postback signature.
     * Formula: SHA512(code + message + advise + timestamp + integration_key)
     *
     * @param  array<string, mixed>  $payload  Must contain 'code', 'message', 'advise', 'timestamp', 'signature'
     */
    public function verifySignature(array $payload, string $integrationKey): bool
    {
        $raw = ($payload['code'] ?? '')
            .($payload['message'] ?? '')
            .($payload['advise'] ?? '')
            .($payload['timestamp'] ?? '')
            .$integrationKey;

        $expected = hash('sha512', $raw);

        return hash_equals($expected, $payload['signature'] ?? '');
    }
}
