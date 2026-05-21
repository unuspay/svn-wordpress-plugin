<?php
/**
 * Asset file for unuspay-blocks.js
 *
 * Declares the script dependencies and version used by WordPress
 * when enqueuing the Blocks payment-method frontend script.
 *
 * Generated manually — no build step required for this simple registration script.
 *
 * @package UnusPay\WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

return array(
    'dependencies' => array(
        'wc-blocks-registry',
        'wc-settings',
        'wp-element',
        'wp-html-entities',
    ),
    'version'      => '1.0.0',
);
