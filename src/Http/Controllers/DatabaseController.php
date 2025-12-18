<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DatabaseController extends BaseController
{
    /**
     * Get database health information.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $health = $this->getDatabaseHealth();

            return $this->success('Database health retrieved', $health);
        } catch (\Throwable $e) {
            return $this->error('Failed to get database health', 500, $e->getMessage());
        }
    }

    /**
     * Get comprehensive database health metrics.
     */
    protected function getDatabaseHealth(): array
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}");

        $health = [
            'driver' => $driver,
            'database' => $connection['database'] ?? 'unknown',
            'host' => $connection['host'] ?? 'unknown',
            'connected' => false,
            'response_time_ms' => null,
            'tables' => [],
            'size' => null,
            'connections' => null,
            'slow_queries' => [],
        ];

        // Test connection and measure response time
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            $health['connected'] = true;
            $health['response_time_ms'] = round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable $e) {
            $health['error'] = $e->getMessage();
            return $health;
        }

        // Get database-specific metrics
        if ($driver === 'mysql') {
            $health = array_merge($health, $this->getMySqlMetrics($connection['database']));
        } elseif ($driver === 'pgsql') {
            $health = array_merge($health, $this->getPostgresMetrics($connection['database']));
        } elseif ($driver === 'sqlite') {
            $health = array_merge($health, $this->getSqliteMetrics($connection['database']));
        }

        return $health;
    }

    /**
     * Get MySQL specific metrics.
     */
    protected function getMySqlMetrics(string $database): array
    {
        $metrics = [];

        // Database size
        try {
            $size = DB::select("
                SELECT 
                    SUM(data_length + index_length) as size_bytes
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [$database]);
            
            $sizeBytes = $size[0]->size_bytes ?? 0;
            $metrics['size'] = [
                'bytes' => (int) $sizeBytes,
                'formatted' => $this->formatBytes((int) $sizeBytes),
            ];
        } catch (\Throwable $e) {
            $metrics['size'] = ['error' => $e->getMessage()];
        }

        // Table info
        try {
            $tables = DB::select("
                SELECT 
                    table_name,
                    table_rows as row_count,
                    data_length + index_length as size_bytes,
                    update_time
                FROM information_schema.tables 
                WHERE table_schema = ?
                ORDER BY data_length + index_length DESC
                LIMIT 20
            ", [$database]);

            $metrics['tables'] = array_map(fn ($table) => [
                'name' => $table->table_name,
                'rows' => (int) $table->row_count,
                'size' => $this->formatBytes((int) $table->size_bytes),
                'updated_at' => $table->update_time,
            ], $tables);
        } catch (\Throwable $e) {
            $metrics['tables'] = ['error' => $e->getMessage()];
        }

        // Active connections
        try {
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            
            $metrics['connections'] = [
                'current' => (int) ($connections[0]->Value ?? 0),
                'max' => (int) ($maxConnections[0]->Value ?? 0),
            ];
        } catch (\Throwable $e) {
            $metrics['connections'] = ['error' => $e->getMessage()];
        }

        // Slow queries (if slow query log is enabled)
        try {
            $slowLog = DB::select("SHOW VARIABLES LIKE 'slow_query_log'");
            if (($slowLog[0]->Value ?? 'OFF') === 'ON') {
                $metrics['slow_queries_enabled'] = true;
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        return $metrics;
    }

    /**
     * Get PostgreSQL specific metrics.
     */
    protected function getPostgresMetrics(string $database): array
    {
        $metrics = [];

        // Database size
        try {
            $size = DB::select("SELECT pg_database_size(?) as size_bytes", [$database]);
            $sizeBytes = $size[0]->size_bytes ?? 0;
            $metrics['size'] = [
                'bytes' => (int) $sizeBytes,
                'formatted' => $this->formatBytes((int) $sizeBytes),
            ];
        } catch (\Throwable $e) {
            $metrics['size'] = ['error' => $e->getMessage()];
        }

        // Table info
        try {
            $tables = DB::select("
                SELECT 
                    schemaname || '.' || relname as table_name,
                    n_live_tup as row_count,
                    pg_total_relation_size(relid) as size_bytes
                FROM pg_stat_user_tables
                ORDER BY pg_total_relation_size(relid) DESC
                LIMIT 20
            ");

            $metrics['tables'] = array_map(fn ($table) => [
                'name' => $table->table_name,
                'rows' => (int) $table->row_count,
                'size' => $this->formatBytes((int) $table->size_bytes),
            ], $tables);
        } catch (\Throwable $e) {
            $metrics['tables'] = ['error' => $e->getMessage()];
        }

        // Active connections
        try {
            $connections = DB::select("SELECT count(*) as current FROM pg_stat_activity WHERE datname = ?", [$database]);
            $maxConnections = DB::select("SHOW max_connections");
            
            $metrics['connections'] = [
                'current' => (int) ($connections[0]->current ?? 0),
                'max' => (int) ($maxConnections[0]->max_connections ?? 0),
            ];
        } catch (\Throwable $e) {
            $metrics['connections'] = ['error' => $e->getMessage()];
        }

        return $metrics;
    }

    /**
     * Get SQLite specific metrics.
     */
    protected function getSqliteMetrics(string $database): array
    {
        $metrics = [];

        // Database file size
        try {
            if (file_exists($database)) {
                $sizeBytes = filesize($database);
                $metrics['size'] = [
                    'bytes' => $sizeBytes,
                    'formatted' => $this->formatBytes($sizeBytes),
                ];
            }
        } catch (\Throwable $e) {
            $metrics['size'] = ['error' => $e->getMessage()];
        }

        // Table info
        try {
            $tables = DB::select("
                SELECT name as table_name
                FROM sqlite_master 
                WHERE type='table' AND name NOT LIKE 'sqlite_%'
            ");

            $metrics['tables'] = array_map(function ($table) {
                $count = DB::select("SELECT COUNT(*) as count FROM \"{$table->table_name}\"");
                return [
                    'name' => $table->table_name,
                    'rows' => (int) ($count[0]->count ?? 0),
                ];
            }, $tables);
        } catch (\Throwable $e) {
            $metrics['tables'] = ['error' => $e->getMessage()];
        }

        return $metrics;
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
