<?php

declare(strict_types=1);

use LBHurtado\EmiCore\Contracts\PayoutProviderIdentity;

it('advertises the legacy payout identity for its canonical settlement provider', function (): void {
    $identities = collect(app()->tagged('emi.payout-provider-identities'))
        ->filter(fn ($identity) => $identity instanceof PayoutProviderIdentity)->values();
    expect($identities)->toHaveCount(1)
        ->and($identities[0]->providerCode())->toBe('paynamics_constellation')
        ->and($identities[0]->acceptsPayoutProvider('paynamics'))->toBeTrue()
        ->and($identities[0]->acceptsPayoutProvider('netbank'))->toBeFalse();
});
