<?php
/**
 * Plugin Name:       WP SMTP-KV
 * Plugin URI:        https://github.com/pavelb-coder/wp-smtp
 * Description:       Sends WordPress mail through an authenticated SMTP server. Settings page, encrypted password storage and a test-email tool.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            pavelb-coder
 * Author URI:        https://github.com/pavelb-coder
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-smtp
 * Domain Path:       /languages
 *
 * @package WP_SMTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_SMTP_VERSION', '1.0.1' );
define( 'WP_SMTP_PLUGIN_FILE', __FILE__ );
define( 'WP_SMTP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SMTP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WP_SMTP_PLUGIN_DIR . 'includes/class-wp-smtp-options.php';
require_once WP_SMTP_PLUGIN_DIR . 'includes/class-wp-smtp-mailer.php';
require_once WP_SMTP_PLUGIN_DIR . 'includes/class-wp-smtp-admin.php';

/**
 * Boot the plugin after WordPress and all plugins are loaded.
 *
 * @return void
 */
function wp_smtp_init() {
	$options = new WP_SMTP_Options();
	$mailer  = new WP_SMTP_Mailer( $options );
	$mailer->register();

	if ( is_admin() ) {
		$admin = new WP_SMTP_Admin( $options, $mailer );
		$admin->register();
	}
}
add_action( 'plugins_loaded', 'wp_smtp_init' );

register_activation_hook( __FILE__, array( 'WP_SMTP_Options', 'activate' ) );

/**
 * Load the translation files.
 *
 * @return void
 */
function wp_smtp_load_textdomain() {
	load_plugin_textdomain( 'wp-smtp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wp_smtp_load_textdomain' );
