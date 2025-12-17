<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Tests;

use Codix\CdxAgent\Services\SignatureService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HmacSignatureTest extends TestCase
{
    private string $secret = 'test-secret-key-for-hmac-signature';

    #[Test]
    public function it_generates_valid_signature(): void
    {
        $service = new SignatureService($this->secret, 60);

        $timestamp = '1702800000';
        $method = 'GET';
        $path = '/cdx-agent/health';
        $body = '';

        $signature = $service->generateSignature($timestamp, $method, $path, $body);

        $this->assertNotEmpty($signature);
        $this->assertEquals(64, strlen($signature)); // SHA256 hex = 64 chars
    }

    #[Test]
    public function it_verifies_valid_signature(): void
    {
        $service = new SignatureService($this->secret, 60);

        $timestamp = '1702800000';
        $method = 'POST';
        $path = '/cdx-agent/maintenance';
        $body = '{"enabled":true}';

        $signature = $service->generateSignature($timestamp, $method, $path, $body);

        $isValid = $service->verifySignature($signature, $timestamp, $method, $path, $body);

        $this->assertTrue($isValid);
    }

    #[Test]
    public function it_rejects_invalid_signature(): void
    {
        $service = new SignatureService($this->secret, 60);

        $timestamp = '1702800000';
        $method = 'POST';
        $path = '/cdx-agent/maintenance';
        $body = '{"enabled":true}';

        $invalidSignature = 'invalid-signature-that-should-fail';

        $isValid = $service->verifySignature($invalidSignature, $timestamp, $method, $path, $body);

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_rejects_tampered_body(): void
    {
        $service = new SignatureService($this->secret, 60);

        $timestamp = '1702800000';
        $method = 'POST';
        $path = '/cdx-agent/maintenance';
        $originalBody = '{"enabled":true}';
        $tamperedBody = '{"enabled":false}';

        $signature = $service->generateSignature($timestamp, $method, $path, $originalBody);

        $isValid = $service->verifySignature($signature, $timestamp, $method, $path, $tamperedBody);

        $this->assertFalse($isValid);
    }

    #[Test]
    public function it_validates_timestamp_within_tolerance(): void
    {
        $service = new SignatureService($this->secret, 60);

        // Timestamp within tolerance (now)
        $validTimestamp = (string) time();
        $this->assertTrue($service->isTimestampValid($validTimestamp));

        // Timestamp 30 seconds ago (within 60s tolerance)
        $validTimestamp30s = (string) (time() - 30);
        $this->assertTrue($service->isTimestampValid($validTimestamp30s));
    }

    #[Test]
    public function it_rejects_expired_timestamp(): void
    {
        $service = new SignatureService($this->secret, 60);

        // Timestamp 120 seconds ago (outside 60s tolerance)
        $expiredTimestamp = (string) (time() - 120);

        $this->assertFalse($service->isTimestampValid($expiredTimestamp));
    }

    #[Test]
    public function it_rejects_future_timestamp(): void
    {
        $service = new SignatureService($this->secret, 60);

        // Timestamp 120 seconds in the future (outside 60s tolerance)
        $futureTimestamp = (string) (time() + 120);

        $this->assertFalse($service->isTimestampValid($futureTimestamp));
    }

    #[Test]
    public function signature_is_case_insensitive_for_method(): void
    {
        $service = new SignatureService($this->secret, 60);

        $timestamp = '1702800000';
        $path = '/cdx-agent/health';
        $body = '';

        $signatureLower = $service->generateSignature($timestamp, 'get', $path, $body);
        $signatureUpper = $service->generateSignature($timestamp, 'GET', $path, $body);

        $this->assertEquals($signatureLower, $signatureUpper);
    }

    #[Test]
    public function different_secrets_produce_different_signatures(): void
    {
        $service1 = new SignatureService('secret-1', 60);
        $service2 = new SignatureService('secret-2', 60);

        $timestamp = '1702800000';
        $method = 'GET';
        $path = '/cdx-agent/health';
        $body = '';

        $signature1 = $service1->generateSignature($timestamp, $method, $path, $body);
        $signature2 = $service2->generateSignature($timestamp, $method, $path, $body);

        $this->assertNotEquals($signature1, $signature2);
    }

    #[Test]
    public function golden_test_signature_generation(): void
    {
        // This is a golden test to ensure signature generation remains consistent
        $service = new SignatureService('cdx-ops-shared-secret', 60);

        $timestamp = '1702800000';
        $method = 'GET';
        $path = '/cdx-agent/health';
        $body = '';

        $signature = $service->generateSignature($timestamp, $method, $path, $body);

        // Pre-calculated expected signature
        // Payload: "1702800000\nGET\n/cdx-agent/health\n<sha256 of empty string>"
        // Body SHA256: e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
        $expectedSignature = 'e259b10f62f2d00ddc6e7103c76064bfb414d0d69cf6f6d0a4098fa3b6a79a57';

        $this->assertEquals($expectedSignature, $signature);
    }
}
