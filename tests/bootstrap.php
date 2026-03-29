<?php

/**
 * Bootstrap for OctoSqueeze WordPress plugin unit tests.
 *
 * Defines stubs for WordPress functions so plugin classes can be loaded
 * and tested without a full WordPress environment.
 */

// Global state used by stubs
global $wp_options, $wp_actions, $wp_filters, $wp_post_meta, $wp_remote_responses;
$wp_options = [];
$wp_actions = [];
$wp_filters = [];
$wp_post_meta = [];
$wp_remote_responses = [];

// ABSPATH must be defined for the plugin files to load
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/fake-wp/');
}

// Plugin constants normally set in octosqueeze.php
if (!defined('OCTOSQUEEZE_VERSION')) {
    define('OCTOSQUEEZE_VERSION', '1.0.0');
}
if (!defined('OCTOSQUEEZE_PLUGIN_DIR')) {
    define('OCTOSQUEEZE_PLUGIN_DIR', dirname(__DIR__) . '/');
}
if (!defined('OCTOSQUEEZE_PLUGIN_URL')) {
    define('OCTOSQUEEZE_PLUGIN_URL', 'https://example.com/wp-content/plugins/octosqueeze/');
}
if (!defined('OCTOSQUEEZE_PLUGIN_BASENAME')) {
    define('OCTOSQUEEZE_PLUGIN_BASENAME', 'octosqueeze/octosqueeze.php');
}

// ─── Options API ─────────────────────────────────────────────────────────────

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        global $wp_options;
        return array_key_exists($key, $wp_options) ? $wp_options[$key] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null) {
        global $wp_options;
        $wp_options[$key] = $value;
        return true;
    }
}

if (!function_exists('add_option')) {
    function add_option($key, $value = '', $deprecated = '', $autoload = 'yes') {
        global $wp_options;
        if (!array_key_exists($key, $wp_options)) {
            $wp_options[$key] = $value;
        }
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($key) {
        global $wp_options;
        unset($wp_options[$key]);
        return true;
    }
}

// ─── Hooks API ───────────────────────────────────────────────────────────────

if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_actions;
        $wp_actions[$tag][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {
        global $wp_filters;
        $wp_filters[$tag][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action($tag, $callback, $priority = 10) {
        global $wp_actions;
        unset($wp_actions[$tag]);
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter($tag, $callback, $priority = 10) {
        global $wp_filters;
        unset($wp_filters[$tag]);
        return true;
    }
}

// ─── HTTP API ────────────────────────────────────────────────────────────────

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = []) {
        global $wp_remote_responses;
        if (isset($wp_remote_responses['error'])) {
            return new WP_Error_Stub('http_request_failed', $wp_remote_responses['error']);
        }
        return $wp_remote_responses['response'] ?? [
            'response' => ['code' => 200],
            'body' => json_encode(['data' => []]),
        ];
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        return wp_remote_request($url, array_merge($args, ['method' => 'POST']));
    }
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = []) {
        return wp_remote_request($url, array_merge($args, ['method' => 'GET']));
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['body'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_wp_error($response)) {
            return '';
        }
        return $response['response']['code'] ?? 200;
    }
}

// ─── WP_Error stub ──────────────────────────────────────────────────────────

if (!class_exists('WP_Error_Stub')) {
    class WP_Error_Stub {
        protected $code;
        protected $message;
        protected $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message($code = '') {
            return $this->message;
        }

        public function get_error_data($code = '') {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error_Stub;
    }
}

// ─── Sanitization / Escaping ─────────────────────────────────────────────────

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags((string) $str));
    }
}

if (!function_exists('sanitize_url')) {
    function sanitize_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

// ─── Nonce / Auth ────────────────────────────────────────────────────────────

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        return '<input type="hidden" name="' . $name . '" value="stub_nonce" />';
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return 1; // always valid in tests
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return 'stub_nonce';
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $stop = true) {
        return 1;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

// ─── i18n ────────────────────────────────────────────────────────────────────

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('sprintf')) {
    // sprintf is a PHP built-in, no stub needed
}

// ─── Plugin / Path utilities ─────────────────────────────────────────────────

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return trailingslashit(dirname($file));
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('trailingslashit')) {
    function trailingslashit($string) {
        return rtrim($string, '/\\') . '/';
    }
}

// ─── Admin utilities ─────────────────────────────────────────────────────────

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('is_admin')) {
    function is_admin() {
        return true;
    }
}

if (!function_exists('add_options_page')) {
    function add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback) {
        return 'settings_page_' . $menu_slug;
    }
}

if (!function_exists('add_media_page')) {
    function add_media_page($page_title, $menu_title, $capability, $menu_slug, $callback) {
        return 'media_page_' . $menu_slug;
    }
}

if (!function_exists('register_setting')) {
    function register_setting($option_group, $option_name, $args = []) {
        return true;
    }
}

if (!function_exists('add_settings_section')) {
    function add_settings_section($id, $title, $callback, $page) {
        return true;
    }
}

if (!function_exists('add_settings_field')) {
    function add_settings_field($id, $title, $callback, $page, $section = '') {
        return true;
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group) {
        // no-op
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page) {
        // no-op
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null) {
        // no-op
    }
}

if (!function_exists('get_admin_page_title')) {
    function get_admin_page_title() {
        return 'OctoSqueeze Settings';
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        $result = ($selected == $current) ? ' selected="selected"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ($checked == $current) ? ' checked="checked"' : '';
        if ($echo) echo $result;
        return $result;
    }
}

// ─── Post meta ───────────────────────────────────────────────────────────────

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        global $wp_post_meta;
        if ($key === '') {
            return $wp_post_meta[$post_id] ?? [];
        }
        $value = $wp_post_meta[$post_id][$key] ?? null;
        if ($single) {
            return $value;
        }
        return $value !== null ? [$value] : [];
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $key, $value) {
        global $wp_post_meta;
        $wp_post_meta[$post_id][$key] = $value;
        return true;
    }
}

// ─── Attachment / Media ──────────────────────────────────────────────────────

if (!function_exists('get_attached_file')) {
    function get_attached_file($attachment_id) {
        global $wp_post_meta;
        return $wp_post_meta[$attachment_id]['_wp_attached_file'] ?? false;
    }
}

if (!function_exists('wp_get_attachment_image_url')) {
    function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail') {
        return 'https://example.com/wp-content/uploads/image.jpg';
    }
}

if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $decimals) . ' ' . $units[$pow];
    }
}

// ─── Cron ────────────────────────────────────────────────────────────────────

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) {
        return false;
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook, $args = []) {
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook) {
        return 0;
    }
}

// ─── Script / Style enqueue ─────────────────────────────────────────────────

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $args = []) {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $data) {
        return true;
    }
}

// ─── AJAX responses ──────────────────────────────────────────────────────────

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        // In tests we throw so we can catch and inspect the response
        throw new \OctoSqueeze\WordPress\Tests\WpJsonResponse(true, $data);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        throw new \OctoSqueeze\WordPress\Tests\WpJsonResponse(false, $data);
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '') {
        throw new \RuntimeException('wp_die: ' . $message);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => '/tmp/fake-wp/wp-content/uploads',
            'baseurl' => 'https://example.com/wp-content/uploads',
            'path' => '/tmp/fake-wp/wp-content/uploads/' . date('Y/m'),
            'url' => 'https://example.com/wp-content/uploads/' . date('Y/m'),
        ];
    }
}

if (!function_exists('current_time')) {
    function current_time($type) {
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return time();
    }
}

// ─── Activation hooks ────────────────────────────────────────────────────────

if (!function_exists('register_activation_hook')) {
    function register_activation_hook($file, $callback) {
        // no-op
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook($file, $callback) {
        // no-op
    }
}

// ─── WP_Query stub ──────────────────────────────────────────────────────────

if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = [];
        public $found_posts = 0;

        public function __construct($args = []) {
            $this->posts = [];
            $this->found_posts = 0;
        }
    }
}

// ─── wpdb stub ───────────────────────────────────────────────────────────────

if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta';

        public $last_insert = [];
        public $last_update = [];
        public $last_query = '';
        public $query_results = [];

        public function prepare($query, ...$args) {
            $this->last_query = $query;
            return vsprintf(str_replace(['%d', '%s'], ["'%d'", "'%s'"], $query), $args);
        }

        public function insert($table, $data) {
            $this->last_insert = ['table' => $table, 'data' => $data];
            return 1;
        }

        public function update($table, $data, $where) {
            $this->last_update = ['table' => $table, 'data' => $data, 'where' => $where];
            return 1;
        }

        public function get_var($query = null) {
            return $this->query_results['get_var'] ?? null;
        }

        public function get_row($query = null) {
            return $this->query_results['get_row'] ?? null;
        }

        public function get_results($query = null, $output = OBJECT) {
            return $this->query_results['get_results'] ?? [];
        }

        public function get_charset_collate() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }

        public function esc_like($text) {
            return addcslashes($text, '_%\\');
        }
    }
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// Set up global $wpdb
global $wpdb;
$wpdb = new wpdb();

// ─── WpJsonResponse exception for testing AJAX handlers ─────────────────────

require_once __DIR__ . '/WpJsonResponse.php';

// ─── Load plugin classes ─────────────────────────────────────────────────────

require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-api.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-settings.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-compressor.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-bulk.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze.php';
