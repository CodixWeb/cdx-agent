<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Services;

class SignatureService
{
    public function __construct(
        protected string $secret,
        protected int $timestampTolerance = 60
    ) {}

    /**
     * Generate HMAC signature for a request.
     *
     * Signature format: HMAC_SHA256(timestamp + "\n" + method + "\n" + path + "\n" + bodySha256, secret)
     */
    public function generateSignature(
        string $timestamp,
        string $method,
        string $path,
        string $body = ''
    ): string {
        $bodySha256 = hash('sha256', $body);
        $payload = $this->buildPayload($timestamp, $method, $path, $bodySha256);

        return hash_hmac('sha256', $payload, $this->secret);
    }

    /**
     * Verify HMAC signature from a request.
     */
    public function verifySignature(
        string $signature,
        string $timestamp,
        string $method,
        string $path,
        string $body = ''
    ): bool {
        $expectedSignature = $this->generateSignature($timestamp, $method, $path, $body);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check if the timestamp is within the allowed tolerance.
     */
    public function isTimestampValid(string $timestamp): bool
    {
        $requestTime = (int) $timestamp;
        $currentTime = time();

        return abs($currentTime - $requestTime) <= $this->timestampTolerance;
    }

    /**
     * Build the signature payload string.
     */
    protected function buildPayload(
        string $timestamp,
        string $method,
        string $path,
        string $bodySha256
    ): string {
        return implode("\n", [
            $timestamp,
            strtoupper($method),
            $path,
            $bodySha256,
        ]);
    }

    /**
     * Get the secret (useful for debugging, should not be exposed).
     */
    public function hasSecret(): bool
    {
        return !empty($this->secret);
    }
}
