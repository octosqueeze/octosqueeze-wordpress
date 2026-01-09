<?php
/**
 * OctoSqueeze image compressor
 */

if (!defined('ABSPATH')) {
    exit;
}

class OctoSqueeze_Compressor {

    protected $api;

    public function __construct() {
        $this->api = new OctoSqueeze_API();
    }

    /**
     * Handle file upload
     */
    public function handle_upload($upload) {
        if (!isset($upload['file']) || !$this->is_image($upload['file'])) {
            return $upload;
        }

        // Mark for processing
        // Actual compression happens in process_attachment
        return $upload;
    }

    /**
     * Process attachment after upload
     */
    public function process_attachment($attachment_id) {
        $file = get_attached_file($attachment_id);

        if (!$file || !$this->is_image($file)) {
            return;
        }

        // Queue for compression
        $this->queue_compression($attachment_id, $file);
    }

    /**
     * Queue image for compression
     */
    protected function queue_compression($attachment_id, $file) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'octosqueeze_compressions';

        // Check if already queued
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE attachment_id = %d",
            $attachment_id
        ));

        if ($exists) {
            return;
        }

        $original_size = filesize($file);

        $wpdb->insert($table_name, [
            'attachment_id' => $attachment_id,
            'original_size' => $original_size,
            'compressed_size' => 0,
            'format' => pathinfo($file, PATHINFO_EXTENSION),
            'status' => 'pending',
        ]);

        // Update attachment meta
        update_post_meta($attachment_id, '_octosqueeze', [
            'status' => 'pending',
            'queued_at' => current_time('mysql'),
        ]);
    }

    /**
     * Process compression queue
     */
    public function process_queue() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'octosqueeze_compressions';

        // Get pending items (limit to 5 per run)
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = %s ORDER BY created_at ASC LIMIT 5",
            'pending'
        ));

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $this->compress_image($item);
        }
    }

    /**
     * Compress a single image
     */
    protected function compress_image($item) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'octosqueeze_compressions';

        $attachment_id = $item->attachment_id;
        $file = get_attached_file($attachment_id);

        if (!$file || !file_exists($file)) {
            $wpdb->update($table_name, ['status' => 'error'], ['id' => $item->id]);
            update_post_meta($attachment_id, '_octosqueeze', [
                'status' => 'error',
                'error' => 'File not found',
            ]);
            return;
        }

        // Mark as processing
        $wpdb->update($table_name, ['status' => 'processing'], ['id' => $item->id]);
        update_post_meta($attachment_id, '_octosqueeze', ['status' => 'processing']);

        // Call API
        $result = $this->api->compress_file($file);

        if (!$result['state']) {
            $wpdb->update($table_name, ['status' => 'error'], ['id' => $item->id]);
            update_post_meta($attachment_id, '_octosqueeze', [
                'status' => 'error',
                'error' => $result['error'] ?? 'Compression failed',
            ]);
            return;
        }

        $data = $result['data'];
        $settings = get_option('octosqueeze_settings', []);

        // Download and save compressed versions
        $saved_files = [];

        if (!empty($data['download_url'])) {
            $compressed = $this->api->download($data['download_url']);

            if ($compressed) {
                // Save WebP/AVIF versions
                $path_info = pathinfo($file);
                $format = $data['format'] ?? 'webp';
                $new_file = $path_info['dirname'] . '/' . $path_info['filename'] . '.' . $format;

                if (file_put_contents($new_file, $compressed)) {
                    $saved_files[] = $new_file;

                    // Optionally replace original
                    if (empty($settings['preserve_originals']) && $format === $path_info['extension']) {
                        // Backup and replace
                        copy($file, $file . '.backup');
                        file_put_contents($file, $compressed);
                    }
                }
            }
        }

        // Update database
        $compressed_size = $data['compressed_size'] ?? filesize($file);
        $savings_percent = $data['savings_percent'] ?? 0;

        $wpdb->update($table_name, [
            'status' => 'completed',
            'compressed_size' => $compressed_size,
        ], ['id' => $item->id]);

        update_post_meta($attachment_id, '_octosqueeze', [
            'status' => 'compressed',
            'original_size' => $item->original_size,
            'compressed_size' => $compressed_size,
            'savings_percent' => $savings_percent,
            'files' => $saved_files,
            'compressed_at' => current_time('mysql'),
        ]);
    }

    /**
     * AJAX handler for manual compression
     */
    public function ajax_compress() {
        check_ajax_referer('octosqueeze_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $attachment_id = intval($_POST['attachment_id'] ?? 0);

        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }

        $file = get_attached_file($attachment_id);

        if (!$file || !file_exists($file)) {
            wp_send_json_error(['message' => 'File not found']);
        }

        // Queue for compression
        $this->queue_compression($attachment_id, $file);

        // Process immediately
        global $wpdb;
        $table_name = $wpdb->prefix . 'octosqueeze_compressions';
        $item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE attachment_id = %d",
            $attachment_id
        ));

        if ($item) {
            $this->compress_image($item);
        }

        $meta = get_post_meta($attachment_id, '_octosqueeze', true);

        if ($meta && $meta['status'] === 'compressed') {
            wp_send_json_success([
                'savings_percent' => $meta['savings_percent'] ?? 0,
            ]);
        } else {
            wp_send_json_error([
                'message' => $meta['error'] ?? 'Compression failed',
            ]);
        }
    }

    /**
     * Check if file is an image
     */
    protected function is_image($file) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return in_array($ext, $allowed);
    }
}
