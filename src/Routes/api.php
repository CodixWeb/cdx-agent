<?php

declare(strict_types=1);

use Codix\CdxAgent\Http\Controllers\CacheController;
use Codix\CdxAgent\Http\Controllers\HealthController;
use Codix\CdxAgent\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

Route::prefix(config('cdx-agent.route_prefix', 'cdx-agent'))
    ->middleware(config('cdx-agent.middleware', ['api']))
    ->group(function () {
        // Health check endpoint
        Route::get('/health', HealthController::class)
            ->name('cdx-agent.health');

        // Maintenance mode toggle
        Route::post('/maintenance', MaintenanceController::class)
            ->name('cdx-agent.maintenance');

        // Clear caches
        Route::post('/clear-caches', CacheController::class)
            ->name('cdx-agent.clear-caches');
    });
