<?php

declare(strict_types=1);

namespace UnusPay\WooCommerce;

use UnusPay\SDK\Webhook;
use UnusPay\SDK\WebhookVerificationError;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook handler for processing Unuspay event notifications.
 *
 * Receives webhook events from Unuspay, verifies signatures using the PHP SDK,
 * performs deduplication via WordPress transients, and dispatches events to
 * the appropriate order-status processing methods.
 *
 * Registered on the `woocommerce_api_unuspay_webhook` endpoint.
 *
 * @package UnusPay\WooCommerce
 */
class WebhookHandler
{
    /**
     * Webhook secret for verifying incoming webhook signatures.
     */
    private string $webhook_secret;

    /**
     * @param string $webhook_secret Secret used for HMAC signature verification
     */
    public function __construct(string $webhook_secret)
    {
        $this->webhook_secret = $webhook_secret;
    }

    /**
     * Handle an incoming webhook request.
     *
     * Reads the raw body, signature/timestamp/id headers, verifies via SDK,
     * deduplicates, then dispatches the event for order processing.
     *
     * Responds with 200 on success, or an appropriate error status.
     */
    public function handle(): void
    {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
        $timestamp = $_SERVER['HTTP_X_WEBHOOK_TIMESTAMP'] ?? '';

        // Validate required headers are present.
        if ($payload === '' || $signature === '' || $timestamp === '') {
            status_header(400);
            exit;
        }

        // Verify the webhook signature using the PHP SDK.
        try {
            $webhook = new Webhook($this->webhook_secret);
            $event = $webhook->verify($payload, $signature, $timestamp);
        } catch (WebhookVerificationError $e) {
            $this->log('warning', 'Webhook verification failed', [
                'error' => $e->getMessage(),
                'code' => $e->errorCode,
            ]);
            status_header(401);
            exit;
        }

        // Validate the event has a webhook ID for deduplication.
        $event_id = $_SERVER['HTTP_X_WEBHOOK_ID'] ?? '';
        if ($event_id === '') {
            status_header(400);
            exit;
        }

        // Deduplication: skip if we have already processed this event within 24h.
        if (get_transient("unuspay_wh_{$event_id}") !== false) {
            status_header(200);
            exit;
        }
        set_transient("unuspay_wh_{$event_id}", true, DAY_IN_SECONDS);

        // Dispatch event for order status processing.
        $this->process_event($event);

        status_header(200);
        exit;
    }

    /**
     * Check if an order is already in a terminal state.
     *
     * Used to prevent duplicate status transitions and order notes.
     *
     * @param \WC_Order $order WooCommerce order
     * @return bool True if order is already processing or completed
     */
    private function is_order_already_processed(\WC_Order $order): bool
    {
        return $order->has_status(['processing', 'completed']);
    }

    /**
     * Process a verified webhook event.
     *
     * Routes the event by type and updates the corresponding WooCommerce order.
     *
     * @param array<string, mixed> $event Verified and parsed webhook event data
     */
    private function process_event(array $event): void
    {
        $event_type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        // Extract the external order ID from the event object.
        $external_order_id = $object['external_order_id'] ?? '';
        if ($external_order_id === '') {
            $this->log('warning', 'Webhook event missing external_order_id', [
                'type' => $event_type,
            ]);
            return;
        }

        $order = wc_get_order((int) $external_order_id);
        if (!$order) {
            $this->log('warning', 'Webhook event references unknown order', [
                'type' => $event_type,
                'order_id' => $external_order_id,
            ]);
            return;
        }

        switch ($event_type) {
            case 'transaction.confirmed':
                $this->handle_transaction_confirmed($order, $object);
                break;

            case 'order.completed':
                $this->handle_order_completed($order, $object);
                break;

            case 'checkout.session.completed':
                $this->handle_checkout_session_completed($order, $object);
                break;

            case 'checkout.session.expired':
            case 'checkout.session.failed':
                $this->handle_checkout_session_failed($order, $object, $event_type);
                break;

            default:
                $this->log('info', 'Unhandled webhook event type', [
                    'type' => $event_type,
                    'order_id' => $external_order_id,
                ]);
                break;
        }
    }

    /**
     * Handle a checkout.session.completed event.
     *
     * Marks the order as paid and records the transaction reference.
     *
     * Note: set_transaction_id() is called before the idempotency guard because
     * a late-arriving webhook should update the stored tx_id even if the order is
     * already paid.
     *
     * @param \WC_Order $order WooCommerce order
     * @param array<string, mixed> $object Event payload object
     */
    private function handle_checkout_session_completed(\WC_Order $order, array $object): void
    {
        $session_id = $object['session_id'] ?? '';
        $transaction_hash = $object['transaction_hash'] ?? '';

        $transaction_id = $transaction_hash !== '' ? $transaction_hash : $session_id;

        if ($transaction_id !== '') {
            $order->set_transaction_id($transaction_id);
        }

        if ($this->is_order_already_processed($order)) {
            $this->log('info', 'Order already processed', [
                'order_id' => $order->get_id(),
            ]);
            return;
        }

        $order->payment_complete($transaction_id);
        $order->add_order_note(sprintf(
            /* translators: %s: Unuspay session ID */
            __('Unuspay: Payment completed (Session: %s).', 'unuspay-payments'),
            $session_id,
        ));
        $order->save();

        $this->log('info', 'Order payment completed via webhook', [
            'order_id' => $order->get_id(),
            'session_id' => $session_id,
        ]);
    }

    /**
     * Handle a transaction.confirmed event.
     *
     * Marks the order as paid and records the on-chain transaction hash.
     *
     * Note: set_transaction_id() is called before the idempotency guard because
     * a late-arriving webhook should update the stored tx_id even if the order is
     * already paid.
     *
     * @param \WC_Order $order WooCommerce order
     * @param array<string, mixed> $object Event payload object
     */
    private function handle_transaction_confirmed(\WC_Order $order, array $object): void
    {
        $tx_hash = $object['tx_hash'] ?? '';

        if ($tx_hash !== '') {
            $order->set_transaction_id($tx_hash);
        }

        if ($this->is_order_already_processed($order)) {
            $this->log('info', 'Order already processed', [
                'order_id' => $order->get_id(),
            ]);
            return;
        }

        $order->payment_complete($tx_hash);
        $order->add_order_note(sprintf(
            /* translators: %s: blockchain transaction hash */
            __('Unuspay: Payment confirmed (Tx: %s).', 'unuspay-payments'),
            $tx_hash !== '' ? $tx_hash : __('unknown', 'unuspay-payments'),
        ));
        $order->save();

        $this->log('info', 'Order payment confirmed via transaction webhook', [
            'order_id' => $order->get_id(),
            'tx_hash' => $tx_hash,
        ]);
    }

    /**
     * Handle an order.completed event.
     *
     * Marks the order as paid.
     *
     * @param \WC_Order $order WooCommerce order
     * @param array<string, mixed> $object Event payload object
     */
    private function handle_order_completed(\WC_Order $order, array $object): void
    {
        if ($this->is_order_already_processed($order)) {
            $this->log('info', 'Order already processed', [
                'order_id' => $order->get_id(),
            ]);
            return;
        }

        $order->payment_complete();
        $order->add_order_note(
            __('Unuspay: Order completed.', 'unuspay-payments')
        );
        $order->save();

        $this->log('info', 'Order completed via webhook', [
            'order_id' => $order->get_id(),
        ]);
    }

    /**
     * Handle a checkout.session.expired or checkout.session.failed event.
     *
     * Cancels the order and records the reason.
     *
     * @param \WC_Order $order WooCommerce order
     * @param array<string, mixed> $object Event payload object
     * @param string $event_type The event type that triggered this handler
     */
    private function handle_checkout_session_failed(\WC_Order $order, array $object, string $event_type): void
    {
        $reason = $event_type === 'checkout.session.expired'
            ? __('Checkout session expired.', 'unuspay-payments')
            : __('Checkout session failed.', 'unuspay-payments');

        $order->update_status('cancelled', $reason);

        $order->add_order_note(sprintf(
            /* translators: %s: reason for cancellation */
            __('Unuspay: %s', 'unuspay-payments'),
            $reason,
        ));

        $this->log('info', 'Order cancelled via webhook', [
            'order_id' => $order->get_id(),
            'event_type' => $event_type,
        ]);
    }

    /**
     * Log a message using WooCommerce logger if available.
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message, array_merge(['source' => 'unuspay-webhook'], $context));
        }
    }
}
