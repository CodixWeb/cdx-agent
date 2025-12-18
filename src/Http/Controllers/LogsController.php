<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogsController extends BaseController
{
    /**
     * Get recent application logs.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $lines = (int) $request->input('lines', 100);
            $level = $request->input('level'); // filter by level: error, warning, info, etc.
            
            $logs = $this->getRecentLogs($lines, $level);

            return $this->success('Logs retrieved', [
                'logs' => $logs,
                'count' => count($logs),
            ]);
        } catch (\Throwable $e) {
            return $this->error('Failed to get logs', 500, $e->getMessage());
        }
    }

    /**
     * Get recent logs from Laravel log file.
     */
    protected function getRecentLogs(int $lines = 100, ?string $level = null): array
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            return [];
        }

        // Read last N lines efficiently
        $file = new \SplFileObject($logPath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();
        
        $startLine = max(0, $totalLines - ($lines * 3)); // Get more lines to account for multi-line entries
        $file->seek($startLine);

        $rawLines = [];
        while (!$file->eof()) {
            $rawLines[] = $file->fgets();
        }

        // Parse log entries
        $entries = $this->parseLogEntries(implode('', $rawLines));
        
        // Filter by level if specified
        if ($level) {
            $entries = array_filter($entries, fn ($entry) => 
                strtolower($entry['level']) === strtolower($level)
            );
        }

        // Return last N entries
        return array_slice(array_values($entries), -$lines);
    }

    /**
     * Parse Laravel log entries.
     */
    protected function parseLogEntries(string $content): array
    {
        $entries = [];
        $pattern = '/\[(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}\.?\d*[\+\-]?\d*:?\d*)\]\s+(\w+)\.(\w+):\s+(.+?)(?=\[\d{4}-\d{2}-\d{2}|\Z)/s';
        
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $message = trim($match[4]);
            $context = [];
            
            // Try to extract JSON context
            if (preg_match('/^(.+?)\s*(\{.+\}|\[.+\])\s*$/s', $message, $contextMatch)) {
                $message = trim($contextMatch[1]);
                $jsonContext = json_decode($contextMatch[2], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $context = $jsonContext;
                }
            }

            $entries[] = [
                'timestamp' => $match[1],
                'environment' => $match[2],
                'level' => strtolower($match[3]),
                'message' => \Illuminate\Support\Str::limit($message, 500),
                'context' => $context,
            ];
        }

        return $entries;
    }
}
