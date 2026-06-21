<?php

use App\Http\Controllers\Api\V1\AssetTypeController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\InvestmentController;
use App\Http\Controllers\Api\V1\InvestmentStrategyController;
use App\Http\Controllers\Api\V1\ManualValueController;
use App\Http\Controllers\Api\V1\PriceProviderController;
use App\Http\Controllers\Api\V1\RefreshPriceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Auth
    Route::post('/auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');
    Route::post('/auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Reference data
        Route::get('/asset-types', [AssetTypeController::class, 'index']);
        Route::get('/price-providers', [PriceProviderController::class, 'index']);
        Route::get('/currencies', [CurrencyController::class, 'index']);

        // Investments CRUD
        Route::get('/investments', [InvestmentController::class, 'index']);
        Route::post('/investments', [InvestmentController::class, 'store']);
        Route::get('/investments/{investment}', [InvestmentController::class, 'show']);
        Route::put('/investments/{investment}', [InvestmentController::class, 'update']);
        Route::patch('/investments/{investment}', [InvestmentController::class, 'update']);
        Route::delete('/investments/{investment}', [InvestmentController::class, 'destroy']);

        // Investment strategy
        Route::get('/investment-strategy', [InvestmentStrategyController::class, 'show']);
        Route::put('/investment-strategy', [InvestmentStrategyController::class, 'update']);

        // Manual valuation
        Route::post('/investments/{investment}/manual-value', ManualValueController::class);

        // Refresh price (fetch from external provider)
        Route::post('/investments/{investment}/refresh-price', RefreshPriceController::class);

        // Dashboard
        Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
        Route::get('/dashboard/allocation', [DashboardController::class, 'allocation']);
        Route::get('/dashboard/breakdown', [DashboardController::class, 'breakdown']);
        Route::get('/dashboard/performance', [DashboardController::class, 'performance']);
        Route::get('/dashboard/geography', [DashboardController::class, 'geography']);
        Route::get('/dashboard/country-allocation', [DashboardController::class, 'countryAllocation']);
        Route::get('/dashboard/sector-allocation', [DashboardController::class, 'sectorAllocation']);

        // Export
        Route::get('/exports/portfolio.json', [ExportController::class, 'json']);
        Route::get('/exports/portfolio.csv', [ExportController::class, 'csv']);

        // AI Chat
        Route::get('/chat/models', [ChatController::class, 'models']);
        Route::put('/chat/api-key', [ChatController::class, 'apiKey'])
            ->middleware('throttle:5,1');
        Route::delete('/chat/api-key', [ChatController::class, 'deleteApiKey'])
            ->middleware('throttle:5,1');
        Route::post('/chat', [ChatController::class, 'stream'])
            ->middleware('throttle:20,1');

        // Health
        Route::get('/health', fn () => response()->json(['status' => 'ok']));
    });
});
