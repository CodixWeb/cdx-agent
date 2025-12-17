<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;

class CacheController extends BaseController
{
    /**
     * POST /cdx-agent/clear-caches
     *
     * Clear all application caches.
     */
    public function __invoke(): JsonResponse
    {
        if (!config('cdx-agent.features.cache_clear', true)) {
            return $this->error('Cache clear feature is disabled', 403);
        }

        $results = [];

        try {
            // Clear config cache
            Artisan::call('config:clear');
            $results['config'] = 'cleared';
        } catch (\Throwable $e) {
            $results['config'] = 'error: ' . $e->getMessage();
        }

        try {
            // Clear route cache
            Artisan::call('route:clear');
            $results['route'] = 'cleared';
        } catch (\Throwable $e) {
            $results['route'] = 'error: ' . $e->getMessage();
        }

        try {
            // Clear view cache
            Artisan::call('view:clear');
            $results['view'] = 'cleared';
        } catch (\Throwable $e) {
            $results['view'] = 'error: ' . $e->getMessage();
        }

        try {
            // Clear application cache
            Artisan::call('cache:clear');
            $results['cache'] = 'cleared';
        } catch (\Throwable $e) {
            $results['cache'] = 'error: ' . $e->getMessage();
        }

        try {
            // Clear event cache
            Artisan::call('event:clear');
            $results['event'] = 'cleared';
        } catch (\Throwable $e) {
            $results['event'] = 'error: ' . $e->getMessage();
        }

        // Check if all operations were successful
        $hasErrors = collect($results)->contains(fn($value) => str_starts_with($value, 'error:'));

        if ($hasErrors) {
            return $this->success('Caches cleared with some errors', [
                'results' => $results,
            ]);
        }

        return $this->success('All caches cleared successfully', [
            'results' => $results,
        ]);
    }
}
