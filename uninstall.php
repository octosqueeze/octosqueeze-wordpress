<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * Cleans up plugin options and database tables.
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove plugin options
delete_option('octosqueeze_settings');

// Remove per-attachment meta
delete_post_meta_by_key('_octosqueeze');

// Drop custom database table
global $wpdb;
$table_name = $wpdb->prefix . 'octosqueeze_compressions';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any scheduled events
wp_clear_scheduled_hook('octosqueeze_process_queue');
