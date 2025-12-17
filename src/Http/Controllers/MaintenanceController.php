<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\App;

class MaintenanceController extends BaseController
{
    /**
     * POST /cdx-agent/maintenance
     *
     * Toggle maintenance mode on or off.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!config('cdx-agent.features.maintenance', true)) {
            return $this->error('Maintenance feature is disabled', 403);
        }

        $enabled = $request->boolean('enabled');
        $secretMessage = $request->input('secret_message');

        try {
            if ($enabled) {
                return $this->enableMaintenance($secretMessage);
            } else {
                return $this->disableMaintenance();
            }
        } catch (\Throwable $e) {
            return $this->error(
                'Failed to toggle maintenance mode',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Enable maintenance mode.
     */
    protected function enableMaintenance(?string $secretMessage): JsonResponse
    {
        if (App::isDownForMaintenance()) {
            return $this->success('Application is already in maintenance mode', [
                'maintenance' => true,
            ]);
        }

        $options = [
            '--retry' => 60,
        ];

        if ($secretMessage) {
            $options['--secret'] = $secretMessage;
        }

        Artisan::call('down', $options);

        return $this->success('Maintenance mode enabled', [
            'maintenance' => true,
        ]);
    }

    /**
     * Disable maintenance mode.
     */
    protected function disableMaintenance(): JsonResponse
    {
        if (!App::isDownForMaintenance()) {
            return $this->success('Application is already live', [
                'maintenance' => false,
            ]);
        }

        Artisan::call('up');

        return $this->success('Maintenance mode disabled', [
            'maintenance' => false,
        ]);
    }
}
