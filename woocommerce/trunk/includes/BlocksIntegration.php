<?php

declare(strict_types=1);

namespace UnusPay\WooCommerce;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Blocks payment-method integration for Unuspay.
 *
 * Registers the Unuspay gateway with the Checkout Block editor so that
 * the payment method appears when the merchant uses a block-based checkout.
 *
 * @package UnusPay\WooCommerce
 */
class BlocksIntegration extends AbstractPaymentMethodType
{
    /**
     * User-facing payment method title (from gateway settings).
     */
    private string $title = '';

    /**
     * User-facing payment method description (from gateway settings).
     */
    private string $description = '';

    /**
     * Whether the gateway is enabled in WooCommerce settings.
     */
    private bool $is_enabled = false;

    /**
     * Payment-method features this gateway supports.
     */
    private array $supports = [];

    /**
     * Initialize the integration — called once by the Blocks API.
     */
    public function initialize(): void
    {
        $this->name = 'unuspay';
        $this->settings = get_option('woocommerce_unuspay_settings', []);
        $this->title = $this->settings['title'] ?? __('Pay with Crypto (Unuspay)', 'unuspay-payments');
        $this->description = $this->settings['description'] ?? __('Pay securely with cryptocurrency via Unuspay.', 'unuspay-payments');
        $this->is_enabled = ($this->settings['enabled'] ?? 'no') === 'yes';

        // Read supported features from the gateway (if available).
        $gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : [];
        $this->supports = $gateways['unuspay']->supports ?? ['products'];
    }

    /**
     * Whether this payment method should be available in the frontend.
     */
    public function is_active(): bool
    {
        return $this->is_enabled;
    }

    /**
     * Return the registered script handle for the payment method frontend.
     *
     * @return string[]
     */
    public function get_payment_method_script_handles(): array
    {
        $script_path = 'assets/js/unuspay-blocks.js';
        $script_url = plugins_url($script_path, UNUSPAY_PAYMENTS_PLUGIN_FILE);
        $script_asset_path = UNUSPAY_PAYMENTS_PLUGIN_DIR . '/assets/js/unuspay-blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require $script_asset_path
            : [
                'dependencies' => ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
                'version' => '1.0.0',
            ];

        wp_register_script(
            'unuspay-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true,
        );

        wp_set_script_translations('unuspay-blocks', 'unuspay-payments');

        return ['unuspay-blocks'];
    }

    /**
     * Return payment-method data exposed to the Blocks frontend.
     *
     * @return array<string, mixed>
     */
    public function get_payment_method_data(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'supports' => $this->supports,
            'token_icons' => [
                'usdc' => plugins_url('assets/images/usdc.svg', UNUSPAY_PAYMENTS_PLUGIN_FILE),
                'usdt' => plugins_url('assets/images/usdt.svg', UNUSPAY_PAYMENTS_PLUGIN_FILE),
            ],
        ];
    }
}
