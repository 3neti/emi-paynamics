<?php

declare(strict_types=1);

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use Closure;
use LBHurtado\EmiCore\Contracts\DeferredPayoutApprovalScope;
use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Adapters\ConstellationPayoutProvider;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;

final class ConstellationDeferredApprovalScope implements DeferredPayoutApprovalScope
{
    public function providerCode(): string
    {
        return 'paynamics_constellation';
    }

    public function isActiveProvider(): bool
    {
        return app()->bound(PayoutProvider::class) && app(PayoutProvider::class) instanceof ConstellationPayoutProvider;
    }

    public function requiresInteractiveApproval(): bool
    {
        return $this->isActiveProvider()
            && app()->bound(ConstellationOtpResolver::class)
            && app(ConstellationOtpResolver::class) instanceof InteractiveOtpResolver;
    }

    public function run(Closure $callback): mixed
    {
        $app = app();
        $previousResolver = $app->bound(ConstellationOtpResolver::class) ? $app->make(ConstellationOtpResolver::class) : null;
        $previousProvider = $app->bound(PayoutProvider::class) ? $app->make(PayoutProvider::class) : null;
        $app->forgetInstance(ConstellationOtpResolver::class);
        $app->instance(ConstellationOtpResolver::class, $app->make(DeferredOtpResolver::class));

        if ($previousProvider instanceof ConstellationPayoutProvider) {
            $app->forgetInstance(PayoutProvider::class);
            $app->instance(PayoutProvider::class, $app->make($previousProvider::class));
        }

        try {
            return $callback();
        } finally {
            $app->forgetInstance(PayoutProvider::class);
            $app->forgetInstance(ConstellationOtpResolver::class);
            if ($previousResolver) {
                $app->instance(ConstellationOtpResolver::class, $previousResolver);
            }
            if ($previousProvider) {
                $app->instance(PayoutProvider::class, $previousProvider);
            }
        }
    }
}
