<?php
/**
 * Plugin Name: Unuspay Crypto Payments for WooCommerce
 * Plugin URI: https://unuspay.com
 * Description: Accept cryptocurrency payments in WooCommerce through Unuspay hosted checkout. Support for 1000+ cryptocurrencies.
 * Version: 1.1.1
 * Author: Unuspay
 * Author URI: https://unuspay.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: unuspay-payments
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package UnusPay\WooCommerce
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}
// Plugin constants — used by includes that need the main file path.
define('UNUSPAY_PAYMENTS_PLUGIN_FILE', __FILE__);
define('UNUSPAY_PAYMENTS_PLUGIN_DIR', __DIR__);
define('UNUSPAY_PAYMENTS_VERSION', '1.1.1');
define('UNUSPAY_PAYMENTS_DEV_MODE', false);

// Add "Settings" link on the plugins page.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    $settings_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=unuspay');
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'unuspay-payments') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

function unuspay_get_default_api_base_url(): string
{
    return 'https://api.unuspay.com';
}

function unuspay_resolve_site_id(): string
{
    $salts = [
        defined('AUTH_KEY')         ? AUTH_KEY         : '',
        defined('SECURE_AUTH_KEY')  ? SECURE_AUTH_KEY  : '',
        defined('LOGGED_IN_KEY')    ? LOGGED_IN_KEY    : '',
        defined('NONCE_KEY')        ? NONCE_KEY        : '',
        defined('AUTH_SALT')        ? AUTH_SALT        : '',
        defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : '',
        defined('LOGGED_IN_SALT')   ? LOGGED_IN_SALT   : '',
        defined('NONCE_SALT')       ? NONCE_SALT       : '',
    ];

    $non_empty = array_filter($salts, function (string $s): bool {
        return $s !== '';
    });

    // Fallback: if all salts are empty, use URL hash
    if (count($non_empty) === 0) {
        return hash('sha256', function_exists('home_url') ? home_url('/') : '');
    }

    return hash('sha256', implode('|', $salts));
}

function unuspay_get_site_id(): string
{
    $deterministic = unuspay_resolve_site_id();
    $cached = get_option('unuspay_site_id', '');

    if ($cached !== $deterministic && $deterministic !== '') {
        update_option('unuspay_site_id', $deterministic, true);
    }

    return $deterministic;
}

// Load Composer autoloader (includes the UnusPay SDK).
require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, function (): void {
    unuspay_get_site_id();
});

/**
 * Declare compatibility with WooCommerce features.
 *
 * Must run before WooCommerce initializes so it can skip incompatibility warnings
 * for High-Performance Order Storage (HPOS) and Cart/Checkout Blocks.
 */
add_action('before_woocommerce_init', function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true,
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true,
        );
    }
});

/**
 * Initialize the gateway after WooCommerce is loaded.
 */
add_action('plugins_loaded', function (): void {
    // Activation is the primary initialization path; this lazy fallback covers
    // edge cases where plugin activation does not complete normally.
    unuspay_get_site_id();

    if (!class_exists('WooCommerce')) {
        return;
    }

    require_once __DIR__ . '/includes/ApiClient.php';
    require_once __DIR__ . '/includes/WebhookHandler.php';
    require_once __DIR__ . '/includes/WcGatewayUnuspay.php';
});

/**
 * Register the gateway with WooCommerce.
 */
add_filter('woocommerce_payment_gateways', function (array $gateways): array {
    $gateways[] = 'UnusPay\\WooCommerce\\WcGatewayUnuspay';
    return $gateways;
});

/**
 * Register the webhook endpoint.
 *
 * WooCommerce routes requests to /wc-api/unuspay_webhook/ to this callback.
 * The handler is instantiated with the stored webhook secret from gateway settings.
 */
add_action('woocommerce_api_unuspay_webhook', function (): void {
    $settings = get_option('woocommerce_unuspay_settings', []);
    $webhook_secret = $settings['webhook_secret'] ?? '';

    if ($webhook_secret === '') {
        status_header(500);
        exit;
    }

    $handler = new \UnusPay\WooCommerce\WebhookHandler($webhook_secret);
    $handler->handle();
});

/**
 * Register the Blocks integration with WooCommerce.
 *
 * Guarded so the plugin remains functional on installs that do not have
 * the WooCommerce Blocks package loaded.
 */
add_action('woocommerce_blocks_payment_method_type_registration', function (
    \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $registry,
): void {
    require_once __DIR__ . '/includes/BlocksIntegration.php';
    $registry->register(new \UnusPay\WooCommerce\BlocksIntegration());
});
