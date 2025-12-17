<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;

class HealthController extends BaseController
{
    /**
     * GET /cdx-agent/health
     *
     * Returns the health status and system information.
     */
    public function __invoke(): JsonResponse
    {
        $data = [
            'app_name' => config('app.name'),
            'app_env' => config('app.env'),
            'laravel_version' => App::version(),
            'php_version' => PHP_VERSION,
            'time' => now()->toIso8601String(),
            'timezone' => config('app.timezone'),
            'maintenance' => App::isDownForMaintenance(),
        ];

        // Add Git info if enabled
        if (config('cdx-agent.features.git_info', true)) {
            $data['git'] = $this->getGitInfo();
        }

        // Add Queue info if enabled
        if (config('cdx-agent.features.queue_info', true)) {
            $data['queue'] = $this->getQueueInfo();
        }

        return $this->success('Health check successful', $data);
    }

    /**
     * Get Git repository information.
     */
    protected function getGitInfo(): ?array
    {
        $basePath = base_path();

        // Check if .git directory exists
        if (!is_dir($basePath . '/.git')) {
            return null;
        }

        $branch = null;
        $commit = null;

        // Get current branch
        $headFile = $basePath . '/.git/HEAD';
        if (is_file($headFile)) {
            $headContent = trim(file_get_contents($headFile));
            if (str_starts_with($headContent, 'ref: refs/heads/')) {
                $branch = substr($headContent, 16);
            } else {
                $commit = $headContent; // Detached HEAD
            }
        }

        // Get current commit hash
        if ($branch && $commit === null) {
            $refFile = $basePath . '/.git/refs/heads/' . $branch;
            if (is_file($refFile)) {
                $commit = trim(file_get_contents($refFile));
            }
        }

        return [
            'branch' => $branch,
            'commit' => $commit ? substr($commit, 0, 8) : null,
            'commit_full' => $commit,
        ];
    }

    /**
     * Get queue information.
     */
    protected function getQueueInfo(): ?array
    {
        try {
            $connection = config('queue.default');
            $driver = config("queue.connections.{$connection}.driver");

            $info = [
                'connection' => $connection,
                'driver' => $driver,
            ];

            // Get pending jobs count for database driver
            if ($driver === 'database') {
                $table = config("queue.connections.{$connection}.table", 'jobs');
                $info['pending_jobs'] = \DB::table($table)->count();
                $info['failed_jobs'] = \DB::table('failed_jobs')->count();
            }

            return $info;
        } catch (\Throwable $e) {
            return [
                'error' => 'Unable to retrieve queue info',
            ];
        }
    }
}
