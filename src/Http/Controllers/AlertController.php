<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends BaseController
{
    /**
     * Get current alert/notification configuration and status.
     */
    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Check available notification channels
        $channels = [
            'mail' => $this->checkMailChannel(),
            'slack' => $this->checkSlackChannel(),
            'discord' => $this->checkDiscordChannel(),
            'database' => $this->checkDatabaseChannel(),
        ];

        // Get recent notifications if available
        $recentNotifications = $this->getRecentNotifications();

        return response()->json([
            'channels' => $channels,
            'active_channels' => collect($channels)->filter(fn($c) => $c['configured'])->keys()->values()->toArray(),
            'recent_notifications' => $recentNotifications,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Check mail channel configuration.
     */
    protected function checkMailChannel(): array
    {
        $configured = !empty(config('mail.mailers.smtp.host')) || 
                      !empty(config('mail.mailers.ses.key')) ||
                      config('mail.default') !== 'log';

        return [
            'configured' => $configured,
            'driver' => config('mail.default'),
            'from_address' => config('mail.from.address'),
            'from_name' => config('mail.from.name'),
        ];
    }

    /**
     * Check Slack channel configuration.
     */
    protected function checkSlackChannel(): array
    {
        $webhookUrl = config('services.slack.webhook_url') ?? 
                      config('logging.channels.slack.url');

        return [
            'configured' => !empty($webhookUrl),
            'webhook_configured' => !empty($webhookUrl),
        ];
    }

    /**
     * Check Discord channel configuration.
     */
    protected function checkDiscordChannel(): array
    {
        $webhookUrl = config('services.discord.webhook_url') ?? 
                      config('logging.channels.discord.url');

        return [
            'configured' => !empty($webhookUrl),
            'webhook_configured' => !empty($webhookUrl),
        ];
    }

    /**
     * Check database notification channel.
     */
    protected function checkDatabaseChannel(): array
    {
        $hasNotificationsTable = false;

        try {
            $hasNotificationsTable = \Illuminate\Support\Facades\Schema::hasTable('notifications');
        } catch (\Exception $e) {
            // Database connection might fail
        }

        return [
            'configured' => $hasNotificationsTable,
            'table_exists' => $hasNotificationsTable,
        ];
    }

    /**
     * Get recent notifications from database.
     */
    protected function getRecentNotifications(): array
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('notifications')) {
                return [];
            }

            $notifications = \Illuminate\Support\Facades\DB::table('notifications')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(function ($notification) {
                    $data = json_decode($notification->data, true);
                    return [
                        'id' => $notification->id,
                        'type' => class_basename($notification->type),
                        'data' => $data,
                        'read' => !is_null($notification->read_at),
                        'created_at' => $notification->created_at,
                    ];
                })
                ->toArray();

            return $notifications;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Test sending a notification to a specific channel.
     */
    public function test(Request $request): JsonResponse
    {
        if (!$this->authenticate($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $channel = $request->input('channel', 'discord');
        $message = $request->input('message', 'Test notification from CDX-Agent');

        try {
            switch ($channel) {
                case 'discord':
                    return $this->testDiscord($message);
                case 'slack':
                    return $this->testSlack($message);
                case 'mail':
                    return $this->testMail($message);
                default:
                    return response()->json(['error' => 'Unknown channel'], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test Discord webhook.
     */
    protected function testDiscord(string $message): JsonResponse
    {
        $webhookUrl = config('services.discord.webhook_url') ?? 
                      config('logging.channels.discord.url');

        if (empty($webhookUrl)) {
            return response()->json([
                'success' => false,
                'channel' => 'discord',
                'error' => 'Discord webhook URL not configured',
            ], 400);
        }

        $response = \Illuminate\Support\Facades\Http::post($webhookUrl, [
            'content' => $message,
            'embeds' => [
                [
                    'title' => '🧪 Test Notification',
                    'description' => $message,
                    'color' => 3447003, // Blue
                    'footer' => [
                        'text' => 'CDX-Agent • ' . config('app.name'),
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ]);

        return response()->json([
            'success' => $response->successful(),
            'channel' => 'discord',
            'status_code' => $response->status(),
        ]);
    }

    /**
     * Test Slack webhook.
     */
    protected function testSlack(string $message): JsonResponse
    {
        $webhookUrl = config('services.slack.webhook_url') ?? 
                      config('logging.channels.slack.url');

        if (empty($webhookUrl)) {
            return response()->json([
                'success' => false,
                'channel' => 'slack',
                'error' => 'Slack webhook URL not configured',
            ], 400);
        }

        $response = \Illuminate\Support\Facades\Http::post($webhookUrl, [
            'text' => $message,
            'blocks' => [
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => "*🧪 Test Notification*\n{$message}",
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => 'CDX-Agent • ' . config('app.name'),
                        ],
                    ],
                ],
            ],
        ]);

        return response()->json([
            'success' => $response->successful(),
            'channel' => 'slack',
            'status_code' => $response->status(),
        ]);
    }

    /**
     * Test mail notification.
     */
    protected function testMail(string $message): JsonResponse
    {
        $to = config('mail.from.address');

        if (empty($to)) {
            return response()->json([
                'success' => false,
                'channel' => 'mail',
                'error' => 'Mail from address not configured',
            ], 400);
        }

        try {
            \Illuminate\Support\Facades\Mail::raw($message, function ($mail) use ($to) {
                $mail->to($to)
                    ->subject('🧪 CDX-Agent Test Notification');
            });

            return response()->json([
                'success' => true,
                'channel' => 'mail',
                'sent_to' => $to,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'channel' => 'mail',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
