<?php
/**
 * Smoke test for WP_SMTP_Updates — runs without WordPress.
 *
 * Usage: php tests/test-updates.php
 *
 * @package WP_SMTP
 */

define( 'ABSPATH', '/tmp/' );

$GLOBALS['filters'] = array();

function add_filter( $hook, $callback, $priority = 10 ) {
	$GLOBALS['filters'][ $hook ][] = $callback;
	return true;
}

function plugin_basename( $file ) {
	return 'wp-smtp-kv/' . basename( $file );
}

require dirname( __DIR__ ) . '/includes/class-wp-smtp-updates.php';

$updates = new WP_SMTP_Updates( '/var/www/wp-content/plugins/wp-smtp-kv/wp-smtp.php' );
$updates->register();

$checks = array();
$checks['filter registered'] = ! empty( $GLOBALS['filters']['site_transient_update_plugins'] );

$transient = (object) array(
	'response'  => array(
		'wp-smtp-kv/wp-smtp.php' => (object) array(
			'slug'        => 'wp-smtp',
			'id'          => 'w.org/plugin/wp-smtp',
			'new_version' => '3.0.0',
			'package'     => 'https://downloads.wordpress.org/plugin/wp-smtp.3.0.0.zip',
		),
		'wp-smtp/wp-smtp.php' => (object) array( 'slug' => 'wp-smtp', 'new_version' => '3.0.0' ),
		'other/plugin.php'    => (object) array( 'slug' => 'other', 'new_version' => '2.0.0' ),
	),
	'no_update' => array(
		'wp-smtp-kv/wp-smtp.php' => (object) array( 'slug' => 'wp-smtp' ),
	),
);

$out = $updates->filter_updates( $transient );

$checks['false .org update removed'] = ! isset( $out->response['wp-smtp-kv/wp-smtp.php'] );
$checks['legacy basename cleaned']   = ! isset( $out->response['wp-smtp/wp-smtp.php'] );
$checks['other plugin untouched']    = isset( $out->response['other/plugin.php'] );
$checks['no_update cleaned']         = ! isset( $out->no_update['wp-smtp-kv/wp-smtp.php'] );

// A future custom updater must NOT be stripped.
$custom = (object) array(
	'response' => array(
		'wp-smtp-kv/wp-smtp.php' => (object) array(
			'slug'        => 'wp-smtp-kv',
			'new_version' => '1.2.0',
			'package'     => 'https://github.com/pavelb-coder/wp-smtp/releases/download/v1.2.0/wp-smtp-kv-1.2.0.zip',
		),
	),
);
$out2 = $updates->filter_updates( $custom );
$checks['custom updater preserved'] = isset( $out2->response['wp-smtp-kv/wp-smtp.php'] );
$checks['non-object passthrough']   = false === $updates->filter_updates( false );

foreach ( $checks as $name => $ok ) {
	printf( "%s  %s\n", $ok ? 'PASS' : 'FAIL', $name );
}

exit( in_array( false, $checks, true ) ? 1 : 0 );
