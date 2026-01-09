<?php
/**
 * OctoSqueeze Bulk Optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class OctoSqueeze_Bulk {

    protected $api;

    public function __construct() {
        $this->api = new OctoSqueeze_API();
    }

    /**
     * Register AJAX handlers
     */
    public function register_ajax_handlers() {
        add_action('wp_ajax_octosqueeze_bulk_get_images', [$this, 'ajax_get_images']);
        add_action('wp_ajax_octosqueeze_bulk_compress', [$this, 'ajax_compress_image']);
        add_action('wp_ajax_octosqueeze_bulk_status', [$this, 'ajax_get_status']);
    }

    /**
     * Get uncompressed images for bulk processing
     */
    public function ajax_get_images() {
        check_ajax_referer('octosqueeze_bulk_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $page = absint($_POST['page'] ?? 1);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        // Get all image attachments
        $args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'DESC',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_octosqueeze',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_octosqueeze',
                    'value' => '"status":"compressed"',
                    'compare' => 'NOT LIKE',
                ],
            ],
        ];

        $query = new WP_Query($args);
        $images = [];

        foreach ($query->posts as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            $file_size = file_exists($file_path) ? filesize($file_path) : 0;
            $meta = get_post_meta($attachment->ID, '_octosqueeze', true);

            $images[] = [
                'id' => $attachment->ID,
                'title' => $attachment->post_title,
                'filename' => basename($file_path),
                'size' => $file_size,
                'size_formatted' => size_format($file_size),
                'thumbnail' => wp_get_attachment_image_url($attachment->ID, 'thumbnail'),
                'status' => $meta['status'] ?? 'pending',
            ];
        }

        // Get total count
        $count_args = [
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_octosqueeze',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_octosqueeze',
                    'value' => '"status":"compressed"',
                    'compare' => 'NOT LIKE',
                ],
            ],
        ];
        $count_query = new WP_Query($count_args);
        $total = $count_query->found_posts;

        wp_send_json_success([
            'images' => $images,
            'total' => $total,
            'page' => $page,
            'pages' => ceil($total / $per_page),
        ]);
    }

    /**
     * Compress a single image via AJAX
     */
    public function ajax_compress_image() {
        check_ajax_referer('octosqueeze_bulk_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $attachment_id = absint($_POST['attachment_id'] ?? 0);

        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }

        $file_path = get_attached_file($attachment_id);

        if (!file_exists($file_path)) {
            wp_send_json_error(['message' => 'File not found']);
        }

        // Mark as processing
        update_post_meta($attachment_id, '_octosqueeze', [
            'status' => 'processing',
            'started_at' => current_time('mysql'),
        ]);

        // Call API to compress
        $result = $this->api->compress_file($file_path);

        if (!$result['state']) {
            update_post_meta($attachment_id, '_octosqueeze', [
                'status' => 'error',
                'error' => $result['error'] ?? 'Unknown error',
            ]);
            wp_send_json_error(['message' => $result['error'] ?? 'Compression failed']);
        }

        $data = $result['data'];
        $original_size = filesize($file_path);
        $compressed_size = $data['compressed_size'] ?? $original_size;
        $savings = $original_size - $compressed_size;
        $savings_percent = $original_size > 0 ? round(($savings / $original_size) * 100) : 0;

        // Download and save compressed file if available
        if (!empty($data['download_url'])) {
            $compressed_content = $this->api->download($data['download_url']);
            if ($compressed_content) {
                // Save compressed version
                $options = get_option('octosqueeze_settings', []);
                if (empty($options['preserve_originals'])) {
                    // Replace original
                    file_put_contents($file_path, $compressed_content);
                }

                // Save WebP/AVIF versions if generated
                if (!empty($data['formats'])) {
                    $upload_dir = wp_upload_dir();
                    $file_info = pathinfo($file_path);

                    foreach ($data['formats'] as $format => $format_data) {
                        if (!empty($format_data['download_url'])) {
                            $format_content = $this->api->download($format_data['download_url']);
                            if ($format_content) {
                                $new_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.' . $format;
                                file_put_contents($new_path, $format_content);
                            }
                        }
                    }
                }
            }
        }

        // Update meta
        update_post_meta($attachment_id, '_octosqueeze', [
            'status' => 'compressed',
            'original_size' => $original_size,
            'compressed_size' => $compressed_size,
            'savings_bytes' => $savings,
            'savings_percent' => $savings_percent,
            'compressed_at' => current_time('mysql'),
        ]);

        // Log to database
        global $wpdb;
        $table_name = $wpdb->prefix . 'octosqueeze_compressions';
        $wpdb->insert($table_name, [
            'attachment_id' => $attachment_id,
            'original_size' => $original_size,
            'compressed_size' => $compressed_size,
            'format' => 'original',
            'status' => 'completed',
        ]);

        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'original_size' => $original_size,
            'compressed_size' => $compressed_size,
            'savings_bytes' => $savings,
            'savings_percent' => $savings_percent,
        ]);
    }

    /**
     * Get overall bulk status
     */
    public function ajax_get_status() {
        check_ajax_referer('octosqueeze_bulk_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;

        // Total images
        $total = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type LIKE 'image/%'
        ");

        // Compressed images
        $compressed = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->postmeta}
            WHERE meta_key = '_octosqueeze'
            AND meta_value LIKE '%\"status\":\"compressed\"%'
        ");

        // Total savings
        $table_name = $wpdb->prefix . 'octosqueeze_compressions';
        $savings = $wpdb->get_row("
            SELECT
                SUM(original_size) as total_original,
                SUM(compressed_size) as total_compressed,
                SUM(original_size - compressed_size) as total_saved
            FROM $table_name
            WHERE status = 'completed'
        ");

        wp_send_json_success([
            'total_images' => (int) $total,
            'compressed_images' => (int) $compressed,
            'uncompressed_images' => (int) $total - (int) $compressed,
            'total_original' => (int) ($savings->total_original ?? 0),
            'total_compressed' => (int) ($savings->total_compressed ?? 0),
            'total_saved' => (int) ($savings->total_saved ?? 0),
            'total_saved_formatted' => size_format($savings->total_saved ?? 0),
        ]);
    }

    /**
     * Render bulk optimization page
     */
    public function render_page() {
        if (!current_user_can('upload_files')) {
            wp_die(__('You do not have sufficient permissions.', 'octosqueeze'));
        }

        $options = get_option('octosqueeze_settings', []);
        $has_api_key = !empty($options['api_key']);
        ?>
        <div class="wrap octosqueeze-bulk-wrap">
            <h1><?php _e('Bulk Optimization', 'octosqueeze'); ?></h1>

            <?php if (!$has_api_key): ?>
                <div class="notice notice-error">
                    <p>
                        <?php printf(
                            __('Please <a href="%s">configure your API key</a> before using bulk optimization.', 'octosqueeze'),
                            admin_url('options-general.php?page=octosqueeze')
                        ); ?>
                    </p>
                </div>
            <?php else: ?>

            <!-- Stats Overview -->
            <div class="octosqueeze-bulk-stats">
                <div class="stat-card">
                    <span class="stat-value" id="stat-total">-</span>
                    <span class="stat-label"><?php _e('Total Images', 'octosqueeze'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-value" id="stat-compressed">-</span>
                    <span class="stat-label"><?php _e('Compressed', 'octosqueeze'); ?></span>
                </div>
                <div class="stat-card">
                    <span class="stat-value" id="stat-pending">-</span>
                    <span class="stat-label"><?php _e('Pending', 'octosqueeze'); ?></span>
                </div>
                <div class="stat-card stat-card-highlight">
                    <span class="stat-value" id="stat-saved">-</span>
                    <span class="stat-label"><?php _e('Space Saved', 'octosqueeze'); ?></span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="octosqueeze-progress-container" style="display: none;">
                <div class="progress-header">
                    <span id="progress-status"><?php _e('Compressing...', 'octosqueeze'); ?></span>
                    <span id="progress-count">0 / 0</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-details">
                    <span id="progress-current"><?php _e('Processing:', 'octosqueeze'); ?> -</span>
                    <span id="progress-saved"><?php _e('Saved:', 'octosqueeze'); ?> 0 KB</span>
                </div>
            </div>

            <!-- Controls -->
            <div class="octosqueeze-controls">
                <button type="button" class="button button-primary button-hero" id="start-bulk">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <?php _e('Start Bulk Optimization', 'octosqueeze'); ?>
                </button>
                <button type="button" class="button button-secondary" id="stop-bulk" style="display: none;">
                    <span class="dashicons dashicons-controls-pause"></span>
                    <?php _e('Pause', 'octosqueeze'); ?>
                </button>
                <button type="button" class="button button-link" id="refresh-list">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh', 'octosqueeze'); ?>
                </button>
            </div>

            <!-- Image List -->
            <div class="octosqueeze-image-list">
                <h2><?php _e('Uncompressed Images', 'octosqueeze'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th class="column-thumbnail"><?php _e('Image', 'octosqueeze'); ?></th>
                            <th class="column-filename"><?php _e('Filename', 'octosqueeze'); ?></th>
                            <th class="column-size"><?php _e('Size', 'octosqueeze'); ?></th>
                            <th class="column-status"><?php _e('Status', 'octosqueeze'); ?></th>
                            <th class="column-actions"><?php _e('Actions', 'octosqueeze'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="image-list-body">
                        <tr>
                            <td colspan="5" class="loading-row">
                                <span class="spinner is-active"></span>
                                <?php _e('Loading images...', 'octosqueeze'); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="tablenav bottom">
                    <div class="tablenav-pages" id="pagination"></div>
                </div>
            </div>

            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var isProcessing = false;
            var shouldStop = false;
            var totalSaved = 0;
            var processedCount = 0;
            var totalToProcess = 0;
            var imageQueue = [];

            // Load initial data
            loadStatus();
            loadImages(1);

            // Start bulk
            $('#start-bulk').on('click', function() {
                if (isProcessing) return;
                startBulkOptimization();
            });

            // Stop bulk
            $('#stop-bulk').on('click', function() {
                shouldStop = true;
                $(this).text('<?php _e('Stopping...', 'octosqueeze'); ?>');
            });

            // Refresh
            $('#refresh-list').on('click', function() {
                loadStatus();
                loadImages(1);
            });

            // Single compress
            $(document).on('click', '.compress-single', function() {
                var $btn = $(this);
                var id = $btn.data('id');
                $btn.prop('disabled', true).text('<?php _e('Compressing...', 'octosqueeze'); ?>');
                compressImage(id, function(result) {
                    if (result.success) {
                        $btn.closest('tr').find('.column-status').html('<span class="status-compressed"><?php _e('Compressed', 'octosqueeze'); ?></span>');
                        $btn.text('<?php _e('Done', 'octosqueeze'); ?>');
                        loadStatus();
                    } else {
                        $btn.prop('disabled', false).text('<?php _e('Retry', 'octosqueeze'); ?>');
                    }
                });
            });

            function loadStatus() {
                $.post(ajaxurl, {
                    action: 'octosqueeze_bulk_status',
                    nonce: '<?php echo wp_create_nonce('octosqueeze_bulk_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#stat-total').text(response.data.total_images);
                        $('#stat-compressed').text(response.data.compressed_images);
                        $('#stat-pending').text(response.data.uncompressed_images);
                        $('#stat-saved').text(response.data.total_saved_formatted);
                    }
                });
            }

            function loadImages(page) {
                $.post(ajaxurl, {
                    action: 'octosqueeze_bulk_get_images',
                    nonce: '<?php echo wp_create_nonce('octosqueeze_bulk_nonce'); ?>',
                    page: page
                }, function(response) {
                    if (response.success) {
                        renderImageList(response.data.images);
                        renderPagination(response.data);
                    }
                });
            }

            function renderImageList(images) {
                var $tbody = $('#image-list-body');
                $tbody.empty();

                if (images.length === 0) {
                    $tbody.html('<tr><td colspan="5" class="no-images"><?php _e('All images are compressed!', 'octosqueeze'); ?></td></tr>');
                    return;
                }

                images.forEach(function(img) {
                    var statusHtml = img.status === 'compressed'
                        ? '<span class="status-compressed"><?php _e('Compressed', 'octosqueeze'); ?></span>'
                        : '<span class="status-pending"><?php _e('Pending', 'octosqueeze'); ?></span>';

                    var actionHtml = img.status === 'compressed'
                        ? '-'
                        : '<button type="button" class="button button-small compress-single" data-id="' + img.id + '"><?php _e('Compress', 'octosqueeze'); ?></button>';

                    $tbody.append(
                        '<tr data-id="' + img.id + '">' +
                        '<td class="column-thumbnail"><img src="' + img.thumbnail + '" width="50" height="50"></td>' +
                        '<td class="column-filename">' + img.filename + '</td>' +
                        '<td class="column-size">' + img.size_formatted + '</td>' +
                        '<td class="column-status">' + statusHtml + '</td>' +
                        '<td class="column-actions">' + actionHtml + '</td>' +
                        '</tr>'
                    );
                });
            }

            function renderPagination(data) {
                var $pagination = $('#pagination');
                $pagination.empty();

                if (data.pages <= 1) return;

                for (var i = 1; i <= data.pages; i++) {
                    var cls = i === data.page ? 'button button-primary' : 'button';
                    $pagination.append('<button type="button" class="' + cls + ' page-btn" data-page="' + i + '">' + i + '</button> ');
                }

                $('.page-btn').on('click', function() {
                    loadImages($(this).data('page'));
                });
            }

            function startBulkOptimization() {
                isProcessing = true;
                shouldStop = false;
                totalSaved = 0;
                processedCount = 0;

                $('#start-bulk').hide();
                $('#stop-bulk').show();
                $('.octosqueeze-progress-container').show();

                // Get all pending images
                $.post(ajaxurl, {
                    action: 'octosqueeze_bulk_get_images',
                    nonce: '<?php echo wp_create_nonce('octosqueeze_bulk_nonce'); ?>',
                    page: 1
                }, function(response) {
                    if (response.success && response.data.images.length > 0) {
                        imageQueue = response.data.images.filter(function(img) {
                            return img.status !== 'compressed';
                        });
                        totalToProcess = imageQueue.length;
                        $('#progress-count').text('0 / ' + totalToProcess);
                        processNextImage();
                    } else {
                        finishBulk();
                    }
                });
            }

            function processNextImage() {
                if (shouldStop || imageQueue.length === 0) {
                    finishBulk();
                    return;
                }

                var img = imageQueue.shift();
                $('#progress-current').text('<?php _e('Processing:', 'octosqueeze'); ?> ' + img.filename);

                compressImage(img.id, function(result) {
                    processedCount++;
                    var percent = Math.round((processedCount / totalToProcess) * 100);
                    $('#progress-fill').css('width', percent + '%');
                    $('#progress-count').text(processedCount + ' / ' + totalToProcess);

                    if (result.success) {
                        totalSaved += result.data.savings_bytes;
                        $('#progress-saved').text('<?php _e('Saved:', 'octosqueeze'); ?> ' + formatBytes(totalSaved));
                        $('tr[data-id="' + img.id + '"] .column-status').html('<span class="status-compressed"><?php _e('Compressed', 'octosqueeze'); ?></span>');
                    }

                    // Small delay to avoid overwhelming the server
                    setTimeout(processNextImage, 500);
                });
            }

            function compressImage(id, callback) {
                $.post(ajaxurl, {
                    action: 'octosqueeze_bulk_compress',
                    nonce: '<?php echo wp_create_nonce('octosqueeze_bulk_nonce'); ?>',
                    attachment_id: id
                }, callback);
            }

            function finishBulk() {
                isProcessing = false;
                $('#start-bulk').show();
                $('#stop-bulk').hide().text('<?php _e('Pause', 'octosqueeze'); ?>');
                $('#progress-status').text('<?php _e('Complete!', 'octosqueeze'); ?>');
                loadStatus();
                loadImages(1);
            }

            function formatBytes(bytes) {
                if (bytes === 0) return '0 B';
                var k = 1024;
                var sizes = ['B', 'KB', 'MB', 'GB'];
                var i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
        });
        </script>
        <?php
    }
}
