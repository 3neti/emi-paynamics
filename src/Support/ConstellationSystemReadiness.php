<?php

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use Illuminate\Support\Facades\Cache;
use LBHurtado\EmiCore\Contracts\SystemReadiness;
use LBHurtado\EmiCore\Data\SystemReadinessResult;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\GetWalletDetails;

class ConstellationSystemReadiness implements SystemReadiness
{
    private const CACHE_KEY = 'constellation:system_readiness';

    public function check(): SystemReadinessResult
    {
        // Layer 1: Config present?
        $settlementId = config('constellation.settlement_wallet_id');
        $revenueId = config('constellation.revenue_wallet_id');

        $issues = [];

        if (empty($settlementId)) {
            $issues[] = 'CONSTELLATION_SETTLEMENT_WALLET_ID is not configured';
        }

        if (empty($revenueId)) {
            $issues[] = 'CONSTELLATION_REVENUE_WALLET_ID is not configured';
        }

        if (! empty($issues)) {
            return SystemReadinessResult::fail(...$issues);
        }

        // Layer 2: Cached result?
        $ttl = config('emi-core.readiness_cache_ttl', 300);
        $cached = Cache::get(self::CACHE_KEY);

        if ($cached instanceof SystemReadinessResult) {
            return $cached;
        }

        // Layer 3: API probe
        try {
            $settlement = GetWalletDetails::run($settlementId);

            if (! ($settlement['success'] ?? false)) {
                $issues[] = "Settlement wallet {$settlementId}: ".($settlement['data']['response_message'] ?? 'not found');
            } elseif (($settlement['data']['status'] ?? '') !== 'Active') {
                $issues[] = "Settlement wallet {$settlementId}: status is ".($settlement['data']['status'] ?? 'unknown');
            }
        } catch (\Throwable $e) {
            $issues[] = "Settlement wallet probe failed: {$e->getMessage()}";
        }

        try {
            $revenue = GetWalletDetails::run($revenueId);

            if (! ($revenue['success'] ?? false)) {
                $issues[] = "Revenue wallet {$revenueId}: ".($revenue['data']['response_message'] ?? 'not found');
            } elseif (($revenue['data']['status'] ?? '') !== 'Active') {
                $issues[] = "Revenue wallet {$revenueId}: status is ".($revenue['data']['status'] ?? 'unknown');
            }
        } catch (\Throwable $e) {
            $issues[] = "Revenue wallet probe failed: {$e->getMessage()}";
        }

        $result = empty($issues)
            ? SystemReadinessResult::ok()
            : SystemReadinessResult::fail(...$issues);

        Cache::put(self::CACHE_KEY, $result, $ttl);

        return $result;
    }
}
