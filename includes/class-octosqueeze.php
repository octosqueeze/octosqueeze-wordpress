<?php
/**
 * Main OctoSqueeze plugin class
 */

if (!defined('ABSPATH')) {
    exit;
}

class OctoSqueeze {

    protected $settings;
    protected $compressor;
    protected $bulk;

    public function __construct() {
        $this->settings = new OctoSqueeze_Settings();
        $this->compressor = new OctoSqueeze_Compressor();
        $this->bulk = new OctoSqueeze_Bulk();
    }

    public function run() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this->settings, 'add_menu']);
            add_action('admin_menu', [$this, 'add_bulk_menu']);
            add_action('admin_init', [$this->settings, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // Register bulk AJAX handlers
            $this->bulk->register_ajax_handlers();

            // Add settings link to plugins page
            add_filter('plugin_action_links_' . OCTOSQUEEZE_PLUGIN_BASENAME, [$this, 'add_settings_link']);
        }

        // Image upload hooks
        $options = get_option('octosqueeze_settings', []);

        if (!empty($options['compress_on_upload']) && !empty($options['api_key'])) {
            add_action('add_attachment', [$this->compressor, 'process_attachment']);
        }

        // Schedule background processing
        if (!wp_next_scheduled('octosqueeze_process_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'octosqueeze_process_queue');
        }
        add_action('octosqueeze_process_queue', [$this->compressor, 'process_queue']);

        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Media library column
        add_filter('manage_media_columns', [$this, 'add_media_column']);
        add_action('manage_media_custom_column', [$this, 'render_media_column'], 10, 2);

        // AJAX handlers
        add_action('wp_ajax_octosqueeze_compress', [$this->compressor, 'ajax_compress']);
        add_action('wp_ajax_octosqueeze_stats', [$this, 'ajax_stats']);
    }

    public function add_cron_interval($schedules) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'octosqueeze'),
        ];
        return $schedules;
    }

    public function enqueue_admin_assets($hook) {
        $allowed_hooks = [
            'settings_page_octosqueeze',
            'upload.php',
            'media_page_octosqueeze-bulk',
        ];

        if (in_array($hook, $allowed_hooks)) {
            wp_enqueue_style(
                'octosqueeze-admin',
                OCTOSQUEEZE_PLUGIN_URL . 'admin/css/admin.css',
                [],
                OCTOSQUEEZE_VERSION
            );

            wp_enqueue_script(
                'octosqueeze-admin',
                OCTOSQUEEZE_PLUGIN_URL . 'admin/js/admin.js',
                ['jquery'],
                OCTOSQUEEZE_VERSION,
                true
            );

            wp_localize_script('octosqueeze-admin', 'octosqueeze', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('octosqueeze_nonce'),
            ]);
        }
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('options-general.php?page=octosqueeze') . '">' .
                         __('Settings', 'octosqueeze') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function add_bulk_menu() {
        add_media_page(
            __('Bulk Optimization', 'octosqueeze'),
            __('Bulk Optimize', 'octosqueeze'),
            'upload_files',
            'octosqueeze-bulk',
            [$this->bulk, 'render_page']
        );
    }

    public function add_media_column($columns) {
        $columns['octosqueeze'] = __('OctoSqueeze', 'octosqueeze');
        return $columns;
    }

    public function render_media_column($column_name, $attachment_id) {
        if ($column_name !== 'octosqueeze') {
            return;
        }

        $meta = get_post_meta($attachment_id, '_octosqueeze', true);

        if (!$meta) {
            echo '<span class="octosqueeze-status pending">' .
                 '<button class="button octosqueeze-compress" data-id="' . esc_attr($attachment_id) . '">' .
                 __('Compress', 'octosqueeze') . '</button></span>';
            return;
        }

        if ($meta['status'] === 'compressed') {
            $savings = $meta['savings_percent'] ?? 0;
            echo '<span class="octosqueeze-status compressed" title="' .
                 esc_attr(sprintf(__('Saved %d%%', 'octosqueeze'), $savings)) . '">' .
                 sprintf(__('-%d%%', 'octosqueeze'), $savings) . '</span>';
        } elseif ($meta['status'] === 'processing') {
            echo '<span class="octosqueeze-status processing">' .
                 __('Processing...', 'octosqueeze') . '</span>';
        } elseif ($meta['status'] === 'error') {
            echo '<span class="octosqueeze-status error" title="' .
                 esc_attr($meta['error'] ?? '') . '">' .
                 __('Error', 'octosqueeze') . '</span>';
        }
    }

    public function ajax_stats() {
        check_ajax_referer('octosqueeze_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'octosqueeze_compressions';

        $stats = $wpdb->get_row("
            SELECT
                COUNT(*) as total_images,
                SUM(original_size) as total_original,
                SUM(compressed_size) as total_compressed,
                SUM(original_size - compressed_size) as total_saved
            FROM $table_name
            WHERE status = 'completed'
        ");

        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");

        wp_send_json_success([
            'total_images' => (int) $stats->total_images,
            'total_original' => (int) $stats->total_original,
            'total_compressed' => (int) $stats->total_compressed,
            'total_saved' => (int) $stats->total_saved,
            'pending' => (int) $pending,
        ]);
    }
}
