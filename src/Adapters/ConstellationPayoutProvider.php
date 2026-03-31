<?php

namespace LBHurtado\EmiPaynamicsConstellation\Adapters;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\SettlementRail;

class ConstellationPayoutProvider implements PayoutProvider
{
    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        // TODO: Implement disburse() method.
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        // TODO: Implement checkStatus() method.
    }

    public function getRailFee(SettlementRail $rail): int
    {
        // TODO: Implement getRailFee() method.
    }
}