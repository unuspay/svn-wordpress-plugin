<?php

declare(strict_types=1);

namespace UnusPay\WooCommerce;

use WC_Admin_Settings;
use WC_Payment_Gateway;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unuspay WooCommerce Payment Gateway.
 *
 * Provides a hosted checkout redirect flow for crypto payments.
 *
 * @package UnusPay\WooCommerce
 */
class WcGatewayUnuspay extends WC_Payment_Gateway
{
    /**
     * API key for authenticating with the Unuspay API.
     */
    public string $api_key = '';

    /**
     * Webhook secret for verifying incoming webhook signatures.
     */
    public string $webhook_secret = '';

    /**
     * Wallet set ID to associate with checkout sessions.
     */
    public string $wallet_set_id = '';

    /**
     * Persistent WordPress site identifier.
     */
    public string $wp_site_id = '';

    /**
     * Base URL for the Unuspay API.
     */
    public string $api_base_url = '';

    public function __construct()
    {
        $this->id = 'unuspay';
        $this->icon = plugins_url('assets/images/unuspay-mark.svg', UNUSPAY_PAYMENTS_PLUGIN_FILE);
        $this->has_fields = false;
        $this->method_title = __('Unuspay Payments', 'unuspay-payments');
        $this->method_description = __('Accept crypto payments via Unuspay hosted checkout.', 'unuspay-payments');

        // Load form fields and settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user-facing title from settings.
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Assign configured credentials.
        $this->api_key = (string) $this->get_option('api_key', '');
        $this->webhook_secret = (string) $this->get_option('webhook_secret', '');
        $this->wallet_set_id = (string) $this->get_option('wallet_set_id', '');
        $this->api_base_url = (string) $this->get_option('api_base_url', unuspay_get_default_api_base_url());
        $this->wp_site_id = (string) $this->get_option('wp_site_id', '');

        if ($this->wp_site_id === '') {
            $this->wp_site_id = unuspay_get_site_id();
            $this->settings['wp_site_id'] = $this->wp_site_id;
            update_option($this->get_option_key(), $this->settings, true);
        }

        // Show USDC / USDT token icons in classic checkout (replace auto-rendered icon).
        add_filter('woocommerce_gateway_icon', function ($icon, $id) {
            if ($id !== 'unuspay') return $icon;

            $usdc = plugins_url('assets/images/usdc.svg', UNUSPAY_PAYMENTS_PLUGIN_FILE);
            $usdt = plugins_url('assets/images/usdt.svg', UNUSPAY_PAYMENTS_PLUGIN_FILE);

            return '<img src="' . esc_url($usdc) . '" class="unuspay-token-icon" alt="USDC" style="max-height:24px;width:auto;margin-left:4px;vertical-align:middle;" />'
                . '<img src="' . esc_url($usdt) . '" class="unuspay-token-icon" alt="USDT" style="max-height:24px;width:auto;margin-left:4px;vertical-align:middle;" />';
        }, 10, 2);

        // Save settings hook (WooCommerce admin).
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options_with_connect']);

        // Thank-you page fallback: poll session status if order is still pending.
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page_poll']);
    }

    /**
     * Define admin-facing configuration form fields.
     */
    public function init_form_fields(): void
    {
        $status_note = $this->get_connection_status_note();

        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'unuspay-payments'),
                'type' => 'checkbox',
                'label' => __('Enable Unuspay Payments', 'unuspay-payments'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'unuspay-payments'),
                'type' => 'text',
                'description' => __('Payment method title shown to customers at checkout.', 'unuspay-payments'),
                'default' => __('Pay with Crypto - Unuspay', 'unuspay-payments'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'unuspay-payments'),
                'type' => 'textarea',
                'description' => __('Payment method description shown to customers at checkout.', 'unuspay-payments'),
                'default' => __('Pay securely with cryptocurrency via Unuspay.', 'unuspay-payments'),
                'desc_tip' => true,
            ],
            'api_key' => [
                'title' => __('API Key', 'unuspay-payments'),
                'type' => 'password',
                'description' => sprintf(
                    /* translators: %s: connection status note */
                    __('Your Unuspay API key for authenticating requests. %s', 'unuspay-payments'),
                    $status_note,
                ),
                'default' => '',
                'desc_tip' => true,
            ],
            'wp_site_id' => [
                'title' => __('Site ID', 'unuspay-payments'),
                'type' => 'text',
                'description' => __('Persistent site identifier derived from WordPress configuration.', 'unuspay-payments'),
                'default' => $this->wp_site_id !== '' ? $this->wp_site_id : unuspay_get_site_id(),
                'desc_tip' => true,
                'custom_attributes' => [
                    'readonly' => 'readonly',
                ],
            ],
        ];

        if ($this->should_show_api_base_url_field()) {
            $this->form_fields['api_base_url'] = [
                'title' => __('API Base URL', 'unuspay-payments'),
                'type' => 'text',
                'description' => __('Base URL for the Unuspay API (e.g., https://api.unuspay.com).', 'unuspay-payments'),
                'default' => unuspay_get_default_api_base_url(),
                'desc_tip' => true,
            ];
        }
    }

    /**
     * Save settings and provision WooCommerce connection credentials.
     */
    public function process_admin_options_with_connect(): bool
    {
        $previous_settings = get_option($this->get_option_key(), []);
        $previous_settings = is_array($previous_settings) ? $previous_settings : [];
        $previous_api_key = (string) ($previous_settings['api_key'] ?? '');
        $previous_webhook_secret = (string) ($previous_settings['webhook_secret'] ?? '');
        $previous_wallet_set_id = (string) ($previous_settings['wallet_set_id'] ?? '');

        $saved = $this->process_admin_options();

        $this->init_settings();
        $this->api_key = (string) $this->get_option('api_key', '');
        $this->webhook_secret = (string) $this->get_option('webhook_secret', '');
        $this->wallet_set_id = (string) $this->get_option('wallet_set_id', '');
        $this->api_base_url = (string) $this->get_option('api_base_url', unuspay_get_default_api_base_url());

        if ($this->api_key === '') {
            $this->persist_connection_state('', '', '', 'not_configured');
            return $saved;
        }

        $site_id = function_exists('unuspay_get_site_id') ? unuspay_get_site_id() : '';

        if ($site_id === '') {
            $site_id = $this->get_option('wp_site_id');
        }

        if ($site_id === '') {
            WC_Admin_Settings::add_error(__('Unuspay: Failed to determine WordPress site ID.', 'unuspay-payments'));
            $this->restore_failed_connection_state(
                '',
                $previous_api_key,
                $previous_webhook_secret,
                $previous_wallet_set_id,
            );
            return false;
        }

        $this->wp_site_id = $site_id;

        $store_url = function_exists('home_url') ? (string) home_url('/') : '';
        $store_name = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
        $plugin_version = defined('UNUSPAY_PAYMENTS_VERSION') ? (string) UNUSPAY_PAYMENTS_VERSION : '1.0.0';

        $api_client = new ApiClient($this->api_key, $this->api_base_url);
        $connection = $api_client->connect($site_id, $store_url, $store_name, $plugin_version);

        if ($connection === null) {
            WC_Admin_Settings::add_error(__('Unuspay: Failed to connect this WooCommerce store. Please verify the API key and try again.', 'unuspay-payments'));
            $this->restore_failed_connection_state(
                $site_id,
                $previous_api_key,
                $previous_webhook_secret,
                $previous_wallet_set_id,
            );
            return false;
        }

        $this->persist_connection_state(
            $site_id,
            (string) ($connection['webhook_secret'] ?? ''),
            (string) ($connection['wallet_set_id'] ?? ''),
            'connected',
        );

        return $saved;
    }

    /**
     * Process the payment: create a checkout session and redirect.
     *
     * @param int $order_id WooCommerce order ID
     * @return array<string, mixed> Payment result
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(__('Order not found.', 'unuspay-payments'), 'error');
            return ['result' => 'failure', 'redirect' => ''];
        }

        // Build line items from order.
        $line_items = $this->build_line_items($order);

        // Compute merchant return URLs for the hosted checkout page.
        $metadata = [];
        $cancel_url = $order->get_checkout_payment_url(false);
        $success_url = $this->get_return_url($order);

        if (is_string($cancel_url) && $cancel_url !== '') {
            $metadata['cancel_url'] = $cancel_url;
        }
        if (is_string($success_url) && $success_url !== '') {
            $metadata['success_url'] = $success_url;
        }

        // Create API client and checkout session.
        $api_client = new ApiClient($this->api_key, $this->api_base_url);
        $currency = $order->get_currency();

        $session = $api_client->create_checkout_session(
            (string) $order_id,
            $line_items,
            $currency,
            $this->wallet_set_id,
            $metadata,
        );

        if ($session === null) {
            wc_add_notice(__('Failed to create payment session. Please try again.', 'unuspay-payments'), 'error');
            $order->add_order_note(__('Unuspay: Failed to create checkout session.', 'unuspay-payments'));
            return ['result' => 'failure', 'redirect' => ''];
        }

        // Store session metadata on the order.
        $order->update_meta_data('_unuspay_session_id', $session['session_id']);
        $order->update_meta_data('_unuspay_amount_paid', $session['amount_total']);
        $order->save();

        // Mark order as pending payment (awaiting crypto checkout),
        // but only if it is not already in pending status to avoid
        // triggering redundant status-transition hooks.
        if (!$order->has_status('pending')) {
            $order->update_status('pending', __('Awaiting Unuspay payment.', 'unuspay-payments'));
        }

        $order->add_order_note(sprintf(
            __('Unuspay: Checkout session created (ID: %s).', 'unuspay-payments'),
            $session['session_id'],
        ));

        return [
            'result' => 'success',
            'redirect' => $session['checkout_url'],
        ];
    }

    /**
     * Poll the Unuspay API for session status on the thank-you page.
     *
     * If the order is still pending and a session ID exists, this polls the
     * API once to check if the session has completed. This serves as a fallback
     * when the webhook delivery is delayed or has not yet arrived.
     *
     * @param int $order_id WooCommerce order ID
     */
    public function thankyou_page_poll(int $order_id): void
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Only poll if the order is still pending payment.
        if (!$order->has_status('pending')) {
            return;
        }

        $session_id = $order->get_meta('_unuspay_session_id');
        if ($session_id === '') {
            return;
        }

        $api_client = new ApiClient($this->api_key, $this->api_base_url);
        $session = $api_client->get_checkout_session($session_id);

        if ($session === null) {
            $this->log('warning', 'Thank-you page poll: failed to retrieve session', [
                'order_id' => $order_id,
                'session_id' => $session_id,
            ]);
            return;
        }

        $status = $session['status'] ?? '';

        if ($status === 'complete') {
            $transaction_id = $session['session_id'] ?? '';
            $order->set_transaction_id($transaction_id);
            $order->payment_complete($transaction_id);
            $order->add_order_note(sprintf(
                /* translators: %s: Unuspay session ID */
                __('Unuspay: Payment completed via thank-you page poll (Session: %s).', 'unuspay-payments'),
                $transaction_id,
            ));
            $order->save();
        } elseif ($status === 'expired' || $status === 'failed') {
            $reason = $status === 'expired'
                ? __('Checkout session expired.', 'unuspay-payments')
                : __('Checkout session failed.', 'unuspay-payments');
            $order->update_status('cancelled', $reason);
            $order->add_order_note(sprintf(
                /* translators: %s: reason for cancellation */
                __('Unuspay: %s', 'unuspay-payments'),
                $reason,
            ));
        }
    }

    /**
     * Build line items array from a WooCommerce order.
     *
     * Maps order items, shipping, fees, tax, and discounts to the Unuspay
     * checkout session line_items format so the sum matches the customer-
     * facing order total as closely as possible.
     *
     * @param \WC_Order $order WooCommerce order
     * @return array<array{name: string, unit_amount: string, quantity: int}> Line items
     */
    private function build_line_items(\WC_Order $order): array
    {
        $line_items = [];
        $prices_include_tax = function_exists('wc_prices_include_tax') && wc_prices_include_tax();

        // Product items — use after-discount per-unit pricing so the line item
        // totals already account for any coupons / discounts applied to the order.
        // get_item_total() returns the per-unit price AFTER discount, matching
        // what the customer actually pays.
        foreach ($order->get_items() as $item) {
            /** @var \WC_Order_Item_Product $item */
            $unit_price = $order->get_item_total($item, $prices_include_tax, false);

            $line_items[] = [
                'name' => $item->get_name(),
                'unit_amount' => number_format((float) $unit_price, 8, '.', ''),
                'quantity' => $item->get_quantity(),
            ];
        }

        // Add shipping as a line item if present.
        foreach ($order->get_shipping_methods() as $shipping_item) {
            /** @var \WC_Order_Item_Shipping $shipping_item */
            $shipping_total = (float) $shipping_item->get_total();

            if ($prices_include_tax) {
                $shipping_total += (float) $shipping_item->get_total_tax();
            }

            if ($shipping_total > 0) {
                $line_items[] = [
                    'name' => $shipping_item->get_method_title() ?: __('Shipping', 'unuspay-payments'),
                    'unit_amount' => number_format($shipping_total, 8, '.', ''),
                    'quantity' => 1,
                ];
            }
        }

        // Add fees as line items (e.g., payment gateway surcharges).
        foreach ($order->get_fees() as $fee) {
            /** @var \WC_Order_Item_Fee $fee */
            $fee_total = (float) $fee->get_total();

            if ($prices_include_tax) {
                $fee_total += (float) $fee->get_total_tax();
            }

            if ($fee_total > 0) {
                $line_items[] = [
                    'name' => $fee->get_name() ?: __('Fee', 'unuspay-payments'),
                    'unit_amount' => number_format($fee_total, 8, '.', ''),
                    'quantity' => 1,
                ];
            }
        }

        // Tax as a separate line item — only when prices are displayed
        // excluding tax (otherwise tax is already embedded in item prices).
        if (!$prices_include_tax) {
            $total_tax = (float) $order->get_total_tax();

            if ($total_tax > 0) {
                $line_items[] = [
                    'name' => __('Tax', 'unuspay-payments'),
                    'unit_amount' => number_format($total_tax, 8, '.', ''),
                    'quantity' => 1,
                ];
            }
        }

        // Discounts are already applied via get_item_total() above, which
        // returns per-unit prices after discount. No separate negative line
        // item is needed — sending negative amounts would be rejected by the
        // Unuspay API.

        return $line_items;
    }

    /**
     * Persist local connection-related settings and instance properties.
     */
    private function persist_connection_state(
        string $wp_site_id,
        string $webhook_secret,
        string $wallet_set_id,
        string $connection_status,
    ): void {
        $this->wp_site_id = $wp_site_id;
        $this->webhook_secret = $webhook_secret;
        $this->wallet_set_id = $wallet_set_id;

        $this->settings['wp_site_id'] = $wp_site_id;
        $this->settings['webhook_secret'] = $webhook_secret;
        $this->settings['wallet_set_id'] = $wallet_set_id;
        $this->settings['connection_status'] = $connection_status;

        update_option($this->get_option_key(), $this->settings, true);
    }

    /**
     * Restore stable settings after a failed connect attempt.
     */
    private function restore_failed_connection_state(
        string $wp_site_id,
        string $api_key,
        string $webhook_secret,
        string $wallet_set_id,
    ): void {
        $this->api_key = $api_key;
        $this->settings['api_key'] = $api_key;
        $this->persist_connection_state($wp_site_id, $webhook_secret, $wallet_set_id, 'disconnected');
    }

    /**
     * Return a small connection status note for settings UI.
     */
    private function get_connection_status_note(): string
    {
        $status = $this->get_option('connection_status', 'not_configured');

        return match ($status) {
            'connected' => __('Status: connected.', 'unuspay-payments'),
            'disconnected' => __('Status: disconnected.', 'unuspay-payments'),
            default => __('Status: not configured.', 'unuspay-payments'),
        };
    }

    /**
     * Only show API base URL override in development-like environments.
     */
    private function should_show_api_base_url_field(): bool
    {
        return (defined('UNUSPAY_PAYMENTS_DEV_MODE') && UNUSPAY_PAYMENTS_DEV_MODE)
            || (defined('WP_DEBUG') && WP_DEBUG)
            || (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE !== 'production');
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
            $logger->log($level, $message, array_merge(['source' => 'unuspay-gateway'], $context));
        }
    }
}
