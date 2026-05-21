<?php

declare(strict_types=1);

namespace UnusPay\SDK;


if (!defined('ABSPATH')) {
    exit;
}
/**
 * Exception thrown when webhook signature verification fails.
 *
 * @package UnusPay\SDK
 */
class WebhookVerificationError extends \Exception
{
    /**
     * Error code identifying the verification failure reason.
     *
     * @var string 'INVALID_SIGNATURE' | 'TIMESTAMP_EXPIRED' | 'MISSING_HEADERS'
     */
    public string $errorCode;

    /**
     * @param string $message Human-readable error message
     * @param string $errorCode One of: INVALID_SIGNATURE, TIMESTAMP_EXPIRED, MISSING_HEADERS
     */
    public function __construct(string $message, string $errorCode)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }
}
