<?php

namespace App\Support\Jobs;

use App\Application\Pricing\FetchInvestmentPrice;
use App\Models\Investment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchAllPricesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly string $userId,
    ) {}

    public function handle(FetchInvestmentPrice $fetchPrice): void
    {
        $investments = Investment::forUser($this->userId)
            ->where('status', 'active')
            ->whereNull('manual_value') // only externally-priced ones
            ->with('assetType')
            ->get();

        Log::info('FetchAllPricesJob started', ['user' => $this->userId, 'count' => $investments->count()]);

        foreach ($investments as $investment) {
            try {
                $fetchPrice->execute($investment);
            } catch (\Throwable $e) {
                Log::error('Price fetch failed in batch', [
                    'investment' => $investment->id,
                    'error' => $e->getMessage(),
                ]);
                // Continue to next investment — one failure must not abort the whole batch.
            }
        }

        Log::info('FetchAllPricesJob completed', ['user' => $this->userId]);
    }
}
