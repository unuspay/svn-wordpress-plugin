<?php

declare(strict_types=1);

namespace UnusPay\SDK;


if (!defined('ABSPATH')) {
    exit;
}
/**
 * Webhook signature verification class.
 *
 * Verifies HMAC-SHA256 signatures on webhook payloads to ensure
 * authenticity and integrity of incoming webhook events.
 *
 * @package UnusPay\SDK
 */
class Webhook
{
    /**
     * Webhook secret used for HMAC signature verification.
     */
    private string $secret;

    /**
     * Configuration options for verification.
     */
    private WebhookConfig $config;

    /**
     * Create a new Webhook verifier.
     *
     * @param string $secret Your webhook secret (starts with whsec_)
     * @param WebhookConfig|null $config Optional configuration
     * @throws WebhookVerificationError If secret is empty
     */
    public function __construct(string $secret, ?WebhookConfig $config = null)
    {
        if ($secret === '') {
            throw new WebhookVerificationError(
                'Invalid secret: Secret must be a non-empty string',
                'MISSING_HEADERS'
            );
        }

        $this->secret = $secret;
        $this->config = $config ?? new WebhookConfig();
    }

    /**
     * Verify a webhook signature and return the parsed payload.
     *
     * @param string $payload Raw request body as string (NOT parsed JSON)
     * @param string $signature Value of X-Webhook-Signature header
     * @param string|int $timestamp Value of X-Webhook-Timestamp header (unix timestamp)
     * @return array<string, mixed> Parsed event data
     * @throws WebhookVerificationError If verification fails
     *
     * @example
     * ```php
     * $webhook = new Webhook('whsec_your_secret_here');
     *
     * // In your webhook handler
     * $event = $webhook->verify(
     *     $request->getBody(),
     *     $request->getHeader('X-Webhook-Signature'),
     *     $request->getHeader('X-Webhook-Timestamp')
     * );
     *
     * echo "Received event: {$event['type']}\n";
     * ```
     */
    public function verify(string $payload, string $signature, string|int $timestamp): array
    {
        // Validate payload
        if ($payload === '') {
            throw new WebhookVerificationError(
                'Invalid payload: Payload must be a non-empty string',
                'MISSING_HEADERS'
            );
        }

        // Validate signature
        if ($signature === '') {
            throw new WebhookVerificationError(
                'Invalid signature: Signature must be a non-empty string',
                'MISSING_HEADERS'
            );
        }

        // Parse timestamp to integer
        $ts = is_string($timestamp) ? filter_var($timestamp, FILTER_VALIDATE_INT) : $timestamp;
        if ($ts === false || $ts === null) {
            throw new WebhookVerificationError(
                'Invalid timestamp: must be a valid unix timestamp',
                'MISSING_HEADERS'
            );
        }

        // Check timestamp freshness (replay protection)
        $now = time();
        if ($now - $ts > $this->config->maxAgeSeconds) {
            throw new WebhookVerificationError(
                'Timestamp expired: event is ' . ($now - $ts) . ' seconds old (max: ' . $this->config->maxAgeSeconds . ')',
                'TIMESTAMP_EXPIRED'
            );
        }

        // Compute expected signature: HMAC-SHA256(ts.payload, secret)
        $signedPayload = "{$ts}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->secret);

        // Constant-time comparison to prevent timing attacks
        if (!hash_equals($expectedSignature, $signature)) {
            throw new WebhookVerificationError(
                'Invalid signature: the signature does not match the payload',
                'INVALID_SIGNATURE'
            );
        }

        // Parse and return JSON payload
        $event = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WebhookVerificationError(
                'Invalid payload: could not parse JSON',
                'INVALID_SIGNATURE'
            );
        }

        return $event;
    }
}
