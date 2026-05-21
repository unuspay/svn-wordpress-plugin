<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('woocommerce_unuspay_settings', []);
$api_key = is_array($settings) ? (string) ($settings['api_key'] ?? '') : '';
$wp_site_id = is_array($settings) ? (string) ($settings['wp_site_id'] ?? '') : '';
$default_api_base_url = 'https://api.unuspay.com';
$plugin_version = '1.0.0';
$api_base_url = is_array($settings) ? (string) ($settings['api_base_url'] ?? $default_api_base_url) : $default_api_base_url;

if ($api_key !== '' && $wp_site_id !== '') {
    require_once __DIR__ . '/includes/ApiClient.php';

    $client = new \UnusPay\WooCommerce\ApiClient($api_key, $api_base_url);
    $client->disconnect($wp_site_id, $plugin_version);
}

delete_option('woocommerce_unuspay_settings');
delete_option('unuspay_site_id');
