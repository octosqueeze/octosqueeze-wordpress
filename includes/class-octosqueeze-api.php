<?php
/**
 * OctoSqueeze API client for WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class OctoSqueeze_API {

    protected $api_key;
    protected $endpoint = 'https://api.octosqueeze.com/v1';

    public function __construct($api_key = null) {
        if ($api_key) {
            $this->api_key = $api_key;
        } else {
            $options = get_option('octosqueeze_settings', []);
            $this->api_key = $options['api_key'] ?? '';
        }
    }

    public function set_endpoint($endpoint) {
        $this->endpoint = rtrim($endpoint, '/');
    }

    /**
     * Compress image from file path
     */
    public function compress_file($file_path, $options = []) {
        if (!file_exists($file_path)) {
            return ['state' => false, 'error' => 'File not found'];
        }

        $settings = get_option('octosqueeze_settings', []);

        $body = [
            'file' => new CURLFile($file_path),
            'mode' => $options['mode'] ?? $settings['mode'] ?? 'balanced',
            'formats' => json_encode($options['formats'] ?? $settings['formats'] ?? ['webp']),
        ];

        return $this->request('POST', '/compress', $body, true);
    }

    /**
     * Compress image from URL
     */
    public function compress_url($url, $options = []) {
        $settings = get_option('octosqueeze_settings', []);

        $body = [
            'url' => $url,
            'mode' => $options['mode'] ?? $settings['mode'] ?? 'balanced',
            'formats' => $options['formats'] ?? $settings['formats'] ?? ['webp'],
        ];

        return $this->request('POST', '/compress', $body);
    }

    /**
     * Batch compress URLs
     */
    public function compress_batch($items, $options = []) {
        $settings = get_option('octosqueeze_settings', []);

        $body = [
            'items' => $items,
            'options' => array_merge([
                'mode' => $settings['mode'] ?? 'balanced',
                'formats' => $settings['formats'] ?? ['webp'],
            ], $options),
        ];

        return $this->request('POST', '/compress-batch', $body);
    }

    /**
     * Get usage statistics
     */
    public function get_usage() {
        return $this->request('GET', '/usage');
    }

    /**
     * Download compressed image
     */
    public function download($url) {
        $response = wp_remote_get($url, [
            'headers' => $this->get_headers(),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        return wp_remote_retrieve_body($response);
    }

    /**
     * Make API request
     */
    protected function request($method, $endpoint, $body = null, $multipart = false) {
        $url = $this->endpoint . $endpoint;

        $args = [
            'method' => $method,
            'timeout' => 60,
            'headers' => $this->get_headers($multipart),
        ];

        if ($body !== null) {
            if ($multipart) {
                // For file uploads, we need to use a different approach
                $args['body'] = $body;
            } else {
                $args['body'] = json_encode($body);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'state' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code >= 400) {
            return [
                'state' => false,
                'error' => $body['error']['message'] ?? 'Request failed',
                'code' => $status_code,
            ];
        }

        return [
            'state' => true,
            'data' => $body['data'] ?? $body,
        ];
    }

    /**
     * Get request headers
     */
    protected function get_headers($multipart = false) {
        $headers = [
            'Authorization' => 'Bearer ' . $this->api_key,
            'Accept' => 'application/json',
        ];

        if (!$multipart) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }
}
