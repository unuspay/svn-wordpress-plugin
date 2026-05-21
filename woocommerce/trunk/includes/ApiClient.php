<?php

declare(strict_types=1);

namespace UnusPay\WooCommerce;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * API Client for communicating with the Unuspay API.
 *
 * Handles checkout session creation via the Unuspay REST API.
 *
 * @package UnusPay\WooCommerce
 */
class ApiClient
{
    private string $api_key;

    private string $api_base_url;

    /**
     * @param string $api_key API key for authentication
     * @param string $api_base_url Base URL for the Unuspay API
     */
    public function __construct(string $api_key, string $api_base_url)
    {
        $this->api_key = $api_key;
        $this->api_base_url = rtrim($api_base_url, '/');
    }

    /**
     * Create a checkout session via the Unuspay API.
     *
     * @param string $order_id External order ID (WooCommerce order ID)
     * @param array<array{name: string, description?: string, unit_amount: string, quantity: int, metadata?: array<string, mixed>}> $line_items Line items for the session
     * @param string $currency Currency code (e.g., 'USD')
     * @param string $wallet_set_id Wallet set ID for the session
     * @param array<string, mixed> $metadata Optional metadata to attach to the session
     * @return array{session_id: string, public_token: string, payment_token: string, checkout_url: string, amount_total: string, currency: string, status: string, expires_at: string}|null Response data or null on failure
     */
    public function create_checkout_session(
        string $order_id,
        array $line_items,
        string $currency,
        string $wallet_set_id,
        array $metadata = [],
    ): ?array {
        $body = [
            'wallet_set_id' => $wallet_set_id,
            'currency' => $currency,
            'external_order_id' => $order_id,
            'line_items' => $line_items,
        ];

        if ($metadata !== []) {
            $body['metadata'] = $metadata;
        }

        $response = $this->request('POST', '/api/v1/checkout_session', $body, $order_id);

        if ($response === null) {
            return null;
        }

        // The API wraps the response in a { status: "success", data: {...} } envelope.
        if (isset($response['status'], $response['data']) && $response['status'] === 'success') {
            return $response['data'];
        }

        // Log unexpected envelope to aid diagnosis of API contract mismatches.
        $this->log('error', 'Unuspay API: unexpected response envelope on create checkout session', [
            'path' => '/api/v1/checkout_session',
            'body' => wp_json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);

        return null;
    }

    /**
     * Retrieve a checkout session by ID.
     *
     * @param string $session_id The checkout session ID
     * @return array{session_id: string, status: string, amount_total: string, currency: string, external_order_id?: string}|null Session data or null on failure
     */
    public function get_checkout_session(string $session_id): ?array
    {
        $response = $this->request('GET', '/api/v1/checkout_session/' . $session_id);

        if ($response === null) {
            return null;
        }

        // The API wraps the response in a { status: "success", data: {...} } envelope.
        if (isset($response['status'], $response['data']) && $response['status'] === 'success') {
            return $response['data'];
        }

        // Log unexpected envelope to aid diagnosis of API contract mismatches.
        $this->log('error', 'Unuspay API: unexpected response envelope on get checkout session', [
            'path' => '/api/v1/checkout_session/' . $session_id,
            'body' => wp_json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);

        return null;
    }

    /**
     * Connect a WooCommerce site and fetch provisioned credentials.
     *
     * @param string $wp_site_id Persistent WordPress site ID
     * @param string $store_url Store URL
     * @param string $store_name Store name
     * @param string $plugin_version Installed plugin version
     * @return array{webhook_secret: string, wallet_set_id: string, webhook_url?: string}|null Provisioned credentials or null on failure
     */
    public function connect(string $wp_site_id, string $store_url, string $store_name, string $plugin_version): ?array
    {
        $response = $this->request_with_meta(
            'POST',
            '/api/v1/wp_connect',
            [
                'wp_site_id' => $wp_site_id,
                'store_url' => $store_url,
                'store_name' => $store_name,
            ],
            $wp_site_id,
            [
                'X-Client-Type' => 'woocommerce',
                'X-Plugin-Version' => $plugin_version,
            ],
        );

        if ($response === null) {
            return null;
        }

        if ($response['status_code'] !== 200) {
            $this->log('error', 'Unuspay API: wp_connect returned non-200 response', [
                'path' => '/api/v1/wp_connect',
                'status' => $response['status_code'],
                'body' => $response['body'],
            ]);
            return null;
        }

        if (!is_array($response['decoded'])) {
            $this->log('error', 'Unuspay API: wp_connect returned invalid JSON', [
                'path' => '/api/v1/wp_connect',
                'body' => $response['body'],
            ]);
            return null;
        }

        $decoded = $response['decoded'];

        if (
            !isset($decoded['status'], $decoded['data'])
            || $decoded['status'] !== 'success'
            || !is_array($decoded['data'])
        ) {
            $this->log('error', 'Unuspay API: wp_connect returned unexpected response envelope', [
                'path' => '/api/v1/wp_connect',
                'body' => wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
            return null;
        }

        if (!isset($decoded['data']['webhook_secret']) || !is_string($decoded['data']['webhook_secret']) || $decoded['data']['webhook_secret'] === '') {
            $this->log('error', 'Unuspay API: wp_connect missing or empty webhook_secret', [
                'path' => '/api/v1/wp_connect',
                'body' => wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
            return null;
        }

        if (!isset($decoded['data']['wallet_set_id']) || !is_string($decoded['data']['wallet_set_id']) || $decoded['data']['wallet_set_id'] === '') {
            $this->log('error', 'Unuspay API: wp_connect missing or empty wallet_set_id', [
                'path' => '/api/v1/wp_connect',
                'body' => wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            ]);
            return null;
        }

        return $decoded['data'];
    }

    /**
     * Disconnect a WooCommerce site from provisioned credentials.
     *
     * @param string $wp_site_id Persistent WordPress site ID
     * @param string $plugin_version Installed plugin version
     */
    public function disconnect(string $wp_site_id, string $plugin_version): bool
    {
        $response = $this->request(
            'DELETE',
            '/api/v1/wp_connect',
            ['wp_site_id' => $wp_site_id],
            null,
            [
                'X-Client-Type' => 'woocommerce',
                'X-Plugin-Version' => $plugin_version,
            ],
        );

        return is_array($response)
            && isset($response['status'], $response['data']['disconnected'])
            && $response['status'] === 'success'
            && $response['data']['disconnected'] === true;
    }

    /**
     * Make an HTTP request to the Unuspay API.
     *
     * @param string $method HTTP method
     * @param string $path API path (e.g., '/api/v1/checkout_session')
     * @param array<string, mixed>|null $body Request body (null for GET requests)
     * @param string|null $idempotency_key Optional idempotency key
     * @param array<string, string> $extra_headers Optional additional headers
     * @return array<string, mixed>|null Decoded response or null on failure
     */
    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $idempotency_key = null,
        array $extra_headers = [],
    ): ?array {
        $response = $this->request_with_meta($method, $path, $body, $idempotency_key, $extra_headers);

        if ($response === null) {
            return null;
        }

        if ($response['status_code'] < 200 || $response['status_code'] >= 300) {
            $decoded_error = json_decode($response['body'], true);
            $context = [
                'path' => $path,
                'status' => $response['status_code'],
                'body' => $response['body'],
            ];
            // Surface structured error fields from the API envelope when present.
            if (
                is_array($decoded_error)
                && isset($decoded_error['error']['code'], $decoded_error['error']['message'])
            ) {
                $context['api_error_code'] = $decoded_error['error']['code'];
                $context['api_error_message'] = $decoded_error['error']['message'];
            }
            $this->log('error', 'Unuspay API returned error', $context);
            return null;
        }

        if (!is_array($response['decoded'])) {
            $this->log('error', 'Unuspay API returned invalid JSON', [
                'path' => $path,
                'body' => $response['body'],
            ]);
            return null;
        }

        return $response['decoded'];
    }

    /**
     * Make an HTTP request and return response metadata.
     *
     * @param string $method HTTP method
     * @param string $path API path (e.g., '/api/v1/checkout_session')
     * @param array<string, mixed>|null $body Request body (null for GET requests)
     * @param string|null $idempotency_key Optional idempotency key
     * @param array<string, string> $extra_headers Optional additional headers
     * @return array{status_code: int, body: string, decoded: array<string, mixed>|null}|null Response metadata or null on failure
     */
    private function request_with_meta(
        string $method,
        string $path,
        ?array $body = null,
        ?string $idempotency_key = null,
        array $extra_headers = [],
    ): ?array {
        $url = $this->api_base_url . $path;

        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        ], $extra_headers);

        if ($idempotency_key !== null) {
            $headers['Idempotency-Key'] = $idempotency_key;
        }

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true,
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log('error', 'Unuspay API request failed', [
                'path' => $path,
                'error' => $response->get_error_message(),
            ]);
            return null;
        }

        $response_body = wp_remote_retrieve_body($response);
        $decoded = json_decode($response_body, true);

        return [
            'status_code' => wp_remote_retrieve_response_code($response),
            'body' => $response_body,
            'decoded' => is_array($decoded) ? $decoded : null,
        ];
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
            $logger->log($level, $message, array_merge(['source' => 'unuspay-api'], $context));
        }
    }
}
