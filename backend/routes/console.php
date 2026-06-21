<?php

use App\Models\User;
use App\Support\Console\SyncPricesCommand;
use App\Support\Console\TakeSnapshotCommand;
use App\Support\Jobs\FetchAllPricesJob;
use App\Support\Jobs\TakePortfolioSnapshotJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily price sync: fetch fresh prices at 09:00 and 18:00 UTC.
Schedule::command(SyncPricesCommand::class)->twiceDaily(9, 18)->timezone('UTC')
    ->name('patrimoine:sync-prices')
    ->withoutOverlapping()
    ->onOneServer();

// Daily portfolio snapshot at 23:00 UTC (after the last price sync).
Schedule::command(TakeSnapshotCommand::class)->dailyAt('23:00')->timezone('UTC')
    ->name('patrimoine:snapshot')
    ->withoutOverlapping()
    ->onOneServer();
