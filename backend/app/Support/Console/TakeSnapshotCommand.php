<?php

namespace App\Support\Console;

use App\Application\Snapshots\SnapshotService;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('patrimoine:snapshot {--user= : Limit to a specific user UUID} {--date= : Snapshot date (Y-m-d), default today}')]
#[Description('Take a portfolio snapshot for all users (or a specific one).')]
class TakeSnapshotCommand extends Command
{
    public function handle(SnapshotService $service): int
    {
        $query = User::query();

        if ($userId = $this->option('user')) {
            $query->where('id', $userId);
        }

        $users = $query->get();
        $date = $this->option('date') ?? now()->toDateString();

        $this->info("Taking snapshot for {$users->count()} user(s) on {$date}...");

        foreach ($users as $user) {
            $snapshot = $service->takeDailySnapshot($user->id, $date);
            $this->line("  [{$user->email}] total={$snapshot->total_value} {$snapshot->currency} ({$snapshot->active_count} assets)");
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
