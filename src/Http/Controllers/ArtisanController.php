<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ArtisanController extends BaseController
{
    /**
     * Execute an artisan command remotely.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $command = $request->input('command');
        $parameters = $request->input('parameters', []);

        if (empty($command)) {
            return response()->json(['error' => 'Command is required'], 400);
        }

        // Whitelist of allowed commands for security
        $allowedCommands = config('cdx-agent.allowed_artisan_commands', [
            'cache:clear',
            'config:clear',
            'config:cache',
            'route:clear',
            'route:cache',
            'view:clear',
            'view:cache',
            'optimize',
            'optimize:clear',
            'queue:restart',
            'queue:retry',
            'migrate:status',
            'storage:link',
            'schedule:list',
        ]);

        // Check if command is allowed
        $baseCommand = explode(' ', $command)[0];
        if (!in_array($baseCommand, $allowedCommands)) {
            return response()->json([
                'error' => 'Command not allowed',
                'allowed_commands' => $allowedCommands,
            ], 403);
        }

        try {
            $startTime = microtime(true);
            
            $exitCode = Artisan::call($command, $parameters);
            $output = Artisan::output();
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'success' => $exitCode === 0,
                'command' => $command,
                'parameters' => $parameters,
                'exit_code' => $exitCode,
                'output' => trim($output),
                'execution_time_ms' => $executionTime,
                'executed_at' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'command' => $command,
                'error' => $e->getMessage(),
                'executed_at' => now()->toIso8601String(),
            ], 500);
        }
    }

    /**
     * Get list of available artisan commands.
     */
    public function list(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $allowedCommands = config('cdx-agent.allowed_artisan_commands', [
            'cache:clear',
            'config:clear',
            'config:cache',
            'route:clear',
            'route:cache',
            'view:clear',
            'view:cache',
            'optimize',
            'optimize:clear',
            'queue:restart',
            'queue:retry',
            'migrate:status',
            'storage:link',
            'schedule:list',
        ]);

        return response()->json([
            'allowed_commands' => $allowedCommands,
            'total' => count($allowedCommands),
        ]);
    }
}
