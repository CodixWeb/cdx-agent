<?php

declare(strict_types=1);

use Codix\CdxAgent\Http\Controllers\AlertController;
use Codix\CdxAgent\Http\Controllers\ArtisanController;
use Codix\CdxAgent\Http\Controllers\BackupController;
use Codix\CdxAgent\Http\Controllers\CacheController;
use Codix\CdxAgent\Http\Controllers\DatabaseController;
use Codix\CdxAgent\Http\Controllers\HealthController;
use Codix\CdxAgent\Http\Controllers\LogsController;
use Codix\CdxAgent\Http\Controllers\MaintenanceController;
use Codix\CdxAgent\Http\Controllers\QueueController;
use Codix\CdxAgent\Http\Controllers\SchedulerController;
use Codix\CdxAgent\Http\Controllers\SelfUpdateController;
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

        // Queue stats (Phase 2)
        Route::get('/queue', QueueController::class)
            ->name('cdx-agent.queue');

        // Application logs (Phase 2)
        Route::get('/logs', LogsController::class)
            ->name('cdx-agent.logs');

        // Database health (Phase 2)
        Route::get('/database', DatabaseController::class)
            ->name('cdx-agent.database');

        // Scheduler info (Phase 2)
        Route::get('/scheduler', SchedulerController::class)
            ->name('cdx-agent.scheduler');

        // Artisan commands (Phase 3)
        Route::post('/artisan', ArtisanController::class)
            ->name('cdx-agent.artisan');
        Route::get('/artisan/list', [ArtisanController::class, 'list'])
            ->name('cdx-agent.artisan.list');

        // Backup status (Phase 3)
        Route::get('/backup', BackupController::class)
            ->name('cdx-agent.backup');

        // Alert/Notification status (Phase 3)
        Route::get('/alerts', AlertController::class)
            ->name('cdx-agent.alerts');
        Route::post('/alerts/test', [AlertController::class, 'test'])
            ->name('cdx-agent.alerts.test');

        // Self-update (Phase 3)
        Route::get('/version', SelfUpdateController::class)
            ->name('cdx-agent.version');
        Route::post('/update', [SelfUpdateController::class, 'update'])
            ->name('cdx-agent.update');
    });
