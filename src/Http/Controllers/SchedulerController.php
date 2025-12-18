<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SchedulerController extends BaseController
{
    /**
     * Get scheduler information.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $info = $this->getSchedulerInfo();

            return $this->success('Scheduler info retrieved', $info);
        } catch (\Throwable $e) {
            return $this->error('Failed to get scheduler info', 500, $e->getMessage());
        }
    }

    /**
     * Get scheduled tasks information.
     */
    protected function getSchedulerInfo(): array
    {
        $schedule = app(Schedule::class);
        $events = $schedule->events();

        $tasks = [];
        foreach ($events as $event) {
            $command = $event->command ?? $event->description ?? 'Closure';
            
            // Clean up command string
            if (str_contains($command, "'artisan'")) {
                preg_match("/artisan['\"]?\s+([^'\"]+)/", $command, $matches);
                $command = 'artisan ' . ($matches[1] ?? $command);
            }

            $tasks[] = [
                'command' => $command,
                'expression' => $event->expression,
                'description' => $event->description,
                'timezone' => $event->timezone,
                'without_overlapping' => $event->withoutOverlapping,
                'on_one_server' => $event->onOneServer ?? false,
                'in_background' => $event->runInBackground,
                'next_run' => $this->getNextRunDate($event->expression, $event->timezone),
                'last_run' => $this->getLastRunInfo($command),
            ];
        }

        return [
            'tasks' => $tasks,
            'count' => count($tasks),
            'scheduler_running' => $this->isSchedulerRunning(),
        ];
    }

    /**
     * Calculate next run date from cron expression.
     */
    protected function getNextRunDate(string $expression, ?string $timezone = null): ?string
    {
        try {
            $cron = new \Cron\CronExpression($expression);
            $next = $cron->getNextRunDate('now', 0, false, $timezone ?? config('app.timezone'));
            return $next->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Get last run information from cache (if available).
     */
    protected function getLastRunInfo(string $command): ?array
    {
        // Laravel stores schedule run info in cache with schedule-{mutexName} pattern
        // This is a simplified approach - in production you might want to track this more precisely
        $cacheKey = 'schedule:' . md5($command);
        
        $lastRun = Cache::get($cacheKey);
        
        if ($lastRun) {
            return [
                'ran_at' => $lastRun['ran_at'] ?? null,
                'duration_ms' => $lastRun['duration_ms'] ?? null,
                'status' => $lastRun['status'] ?? 'unknown',
            ];
        }

        return null;
    }

    /**
     * Check if scheduler is running (by checking schedule:run was called recently).
     */
    protected function isSchedulerRunning(): bool
    {
        // Check if schedule:run was executed in the last 5 minutes
        $lastScheduleRun = Cache::get('cdx-agent:scheduler:last_run');
        
        if ($lastScheduleRun) {
            return now()->diffInMinutes($lastScheduleRun) < 5;
        }

        return false; // Unknown - need to track this
    }
}
