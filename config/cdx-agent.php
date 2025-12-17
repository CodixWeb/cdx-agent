<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CDX Agent Shared Secret
    |--------------------------------------------------------------------------
    |
    | This is the shared secret used to authenticate requests from cdx-ops.
    | It should be a long, random string that matches the one configured
    | in the cdx-ops control center for this site.
    |
    */
    'secret' => env('CDX_AGENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Timestamp Tolerance
    |--------------------------------------------------------------------------
    |
    | The maximum allowed difference (in seconds) between the request timestamp
    | and the server time. This helps prevent replay attacks.
    |
    */
    'timestamp_tolerance' => env('CDX_AGENT_TIMESTAMP_TOLERANCE', 60),

    /*
    |--------------------------------------------------------------------------
    | Log Failed Attempts
    |--------------------------------------------------------------------------
    |
    | Whether to log failed authentication attempts. Useful for debugging
    | and security monitoring.
    |
    */
    'log_failed_attempts' => env('CDX_AGENT_LOG_FAILED_ATTEMPTS', true),

    /*
    |--------------------------------------------------------------------------
    | Route Prefix
    |--------------------------------------------------------------------------
    |
    | The URL prefix for all CDX Agent routes.
    |
    */
    'route_prefix' => env('CDX_AGENT_ROUTE_PREFIX', 'cdx-agent'),

    /*
    |--------------------------------------------------------------------------
    | Route Middleware
    |--------------------------------------------------------------------------
    |
    | Additional middleware to apply to CDX Agent routes.
    |
    */
    'middleware' => [
        'api',
        \Codix\CdxAgent\Http\Middleware\VerifyHmacSignature::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Maximum number of requests per minute from the control center.
    |
    */
    'rate_limit' => env('CDX_AGENT_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Enabled Features
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific agent features.
    |
    */
    'features' => [
        'health' => true,
        'maintenance' => true,
        'cache_clear' => true,
        'git_info' => env('CDX_AGENT_GIT_INFO', true),
        'queue_info' => env('CDX_AGENT_QUEUE_INFO', true),
    ],

];
