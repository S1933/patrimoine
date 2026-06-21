<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Eloquent strict mode in non-production to catch lazy-loading / non-existent attrs.
        if (! $this->app->environment('production')) {
            \Illuminate\Database\Eloquent\Model::shouldBeStrict();
        }
    }
}
