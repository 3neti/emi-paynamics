<?php

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use LBHurtado\EmiCore\Contracts\SignsProviderPayloads;

class ConstellationSigner implements SignsProviderPayloads
{
    /**
     * Generate SHA512 signature by concatenating field values + integration key.
     *
     * @param  array<int, string>  $fields  Ordered field values to concatenate
     */
    public function generateSignature(array $fields, string $integrationKey): string
    {
        $raw = implode('', $fields).$integrationKey;

        return hash('sha512', $raw);
    }
}
