<?php

use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider;

it('can resolve the constellation payout provider from the container', function () {
    expect(app(ConstellationPayoutProvider::class))
        ->toBeInstanceOf(ConstellationPayoutProvider::class);
});