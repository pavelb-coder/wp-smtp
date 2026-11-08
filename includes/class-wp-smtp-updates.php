<?php
/**
 * Protects the plugin from WordPress.org update collisions.
 *
 * The slug `wp-smtp` belongs to "Solid Mail – SMTP email and logging made by
 * SolidWP" in the WordPress.org Plugin Directory. WordPress checks updates by
 * slug, so a custom plugin living in a `wp-smtp` folder used to be offered
 * that plugin's updates (and could be overwritten by them).
 *
 * The `Update URI` header (WordPress 5.8+) is the official fix: the .org API
 * ignores plugins whose UpdateURI host is not wordpress.org. This filter adds
 * a second layer — it drops already cached .org entries for our basenames,
 * so the false "update available" notice disappears immediately.
 *
 * @package WP_SMTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_SMTP_Updates
 */
class WP_SMTP_Updates {

	/**
	 * Main plugin file.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Basenames used by this and previous distributions of the plugin.
	 *
	 * @var string[]
	 */
	private $basenames = array(
		'wp-smtp/wp-smtp.php',
		'wp-smtp-kv/wp-smtp.php',
		'wp-smtp-kv/wp-smtp-kv.php',
	);

	/**
	 * @param string $plugin_file Absolute path to the main plugin file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
	}

	/**
	 * Register the update-transient filter.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'site_transient_update_plugins', array( $this, 'filter_updates' ), 999 );
	}

	/**
	 * Remove WordPress.org responses stored under our plugin basenames.
	 *
	 * Only entries that clearly come from the .org directory are removed, so a
	 * future custom updater (update_plugins_{hostname}) can still offer updates.
	 *
	 * @param object|mixed $transient The update_plugins transient.
	 * @return object|mixed
	 */
	public function filter_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$current = function_exists( 'plugin_basename' ) ? plugin_basename( $this->plugin_file ) : '';

		if ( '' !== $current && ! in_array( $current, $this->basenames, true ) ) {
			$this->basenames[] = $current;
		}

		foreach ( array( 'response', 'no_update' ) as $bucket ) {
			if ( ! isset( $transient->$bucket ) || ! is_array( $transient->$bucket ) ) {
				continue;
			}

			$items = $transient->$bucket;

			foreach ( $this->basenames as $basename ) {
				if ( isset( $items[ $basename ] ) && $this->is_directory_entry( $items[ $basename ] ) ) {
					unset( $items[ $basename ] );
				}
			}

			$transient->$bucket = $items;
		}

		return $transient;
	}

	/**
	 * Whether an update entry came from the WordPress.org directory.
	 *
	 * @param mixed $item Entry from response/no_update.
	 * @return bool
	 */
	private function is_directory_entry( $item ) {
		if ( ! is_object( $item ) ) {
			return true;
		}

		$slug    = isset( $item->slug ) ? (string) $item->slug : '';
		$id      = isset( $item->id ) ? (string) $item->id : '';
		$url     = isset( $item->url ) ? (string) $item->url : '';
		$package = isset( $item->package ) ? (string) $item->package : '';

		if ( 'wp-smtp' === $slug ) { // The colliding WordPress.org slug (Solid Mail / SolidWP).
			return true;
		}

		if ( false !== strpos( $id, 'w.org/plugin/wp-smtp' ) ) {
			return true;
		}

		if ( false !== strpos( $url, 'wordpress.org/plugins/wp-smtp' ) ) {
			return true;
		}

		return false !== strpos( $package, 'downloads.wordpress.org' );
	}
}
