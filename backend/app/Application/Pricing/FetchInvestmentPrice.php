<?php

namespace App\Application\Pricing;

use App\Domain\Pricing\PriceResult;
use App\Infrastructure\Pricing\PriceProviderFactory;
use App\Models\ApiSyncLog;
use App\Models\AssetPrice;
use App\Models\Investment;
use App\Models\PriceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Use case: fetch and persist the current price for one investment.
 * Records an asset_prices row + an api_sync_logs row.
 */
final class FetchInvestmentPrice
{
    public function __construct(
        private readonly PriceProviderFactory $providerFactory,
    ) {}

    public function execute(Investment $investment): PriceResult
    {
        $investment->loadMissing('assetType');
        $targetCurrency = $investment->currency;

        $chain = $this->providerFactory->forInvestment($investment);
        $runId = (string) Str::uuid();
        $start = microtime(true);

        try {
            $result = $chain->fetch($investment, $targetCurrency);
        } catch (\Throwable $e) {
            $result = PriceResult::error($chain->code(), $e->getMessage());
            Log::error('Pricing unexpected error', ['investment' => $investment->id, 'error' => $e->getMessage()]);
        }

        $durationMs = (int) ((microtime(true) - $start) * 1000);

        DB::transaction(function () use ($investment, $result, $runId, $durationMs) {
            // Persist the price only if not an error.
            $providerId = null;
            if (! $result->isError()) {
                $provider = PriceProvider::where('code', $result->source)->first();
                $providerId = $provider?->id;

                AssetPrice::create([
                    'investment_id' => $investment->id,
                    'provider_id' => $providerId,
                    'price' => $result->price,
                    'currency' => $result->currency,
                    'fetched_at' => $result->fetchedAt,
                    'source_status' => $result->status,
                    'error_message' => $result->errorMessage,
                    'raw_payload' => $result->rawPayload,
                ]);
            }

            ApiSyncLog::create([
                'run_id' => $runId,
                'provider_id' => $providerId,
                'investment_id' => $investment->id,
                'status' => $result->isError() ? 'error' : $result->status,
                'duration_ms' => $durationMs,
                'error_message' => $result->errorMessage,
                'created_at' => now(),
            ]);
        });

        return $result;
    }
}
