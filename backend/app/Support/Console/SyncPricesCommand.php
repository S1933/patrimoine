<?php

namespace App\Support\Console;

use App\Application\Pricing\FetchInvestmentPrice;
use App\Models\Investment;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('patrimoine:sync-prices {--user= : Limit to a specific user UUID}')]
#[Description('Fetch fresh prices for all active externally-priced investments.')]
class SyncPricesCommand extends Command
{
    public function handle(FetchInvestmentPrice $fetchPrice): int
    {
        $query = Investment::query()
            ->where('status', 'active')
            ->whereNull('manual_value')
            ->with('assetType');

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $investments = $query->get();
        $this->info("Syncing prices for {$investments->count()} investment(s)...");

        $success = 0;
        $errors = 0;

        foreach ($investments as $investment) {
            try {
                $result = $fetchPrice->execute($investment);
                if ($result->isError()) {
                    $this->error("  [{$investment->name}] {$result->errorMessage}");
                    $errors++;
                } else {
                    $this->line("  [{$investment->name}] {$result->price} {$result->currency} via {$result->source}");
                    $success++;
                }
            } catch (\Throwable $e) {
                $this->error("  [{$investment->name}] {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Done: {$success} success, {$errors} errors.");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
