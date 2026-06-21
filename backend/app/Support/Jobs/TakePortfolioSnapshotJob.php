<?php

namespace App\Support\Jobs;

use App\Application\Snapshots\SnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TakePortfolioSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly string $userId,
        public readonly ?string $date = null,
    ) {}

    public function handle(SnapshotService $service): void
    {
        $service->takeDailySnapshot($this->userId, $this->date);
    }
}
