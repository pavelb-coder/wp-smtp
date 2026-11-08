<?php
/**
 * Fired when the plugin is uninstalled (deleted from the Plugins screen).
 *
 * @package WP_SMTP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-wp-smtp-options.php';

WP_SMTP_Options::uninstall();

// Flash notices are per-user transients; remove the common ones for this site.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wp_smtp_notice_%' OR option_name LIKE '_transient_timeout_wp_smtp_notice_%'"
);
