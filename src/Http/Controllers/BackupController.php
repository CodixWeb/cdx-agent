<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BackupController extends BaseController
{
    /**
     * Get backup status (compatible with Spatie Laravel Backup).
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $backups = [];
        $hasSpatieBakup = class_exists(\Spatie\Backup\BackupDestination\BackupDestination::class);

        if ($hasSpatieBakup) {
            $backups = $this->getSpatieBackups();
        } else {
            // Fallback: Check common backup directories
            $backups = $this->getManualBackups();
        }

        // Calculate stats
        $totalSize = collect($backups)->sum('size');
        $lastBackup = collect($backups)->sortByDesc('date')->first();
        $oldestBackup = collect($backups)->sortBy('date')->first();

        // Determine health status
        $healthStatus = 'healthy';
        $healthMessage = 'Backups are up to date';

        if (empty($backups)) {
            $healthStatus = 'warning';
            $healthMessage = 'No backups found';
        } elseif ($lastBackup) {
            $lastBackupDate = \Carbon\Carbon::parse($lastBackup['date']);
            $daysSinceLastBackup = $lastBackupDate->diffInDays(now());

            if ($daysSinceLastBackup > 7) {
                $healthStatus = 'critical';
                $healthMessage = "Last backup is {$daysSinceLastBackup} days old";
            } elseif ($daysSinceLastBackup > 3) {
                $healthStatus = 'warning';
                $healthMessage = "Last backup is {$daysSinceLastBackup} days old";
            }
        }

        return response()->json([
            'spatie_backup_installed' => $hasSpatieBakup,
            'health_status' => $healthStatus,
            'health_message' => $healthMessage,
            'stats' => [
                'total_backups' => count($backups),
                'total_size' => $totalSize,
                'total_size_human' => $this->formatBytes($totalSize),
                'last_backup' => $lastBackup,
                'oldest_backup' => $oldestBackup,
            ],
            'backups' => array_slice($backups, 0, 20), // Return last 20 backups
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get backups from Spatie Backup package.
     */
    protected function getSpatieBackups(): array
    {
        $backups = [];

        try {
            $config = config('backup.backup.destination.disks', ['local']);
            $backupName = config('backup.backup.name', config('app.name'));

            foreach ($config as $diskName) {
                $disk = Storage::disk($diskName);
                $path = $backupName;

                if (!$disk->exists($path)) {
                    continue;
                }

                $files = $disk->files($path);

                foreach ($files as $file) {
                    if (str_ends_with($file, '.zip')) {
                        $backups[] = [
                            'filename' => basename($file),
                            'path' => $file,
                            'disk' => $diskName,
                            'size' => $disk->size($file),
                            'size_human' => $this->formatBytes($disk->size($file)),
                            'date' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($file))->toIso8601String(),
                            'age_human' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($file))->diffForHumans(),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silently fail if backup config is not available
        }

        return collect($backups)->sortByDesc('date')->values()->toArray();
    }

    /**
     * Get backups from common backup directories.
     */
    protected function getManualBackups(): array
    {
        $backups = [];
        $backupPaths = [
            storage_path('backups'),
            storage_path('app/backups'),
            base_path('backups'),
        ];

        foreach ($backupPaths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $files = glob($path . '/*.{zip,sql,gz,tar}', GLOB_BRACE);

            foreach ($files as $file) {
                $backups[] = [
                    'filename' => basename($file),
                    'path' => $file,
                    'disk' => 'local',
                    'size' => filesize($file),
                    'size_human' => $this->formatBytes(filesize($file)),
                    'date' => \Carbon\Carbon::createFromTimestamp(filemtime($file))->toIso8601String(),
                    'age_human' => \Carbon\Carbon::createFromTimestamp(filemtime($file))->diffForHumans(),
                ];
            }
        }

        return collect($backups)->sortByDesc('date')->values()->toArray();
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
