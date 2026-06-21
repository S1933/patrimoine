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
 * Records per-provider api_sync_logs rows + an asset_prices row (unless fallback).
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

        $totalDurationMs = (int) ((microtime(true) - $start) * 1000);

        DB::transaction(function () use ($investment, $result, $runId, $totalDurationMs) {
            $this->logAttempts($result, $investment, $runId);

            if ($result->isError()) {
                ApiSyncLog::create([
                    'run_id' => $runId,
                    'investment_id' => $investment->id,
                    'status' => 'error',
                    'duration_ms' => $totalDurationMs,
                    'error_message' => $result->errorMessage,
                    'created_at' => now(),
                ]);

                return;
            }

            if ($result->status === 'fallback') {
                ApiSyncLog::create([
                    'run_id' => $runId,
                    'investment_id' => $investment->id,
                    'status' => 'fallback',
                    'duration_ms' => $totalDurationMs,
                    'error_message' => $result->errorMessage,
                    'created_at' => now(),
                ]);

                return;
            }

            // Only persist a new asset_price row for fresh (non-fallback) results.
            $provider = PriceProvider::where('code', $result->source)->first();

            AssetPrice::create([
                'investment_id' => $investment->id,
                'provider_id' => $provider?->id,
                'price' => $result->price,
                'currency' => $result->currency,
                'fetched_at' => $result->fetchedAt,
                'source_status' => $result->status,
                'error_message' => $result->errorMessage,
                'raw_payload' => $result->rawPayload,
            ]);

            ApiSyncLog::create([
                'run_id' => $runId,
                'investment_id' => $investment->id,
                'provider_id' => $provider?->id,
                'status' => $result->status,
                'duration_ms' => $totalDurationMs,
                'error_message' => $result->errorMessage,
                'created_at' => now(),
            ]);
        });

        return $result;
    }

    private function logAttempts(PriceResult $result, Investment $investment, string $runId): void
    {
        foreach ($result->attempts as $attempt) {
            $provider = PriceProvider::where('code', $attempt['provider'])->first();
            $status = $attempt['status'] === 'unavailable' ? 'error' : ($attempt['status'] === 'success' ? 'success' : 'error');

            ApiSyncLog::create([
                'run_id' => $runId,
                'provider_id' => $provider?->id,
                'investment_id' => $investment->id,
                'status' => $status,
                'duration_ms' => $attempt['duration_ms'],
                'error_message' => $attempt['error'],
                'created_at' => now(),
            ]);
        }
    }
}
