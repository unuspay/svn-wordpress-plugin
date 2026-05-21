<?php

declare(strict_types=1);

namespace UnusPay\SDK;


if (!defined('ABSPATH')) {
    exit;
}
/**
 * Configuration options for the Webhook verifier.
 *
 * @package UnusPay\SDK
 */
final class WebhookConfig
{
    /**
     * Max age in seconds for timestamp validation.
     * Events older than this will be rejected.
     *
     * @var int Default: 300 (5 minutes)
     */
    public int $maxAgeSeconds;

    /**
     * @param int $maxAgeSeconds Max allowed age in seconds (must be > 0)
     * @throws \InvalidArgumentException If maxAgeSeconds is not positive
     */
    public function __construct(int $maxAgeSeconds = 300)
    {
        if ($maxAgeSeconds <= 0) {
            throw new \InvalidArgumentException(
                'maxAgeSeconds must be a positive integer'
            );
        }

        $this->maxAgeSeconds = $maxAgeSeconds;
    }
}
