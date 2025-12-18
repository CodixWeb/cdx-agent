<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

class SelfUpdateController extends BaseController
{
    /**
     * Get current agent version and check for updates.
     */
    public function __invoke(): JsonResponse
    {
        $currentVersion = $this->getCurrentVersion();
        
        return response()->json([
            'ok' => true,
            'data' => [
                'current_version' => $currentVersion,
                'package' => 'codix/cdx-agent',
            ],
        ]);
    }

    /**
     * Perform self-update of the agent package.
     */
    public function update(): JsonResponse
    {
        $beforeVersion = $this->getCurrentVersion();
        
        try {
            // Run composer update for the package
            $composerResult = Process::timeout(120)
                ->path(base_path())
                ->run('composer update codix/cdx-agent --no-interaction --no-progress 2>&1');
            
            $composerOutput = $composerResult->output();
            $composerSuccess = $composerResult->successful();
            
            // Clear route cache to pick up new routes
            Artisan::call('route:clear');
            
            // Clear other caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            
            $afterVersion = $this->getCurrentVersion();
            
            return response()->json([
                'ok' => true,
                'data' => [
                    'before_version' => $beforeVersion,
                    'after_version' => $afterVersion,
                    'updated' => $beforeVersion !== $afterVersion,
                    'composer_success' => $composerSuccess,
                    'composer_output' => $composerOutput,
                    'caches_cleared' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Update failed: ' . $e->getMessage(),
                'data' => [
                    'before_version' => $beforeVersion,
                ],
            ], 500);
        }
    }

    /**
     * Get the current installed version of the agent.
     */
    protected function getCurrentVersion(): string
    {
        $composerLock = base_path('composer.lock');
        
        if (!file_exists($composerLock)) {
            return 'unknown';
        }
        
        $lock = json_decode(file_get_contents($composerLock), true);
        
        foreach ($lock['packages'] ?? [] as $package) {
            if ($package['name'] === 'codix/cdx-agent') {
                return $package['version'] ?? 'unknown';
            }
        }
        
        return 'not-installed';
    }
}
