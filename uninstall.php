<?php
/**
 * This file is run automatically when the user deletes the plugin.
 * It removes all elements added by the plugin (e.g., custom options, tables, etc.).
 *
 * More information: https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Remove plugin options
delete_option('wdo_fields');
delete_option('wdo_options'); // Remove the main plugin options
delete_option('wdo_debug_log'); // Remove debug logs if applicable

// Remove any other custom options added by the plugin
delete_option('wdo_custom_field_1');
delete_option('wdo_custom_field_2');

// If the plugin created custom database tables, drop them here
// global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}wdo_custom_table");

