<?php
/**
 * Plugin Name: OctoSqueeze
 * Plugin URI: https://octosqueeze.com/wordpress
 * Description: Automatic image compression and WebP/AVIF conversion for WordPress.
 * Version: 1.0.0
 * Author: OctoSqueeze
 * Author URI: https://octosqueeze.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: octosqueeze
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('OCTOSQUEEZE_VERSION', '1.0.0');
define('OCTOSQUEEZE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OCTOSQUEEZE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OCTOSQUEEZE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoload
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-api.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-compressor.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-settings.php';
require_once OCTOSQUEEZE_PLUGIN_DIR . 'includes/class-octosqueeze-bulk.php';

// Initialize the plugin
function octosqueeze_init() {
    $plugin = new OctoSqueeze();
    $plugin->run();
}
add_action('plugins_loaded', 'octosqueeze_init');

// Activation hook
register_activation_hook(__FILE__, 'octosqueeze_activate');
function octosqueeze_activate() {
    // Set default options
    $defaults = [
        'api_key' => '',
        'mode' => 'balanced',
        'formats' => ['webp'],
        'auto_compress' => true,
        'compress_on_upload' => true,
        'preserve_originals' => true,
        'max_width' => 2560,
        'max_height' => 2560,
    ];

    if (!get_option('octosqueeze_settings')) {
        add_option('octosqueeze_settings', $defaults);
    }

    // Create database table for tracking
    global $wpdb;
    $table_name = $wpdb->prefix . 'octosqueeze_compressions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        attachment_id bigint(20) NOT NULL,
        original_size bigint(20) NOT NULL,
        compressed_size bigint(20) NOT NULL,
        format varchar(10) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY attachment_id (attachment_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'octosqueeze_deactivate');
function octosqueeze_deactivate() {
    // Clean up scheduled events
    wp_clear_scheduled_hook('octosqueeze_process_queue');
}
