<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QueueController extends BaseController
{
    /**
     * Get queue statistics.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $stats = $this->getQueueStats();

            return $this->success('Queue stats retrieved', $stats);
        } catch (\Throwable $e) {
            return $this->error('Failed to get queue stats', 500, $e->getMessage());
        }
    }

    /**
     * Get queue statistics from database.
     */
    protected function getQueueStats(): array
    {
        $connection = config('queue.default');
        $stats = [
            'connection' => $connection,
            'jobs' => [],
            'failed' => [],
            'totals' => [
                'pending' => 0,
                'processing' => 0,
                'failed' => 0,
            ],
        ];

        // Only works with database queue driver
        if ($connection === 'database') {
            $table = config('queue.connections.database.table', 'jobs');
            
            // Get pending jobs (not reserved)
            $pending = DB::table($table)
                ->whereNull('reserved_at')
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->get();

            // Get processing jobs (reserved)
            $processing = DB::table($table)
                ->whereNotNull('reserved_at')
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->get();

            $stats['jobs'] = [
                'pending' => $pending->pluck('count', 'queue')->toArray(),
                'processing' => $processing->pluck('count', 'queue')->toArray(),
            ];

            $stats['totals']['pending'] = $pending->sum('count');
            $stats['totals']['processing'] = $processing->sum('count');
        }

        // Get failed jobs (works with all drivers that have failed_jobs table)
        try {
            $failedTable = config('queue.failed.table', 'failed_jobs');
            
            $failed = DB::table($failedTable)
                ->select('queue', DB::raw('COUNT(*) as count'))
                ->groupBy('queue')
                ->get();

            $recentFailed = DB::table($failedTable)
                ->orderByDesc('failed_at')
                ->limit(10)
                ->get(['id', 'uuid', 'queue', 'payload', 'exception', 'failed_at']);

            $stats['failed'] = [
                'by_queue' => $failed->pluck('count', 'queue')->toArray(),
                'recent' => $recentFailed->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    return [
                        'id' => $job->id,
                        'uuid' => $job->uuid,
                        'queue' => $job->queue,
                        'job' => $payload['displayName'] ?? 'Unknown',
                        'exception' => \Illuminate\Support\Str::limit($job->exception, 200),
                        'failed_at' => $job->failed_at,
                    ];
                })->toArray(),
            ];

            $stats['totals']['failed'] = $failed->sum('count');
        } catch (\Throwable $e) {
            // Failed jobs table might not exist
            $stats['failed'] = ['error' => 'Failed jobs table not available'];
        }

        return $stats;
    }
}
