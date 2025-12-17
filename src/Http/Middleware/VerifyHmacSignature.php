<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Middleware;

use Closure;
use Codix\CdxAgent\Services\SignatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyHmacSignature
{
    public function __construct(
        protected SignatureService $signatureService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-CDX-Timestamp');
        $signature = $request->header('X-CDX-Signature');

        // Check if required headers are present
        if (!$timestamp || !$signature) {
            $this->logFailedAttempt($request, 'Missing authentication headers');
            return $this->errorResponse('Missing authentication headers', 401);
        }

        // Check if secret is configured
        if (!$this->signatureService->hasSecret()) {
            $this->logFailedAttempt($request, 'Agent secret not configured');
            return $this->errorResponse('Agent not configured', 500);
        }

        // Verify timestamp is within tolerance (anti-replay)
        if (!$this->signatureService->isTimestampValid($timestamp)) {
            $this->logFailedAttempt($request, 'Request timestamp expired or invalid');
            return $this->errorResponse('Request expired', 401);
        }

        // Verify signature
        $path = '/' . ltrim($request->path(), '/');
        $isValid = $this->signatureService->verifySignature(
            $signature,
            $timestamp,
            $request->method(),
            $path,
            $request->getContent()
        );

        if (!$isValid) {
            $this->logFailedAttempt($request, 'Invalid signature');
            return $this->errorResponse('Invalid signature', 401);
        }

        return $next($request);
    }

    /**
     * Log a failed authentication attempt.
     */
    protected function logFailedAttempt(Request $request, string $reason): void
    {
        if (!config('cdx-agent.log_failed_attempts', true)) {
            return;
        }

        Log::warning('CDX Agent: Authentication failed', [
            'reason' => $reason,
            'ip' => $request->ip(),
            'path' => $request->path(),
            'method' => $request->method(),
            'timestamp_header' => $request->header('X-CDX-Timestamp'),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Return a standardized JSON error response.
     */
    protected function errorResponse(string $message, int $status): Response
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'error' => $message,
        ], $status);
    }
}
