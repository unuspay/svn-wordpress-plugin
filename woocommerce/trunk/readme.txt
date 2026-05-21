=== Unuspay Crypto Payments for WooCommerce ===
Contributors: unuspay
Tags: woocommerce, payment gateway, crypto, cryptocurrency, bitcoin, usdt
Requires Plugins: woocommerce
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept cryptocurrency payments in WooCommerce using Unuspay hosted checkout.

== Description ==

Unuspay Crypto Payments for WooCommerce lets merchants send WooCommerce customers to a hosted Unuspay checkout to complete cryptocurrency payments.

Features:

* Hosted checkout redirect flow
* WooCommerce checkout integration
* WooCommerce Blocks checkout support
* HPOS compatibility declaration
* Webhook-based order updates
* Support for Bitcoin, stablecoins, and 1000+ cryptocurrencies

== Compatibility ==

This plugin requires WooCommerce and is intended for stores using the standard WooCommerce payment gateway APIs.

Compatibility details for this release:

* WooCommerce classic checkout: supported
* WooCommerce Checkout Block: supported
* High-Performance Order Storage (HPOS): compatibility declared

== External services ==

This plugin connects to Unuspay services to create payment sessions and receive payment-status updates.

It sends:

* Order reference / external order ID
* Order totals and currency
* Order line items
* Callback or return URLs required for checkout completion

Service endpoints used by the plugin include:

* `https://api.unuspay.com`

This plugin depends on the Unuspay external service for payment processing. By using this plugin, you agree to Unuspay's terms and privacy policy.

Policies:

* Privacy Policy: https://unuspay.com/privacy-policy/
* Terms of Use: https://unuspay.com/terms-of-use/

== Installation ==

1. Upload the plugin zip and activate it.
2. Make sure WooCommerce is installed and active.
3. Go to WooCommerce > Settings > Payments > Unuspay.
4. Enter your API key, webhook secret, wallet set ID, and API base URL.
5. Enable the gateway and place a test order.

== Frequently Asked Questions ==

= Does this plugin require a Unuspay account? =

Yes. You need active Unuspay credentials to create hosted checkout sessions and receive webhook updates.

= Does this plugin support the WooCommerce Checkout Block? =

Yes. The plugin includes a Checkout Block integration.

= Does this plugin process payments on my WordPress site directly? =

No. Customers are redirected to Unuspay hosted checkout to complete payment.

= What cryptocurrencies are supported? =

Unuspay supports Bitcoin, stablecoins (USDT, USDC), and 1000+ cryptocurrencies through its hosted checkout.

= What are the fees? =

Unuspay charges a flat 1% transaction fee with no setup or subscription charges.

== Screenshots ==

1. Unuspay payment gateway settings in WooCommerce.
2. Unuspay shown as a checkout payment option.
3. Hosted checkout or order-confirmation flow after redirect.

== Changelog ==

= 1.0.0 =
* First public WordPress.org-ready submission.
* Hosted checkout redirect flow
* WooCommerce Blocks checkout support
* HPOS compatibility declared

== Upgrade Notice ==

= 1.0.0 =
Initial public WordPress.org-ready release.
