<?php

/**
 * Plugin Name: UnusPay Crypto Payments For Easy Digital Downloads
 * Plugin URI: https://unuspay.com/e-commerce
 * Description: unuspay Payments directly into your own wallet.
 * Author: unuspay
 * Author URI: https://unuspay.com
 * Text Domain: unuspay-crypto-payments-for-easy-digital-downloads
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: easy-digital-downloads
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * Version: 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define gateway name
define("UNUSPAY_GATEWAY_NAME", "edd_unuspay_gateway");

// Registering Unuspay Gateway as a Payment Gateway in EDD
function unuspay_edd_register_gateway($gateways)
{
    $gateways[UNUSPAY_GATEWAY_NAME] = array(
        'admin_label' => 'Unuspay Gateway',
        'checkout_label' => esc_html__('Unuspay Crypto Payment Gateway', 'unuspay-crypto-payments-for-easy-digital-downloads'),
    );
    return $gateways;
}

register_activation_hook(__FILE__, 'setup_plugin');
function setup_plugin()
{
    global $wpdb;
    $latestDbVersion = 5;
    $currentDbVersion = get_option('unuspay_edd_db_version');

    if (!empty($currentDbVersion) && $currentDbVersion >= $latestDbVersion) {
        return;
    }
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta("
		CREATE TABLE  IF NOT EXISTS {$wpdb->prefix}edd_unuspay_checkouts (
			id VARCHAR(36) NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			accept LONGTEXT NOT NULL,
			created_at datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
			PRIMARY KEY  (id)
		);"
    );
    dbDelta("
        CREATE TABLE  IF NOT EXISTS {$wpdb->prefix}edd_unuspay_transactions (
        			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        			checkout_id VARCHAR(36) NOT NULL,
        			tracking_uuid VARCHAR(64) NOT NULL,
        			blockchain TINYTEXT NOT NULL,
        			transaction_id TINYTEXT NOT NULL,
        			sender_id TINYTEXT NOT NULL,
        			receiver_id TINYTEXT NOT NULL,
        			token_id TINYTEXT NOT NULL,
        			amount TINYTEXT NOT NULL,
        			status TINYTEXT NOT NULL,
        			failed_reason TINYTEXT NOT NULL,
        			confirmed_by TINYTEXT NOT NULL,
        			confirmed_at datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
        			created_at datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
        			PRIMARY KEY  (id),
        			KEY tracking_uuid_index (tracking_uuid)
        		);
	");
    update_option('unuspay_edd_db_version', $latestDbVersion);
}

add_filter('edd_payment_gateways', 'unuspay_edd_register_gateway');

// Register a subsection for Unuspay Gateway in gateway options tab
function unuspay_edd_register_gateway_section($gateway_sections)
{
    $gateway_sections[UNUSPAY_GATEWAY_NAME] = esc_html__('Unuspay Gateway', 'unuspay-crypto-payments-for-easy-digital-downloads');
    return $gateway_sections;
}

add_filter('edd_settings_sections_gateways', 'unuspay_edd_register_gateway_section');


$unuspay_edd_title = "";
$unuspay_edd_payment_key = "";

// Register the Unuspay Gateway settings for Unuspay Gateway subsection
function unuspay_edd_add_gateway_settings($gateway_settings)
{
    global $unuspay_edd_title, $unuspay_edd_payment_key;

 
    $unuspay_settings = array(
   
        UNUSPAY_GATEWAY_NAME . '_title' => array(
            'id' => UNUSPAY_GATEWAY_NAME . '_title',
            'name' => esc_html__('Title', 'unuspay-crypto-payments-for-easy-digital-downloads'),
            'desc' => esc_html__('Payment method title that the customer will see on your checkout page', 'unuspay-crypto-payments-for-easy-digital-downloads'),
            'type' => 'text',
            'size' => 'regular',
            'std' => $unuspay_edd_title
        ),
        UNUSPAY_GATEWAY_NAME . '_payment_key' => array(
            'id' => UNUSPAY_GATEWAY_NAME . '_payment_key',
            'name' => esc_html__('PaymentKey', 'unuspay-crypto-payments-for-easy-digital-downloads'),
            'desc' => esc_html__('Unuspay Payment Key', 'unuspay-crypto-payments-for-easy-digital-downloads'),
            'type' => 'text',
            'size' => 'regular',
            'std' => $unuspay_edd_payment_key
        ),

    );

    $unuspay_settings = apply_filters('edd_' . UNUSPAY_GATEWAY_NAME . '_settings', $unuspay_settings);
    $gateway_settings[UNUSPAY_GATEWAY_NAME] = $unuspay_settings;
    return $gateway_settings;
}

add_filter('edd_settings_gateways', 'unuspay_edd_add_gateway_settings');

function unuspay_edd_init_settings()
{
    global $edd_options;

    $unuspay_edd_title = edd_get_option(UNUSPAY_GATEWAY_NAME . '_title', '');
    $unuspay_edd_payment_key = edd_get_option(UNUSPAY_GATEWAY_NAME . '_payment_key', '');

    $arr = array(UNUSPAY_GATEWAY_NAME . '_title', UNUSPAY_GATEWAY_NAME . '_payment_key');


    if (!$unuspay_edd_title) {
        $unuspay_edd_title = esc_html__('Unuspay Crypto Payment Gateway', 'unuspay-crypto-payments-for-easy-digital-downloads');
        edd_update_option(UNUSPAY_GATEWAY_NAME . '_title', $unuspay_edd_title);
    }

    $unuspay_edd_payment_key = trim($unuspay_edd_payment_key);
    edd_update_option(UNUSPAY_GATEWAY_NAME . '_payment_key', $unuspay_edd_payment_key);
 
}

function unuspay_edd_process_payment($purchase_data)
{
    try {
        global $wpdb;
        if (!wp_verify_nonce($purchase_data['gateway_nonce'], 'edd-gateway')) {
            wp_die(esc_html__('Nonce verification has failed', 'unuspay-crypto-payments-for-easy-digital-downloads'), esc_html__('Error', 'unuspay-crypto-payments-for-easy-digital-downloads'), array('response' => 403));
        }

        $payment_data = array(
            "price" => $purchase_data['price'],
            "date" => $purchase_data['date'],
            "user_email" => $purchase_data['user_email'],
            "purchase_key" => $purchase_data['purchase_key'],
            "currency" => edd_get_currency(),
            "downloads" => $purchase_data['downloads'],
            "user_info" => $purchase_data['user_info'],
            "cart_details" => $purchase_data['cart_details'],
            "status" => "pending"
        );

        $payment_id = edd_insert_payment($payment_data);
        if ($payment_id) {

           
            $payment = edd_get_payment($payment_id);
            $accept = getUnusPayOrder($payment);
            
            $result = $wpdb->insert("{$wpdb->prefix}edd_unuspay_checkouts", array(
                'id' => $accept->id,
                'order_id' => $payment_id,
                'accept' => json_encode($accept),
                'created_at' => current_time('mysql')
            ));
            if (false === $result) {
                $error_message = $wpdb->last_error;

                throw new Exception('Storing checkout failed: ' . $error_message);
            }
            $redirect_url = "Location: " . edd_get_checkout_uri() . '#edd-unuspay-checkout-' . $accept->id . '@' . time();
            header($redirect_url);
            die();
            return rest_ensure_response('{}');
           
        }
    } catch (Exception $e) {
        wp_die(esc_html__('Storing checkout failed', 'unuspay-crypto-payments-for-easy-digital-downloads'), esc_html__('Error', 'unuspay-crypto-payments-for-easy-digital-downloads'), array('response' => 403));

      
    }
    
}

function getUnusPayOrder($order)
{
    $lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE'])) : '';
    $headers = array(
        'accept-language' => $lang,
        'Content-Type' => 'application/json; charset=utf-8',
    );
    $website = get_option("siteurl");

    $total = $order->total;
    $currency = $order->currency;

    $payment_key = edd_get_option(UNUSPAY_GATEWAY_NAME . '_payment_key', '');
    if (empty($payment_key)) {
        throw new Exception('No payment key found!');
    }

    $post_response = wp_remote_post("https://dapp.unuspay.com/api/payment/ecommerce/order",
        array(
            'headers' => $headers,
            'body' => json_encode([
                'website' => $website,
                'lang' => $lang,
                'orderNo' => $order->id,
                'email' => $order->email,
                'payLinkId' => $payment_key,
                'currency' => $currency,
                'amount' => $total,
                'commerceType'=>2
            ]),
            'method' => 'POST',
            'data_format' => 'body'
        )
    );
    $post_response_code = $post_response['response']['code'];
    $post_response_successful = !is_wp_error($post_response_code) && $post_response_code == 200 ;
    if (!$post_response_successful) {
        throw new Exception('request failed!');
    }
    $post_response_json = json_decode($post_response['body']);
    if ($post_response_json->code != 200) {
        throw new Exception('request failed!');
    }

    return $post_response_json->data;
}

add_action('edd_gateway_' . UNUSPAY_GATEWAY_NAME, 'unuspay_edd_process_payment');

function unuspay_edd_cryptocoin_payment($payment)
{
    try {


        if (edd_get_payment_gateway($payment->ID) == UNUSPAY_GATEWAY_NAME && is_object($payment)) {
            $status = $payment->status;
            $amount = edd_get_payment_amount($payment->ID);
            $currency = edd_get_payment_currency_code($payment->ID);
            $orderID = $payment->ID;
            $userID = edd_get_payment_user_id($payment->ID);

            if (!$userID) {
                $userID = "guest";
            } elseif ($userID == "-1") {
                $userID = 0;
            }

            if ($status == "complete") {
                return true;
            }

            $unuspay_edd_payment_key = edd_get_option(UNUSPAY_GATEWAY_NAME . '_payment_key', '');

            if (!$payment || !$payment->ID) {
                echo '<h3>' . esc_html(esc_html__('ERROR', 'unuspay-crypto-payments-for-easy-digital-downloads')) . '</h3>' . esc_html(PHP_EOL);
                echo "<p class='edd-alert edd-alert-error'>" . esc_html(esc_html__('Unable to get payment object. You can contact the email(contact@unuspay.com) to get more help.', 'unuspay-crypto-payments-for-easy-digital-downloads')) . '</p>';
                return false;
            } else {
                if ($amount < 0) {
                    echo '<h3>' . esc_html(esc_html__('ERROR', 'unuspay-crypto-payments-for-easy-digital-downloads')) . '</h3>' . esc_html(PHP_EOL);
                    echo "<p class='edd-alert edd-alert-error'>" . esc_html(esc_html__("The order amount must be greater than or equal to 0. Please contact us(contact@unuspay.com) if you need assistance.", 'unuspay-crypto-payments-for-easy-digital-downloads') . esc_html(" ") . esc_html($currency)) . "</p>";
                    return false;
                } elseif (!$unuspay_edd_payment_key || $unuspay_edd_payment_key == "") {
                    echo '<h3>' . esc_html(esc_html__('ERROR', 'unuspay-crypto-payments-for-easy-digital-downloads')) . '</h3>' . esc_html(PHP_EOL);
                    echo "<p class='edd-alert edd-alert-error'>" . esc_html(esc_html__("The merchant did not set the plugin configuration. Please contact merchant or us(contact@unuspay.com) if you need assistance.", 'unuspay-crypto-payments-for-easy-digital-downloads')) . "</p>";
                    return false;
                } else {
                    unuspay_edd_generate_checkout_token($orderID, $amount, $currency);
                    return true;
                }
            }
        }
    } catch (Exception $e) {
    }

    return false;
}

function unuspay_edd_generate_checkout_token($orderID, $amount, $currency_code)
{
    global $wp;
    global $wpdb;
    $checkout_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT checkout_id FROM {$wpdb->prefix}edd_unuspay_checkouts WHERE id = %s LIMIT 1",
            $orderID
        )
    );

    /* return( [
            'result'         => 'success',
            'redirect'       => 'unuspay-checkout-' . $checkout_id . '@' . time()
            // 'redirect'       => get_option('woocommerce_enable_signup_and_login_from_checkout') === 'yes' ? $order->get_checkout_payment_url() . '#wc-depay-checkout-' . $checkout_id . '@' . time() : '#wc-depay-checkout-' . $checkout_id . '@' . time()
        ] );*/
    /* $redirect_url= "Location: ". 'unuspay-checkout-' . $checkout_id . '@' . time();
     header($redirect_url);
     die();*/
    return rest_ensure_response('{}');
    /*$unuspay_edd_merchant_id = edd_get_option(UNUSPAY_GATEWAY_NAME . '_merchant_id', '');
    $unuspay_edd_merchant_key = edd_get_option(UNUSPAY_GATEWAY_NAME . '_merchant_key', '');

    $unuspay_generate_checkout_token = "https://dashboard.unuspay.com/api/order/pay/token";
    $unuspay_checkout_url = "https://dashboard.unuspay.com/#/cashier/choose?token=";

    $platform = "EASYDIGITALDOWNLOADS";
    $callback_url = trim(get_site_url(), "/ ") . "/unuspay.edd.callback.php?status=completed&type=AURPAYEDD&platform=UNUSPAY&order_id=" . $orderID;

    $current_url = home_url(add_query_arg(array(), $wp->request));
    $succeed_url = $current_url;

    $origin = array(
        'id' => $orderID,
        'price' => $amount,
        'currency' => $currency_code,
        'callback_url' => $callback_url,
        'succeed_url' => $succeed_url,
        'url' => trim(get_site_url(), "/ "),
    );

    $data = array(
        'platform' => $platform,
        'origin' => $origin,
        'user_id' => $unuspay_edd_merchant_id,
        'key' => $unuspay_edd_merchant_key
    );

    $token_result = unuspay_edd_http_post($unuspay_generate_checkout_token, json_encode($data), $unuspay_edd_merchant_key);
    $response_data = json_decode($token_result['body'], true);
    if (isset($response_data['data']) && $response_data['code'] == 0 && isset($response_data['data']['token']) && $response_data['data']['token'] != "") {
        $token = $response_data['data']['token'];
        $redirect_url = "Location: " . $unuspay_checkout_url . $token;
        header($redirect_url);
        die();
    } else {
        unuspay_edd_log_error("[unuspay_edd_generate_checkout_token] request to unuspay failed, response_data:" . json_encode($response_data));
    }

    return $response_data;*/
}

function unuspay_edd_http_post($url, $data, $API_KEY)
{
    $body = $data;
    $headers = array(
        'Content-Type' => 'application/json; charset=utf-8',
        'Content-Length' => strlen($data),
        'API-KEY' => $API_KEY,
    );
    $args = array(
        'body' => $body,
        'timeout' => '5',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => $headers,
    );

    $response = wp_remote_post($url, $args);

    if ($response) {
        return $response;
    }
    return [];
}

add_action('edd_order_receipt_before_table', 'unuspay_edd_cryptocoin_payment');


function unuspay_edd_disable_checkout_userInfo_details()
{
    remove_action('edd_after_cc_fields', 'edd_default_cc_address_fields');
    remove_action('edd_cc_form', 'edd_get_cc_form');

    unuspay_edd_init_settings();
}

add_action('init', 'unuspay_edd_disable_checkout_userInfo_details');

function unuspay_edd_payment_icon($icons = array())
{
    $icons[esc_url(plugins_url('assets/images/img_logo_1.png', __FILE__))] = 'Unuspay';

    return $icons;
}

add_filter('edd_accepted_payment_icons', 'unuspay_edd_payment_icon');


function unuspay_edd_plugins_loaded()
{
    if (!function_exists('EDD')) {
        return false;
    }

    $unuspay_edd_payment_key = edd_get_option(UNUSPAY_GATEWAY_NAME . '_payment_key', '');

    if (isset($unuspay_edd_payment_key) && $unuspay_edd_payment_key != "") {
        return false;
    } 
}



add_action('plugins_loaded', 'unuspay_edd_plugins_loaded');


add_action(
    'rest_api_init', 'init_rest_api'
);

function init_rest_api()
{
    register_rest_route(
        'unuspay/edd',
        '/checkouts/(?P<id>[\w-]+)',
        [
            'methods' => 'POST',
            'callback' => 'get_checkout_accept',
            'permission_callback' => '__return_true'
        ]
    );
    register_rest_route(
        'unuspay/edd',
        '/track',
        [
            'methods' => 'POST',
            'callback' => 'track_payment',
            'permission_callback' => '__return_true'
        ]
    );
    register_rest_route(
        'unuspay/edd',
        '/validate',
        array(
            'methods' => 'POST,GET',
            'callback' => 'process_notify',
            'permission_callback' => '__return_true'
        )
    );
    register_rest_route(
        'unuspay/edd',
        '/release',
        [
            'methods' => 'POST',
            'callback' => 'check_release',
            'permission_callback' => '__return_true'
        ]
    );

}

function get_checkout_accept($request)
{

    global $wpdb;
    $id = $request->get_param('id');
    $accept = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT accept FROM {$wpdb->prefix}edd_unuspay_checkouts WHERE id = %s LIMIT 1",
            $id
        )
    );
    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}edd_unuspay_checkouts WHERE id = %s LIMIT 1",
            $id
        )
    );
    $checkout_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}edd_unuspay_checkouts WHERE id = %s LIMIT 1",
            $id
        )
    );
    $order = edd_get_payment($order_id);

    if ($order->status === 'complete') {
        $response = rest_ensure_response(
            json_encode([
                'redirect' => edd_get_success_page_uri()
            ])
        );
    } else {
        $response = rest_ensure_response($accept);
    }

    // $response->header('X-Checkout', json_encode([
    //     'request_id' => $id,
    //     'checkout_id' => $checkout_id,
    //     'order_id' => $order_id,
    //     'total' => $order->total,
    //     'currency' => $order->currency
    // ]));
    return $response;
}

function track_payment($request)
{

    global $wpdb;
    $body = $request->get_body();
    $jsonBody = json_decode($body,true);
     
    $id = $jsonBody["orderId"];
    $accept = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT accept FROM {$wpdb->prefix}edd_unuspay_checkouts WHERE id = %s LIMIT 1",
            $id
        )
    );
    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}edd_unuspay_checkouts WHERE id = %s LIMIT 1",
            $id
        )
    );
    $payment = edd_get_payment($order_id);

    $tracking_uuid ;

    $total = $payment->total;

    $transaction_id = $jsonBody["transaction"];

    if (empty($transaction_id)) { // PAYMENT TRACE

        if ($payment->status=='complete') {
            throw new Exception('Order has been completed already!');
        }


    } else { // PAYMENT TRACKING
        $tracking_uuid = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT tracking_uuid FROM {$wpdb->prefix}edd_unuspay_transactions WHERE checkout_id = %s ORDER BY created_at DESC LIMIT 1",
                $id
            )
        );
        if (empty($tracking_uuid)) {
            $tracking_uuid = wp_generate_uuid4();
            $result = $wpdb->insert("{$wpdb->prefix}edd_unuspay_transactions", array(
                'order_id' => $order_id,
                'checkout_id' => $id,
                'tracking_uuid' => $tracking_uuid,
                'blockchain' => $jsonBody["blockchain"],
                'transaction_id' => $transaction_id,
                'sender_id' => $jsonBody["sender"],
                'receiver_id' => '',
                'token_id' => '',
                'amount' => 0.00,
                'status' => 'VALIDATING',

                'created_at' => current_time('mysql')
            ));
        
            if (false === $result) {
                throw new Exception('Storing tracking failed!!');
            }
        }

    }

    $endpoint = 'https://dapp.unuspay.com/api/payment/pay';

    $jsonBody["callback"] = get_site_url(null, 'wp-json/unuspay/edd/validate');
    $jsonBody["trackingId"] = $tracking_uuid;
    $jsonBody["orderId"] = $id;

    $headers = array(
        'Content-Type' => 'application/json; charset=utf-8',
        'csrf_token' => $id
    );
    $post = wp_remote_post($endpoint,
        array(
            'headers' => $headers,
            'body' => json_encode($jsonBody),
            'method' => 'POST',
            'data_format' => 'body'
        )
    );

    $response = rest_ensure_response(json_decode(wp_remote_retrieve_body($post),true));
    $response->set_status(200);
    return $response;
   
}

function check_release($request)
{

    global $wpdb;
    $body = $request->get_body();
    $jsonBody = json_decode($body,true);

    $checkout_id =  $jsonBody["orderId"];
    $existing_transaction_status = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}edd_unuspay_transactions WHERE checkout_id = %s ORDER BY created_at DESC LIMIT 1",
            $checkout_id
        )
    );

    if ('VALIDATING' === $existing_transaction_status) {
        $tracking_uuid = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT tracking_uuid FROM {$wpdb->prefix}edd_unuspay_transactions WHERE checkout_id = %s ORDER BY created_at DESC LIMIT 1",
                $checkout_id
            )
        );

        $endpoint = 'https://dapp.unuspay.com/api/payment/release';
        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
        );
        $response = wp_remote_post($endpoint,
            array(
                'headers' => $headers,
                'body' => json_encode($jsonBody),
                'method' => 'POST',
                'data_format' => 'body'
            )
        );
        $rspBody = json_decode(wp_remote_retrieve_body($response));
        if (!is_wp_error($response) && (wp_remote_retrieve_response_code($response) == 200) && $rspBody->code == 200) {


            $order_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT order_id FROM {$wpdb->prefix}edd_unuspay_transactions WHERE tracking_uuid = %s ORDER BY id DESC LIMIT 1",
                    $tracking_uuid
                )
            );

            
            $expected_transaction = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT transaction_id FROM {$wpdb->prefix}edd_unuspay_transactions WHERE tracking_uuid = %s ORDER BY id DESC LIMIT 1",
                    $tracking_uuid
                )
            );
            $order = edd_get_payment($order_id);
            //$responseBody = json_decode( $response['body'] );
            $status = $rspBody->data->status;
            $transaction = $rspBody->data->transaction;

            if ($expected_transaction != $transaction) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}edd_unuspay_transactions SET transaction_id = %s WHERE tracking_uuid = %s",
                        $transaction,
                        $tracking_uuid
                    )
                );
            }

            if (
                'success' === $status
            ) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}edd_unuspay_transactions SET status = %s, confirmed_at = %s, confirmed_by = %s, failed_reason = NULL WHERE tracking_uuid = %s",
                        'SUCCESS',
                        current_time('mysql'),
                        'API',
                        $tracking_uuid
                    )
                );
                edd_update_order_status($order_id, 'complete');
            } else if ('failed' === $status) {
                $failed_reason = 'fail';
                if (empty($failed_reason)) {
                    $failed_reason = 'MISMATCH';
                }
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}edd_unuspay_transactions SET failed_reason = %s, status = %s, confirmed_by = %s WHERE tracking_uuid = %s",
                        $failed_reason,
                        'FAILED',
                        'API',
                        $tracking_uuid
                    )
                );
                edd_update_order_status($order_id, 'faild');
            }
        }
    }

    $existing_transaction_status = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}edd_unuspay_transactions WHERE checkout_id = %s ORDER BY created_at DESC LIMIT 1",
            $checkout_id
        )
    );

    if (empty($existing_transaction_status) || 'VALIDATING' === $existing_transaction_status) {
        $response = rest_ensure_response("{}");
        $response->set_status(200);
        return $response;
    }

    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}edd_unuspay_transactions WHERE checkout_id = %s ORDER BY id DESC LIMIT 1",
            $checkout_id
        )
    );
    $order = edd_get_payment($order_id);


    if ('SUCCESS' === $existing_transaction_status) {
        $response = rest_ensure_response([
            'code' => 200,
            'data' => [
                'status' => 'success',
                'forward_to' => edd_get_success_page_uri()
            ]
        ]);
        $response->set_status(200);
        return $response;
    } else {
        $failed_reason = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT failed_reason FROM {$wpdb->prefix}edd_unuspay_transactions WHERE checkout_id = %s ORDER BY id DESC LIMIT 1",
                $checkout_id
            )
        );
        $response = rest_ensure_response([
            'code' => 500,
            'data' => [
                'status' => 'failed'
            ]
        ]);

        $response->set_status(200);
        return $response;
    }
}

function process_notify(WP_REST_Request $request)
{
    global $wpdb;
    $response = new WP_REST_Response();
    $body = $request->get_body();
    $jsonBody = json_decode($body,true);

    $tracking_uuid = $jsonBody['trackingId'];
    $existing_transaction_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}edd_unuspay_transactions WHERE tracking_uuid = %s ORDER BY id DESC LIMIT 1",
            $tracking_uuid
        )
    );

    if (empty($existing_transaction_id)) {
        $response->set_status(404);
        return $response;
    }

    $order_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT order_id FROM {$wpdb->prefix}edd_unuspay_transactions WHERE tracking_uuid = %s ORDER BY id DESC LIMIT 1",
            $tracking_uuid
        )
    );

    $expected_transaction = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT transaction_id FROM {$wpdb->prefix}edd_unuspay_transactions WHERE tracking_uuid = %s ORDER BY id DESC LIMIT 1",
            $tracking_uuid
        )
    );

    $status = $jsonBody['status'];
    $transaction = $jsonBody['transaction'];

    if ($expected_transaction != $transaction) {
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}edd_unuspay_transactions SET transaction_id = %s WHERE tracking_uuid = %s",
                $transaction,
                $tracking_uuid
            )
        );
    }

    if (
        'success' === $status
    ) {
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}edd_unuspay_transactions SET status = %s, confirmed_at = %s, confirmed_by = %s, failed_reason = NULL WHERE tracking_uuid = %s",
                'SUCCESS',
                current_time('mysql'),
                'API',
                $tracking_uuid
            )
        );
        edd_update_order_status($order_id, 'complete');
    } else {
        $failed_reason = $jsonBody['failed_reason'];
        if (empty($failed_reason)) {
            $failed_reason = 'MISMATCH';
        }
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}edd_unuspay_transactions SET failed_reason = %s, status = %s, confirmed_by = %s WHERE tracking_uuid = %s",
                $failed_reason,
                'FAILED',
                'API',
                $tracking_uuid
            )
        );
        edd_update_order_status($order_id, 'failed');
    }

    $response->set_status(200);
    return $response;
}

add_action('wp_enqueue_scripts', 'edd_custom_scripts');

function edd_custom_scripts()
{
    // 仅在 EDD 结账页面加载
    //if (edd_is_checkout()) {
    wp_register_script( 'UNUSPAY_EDD_WIDGETS',plugin_dir_url(__FILE__) .'assets/js/widgets.bundle.js', array(), '1.0', true);
    wp_enqueue_script( 'UNUSPAY_EDD_WIDGETS' );

        // 注册脚本（依赖 jQuery）
        wp_register_script(
            'UNUSPAY_EDD_CHECKOUT',
            plugin_dir_url(__FILE__) . 'assets/js/checkout.js', // 脚本路径
            array('wp-api-request', 'jquery'), // 依赖
            '1.0', // 版本号
            true // 在页脚加载
        );


        // 加载脚本
        wp_enqueue_script('UNUSPAY_EDD_CHECKOUT');
    //}
}

function get_edd_options()
{
    global $edd_options;

    return $edd_options;
}
 