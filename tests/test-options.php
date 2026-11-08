<?php
/**
 * Smoke test for WP_SMTP_Options — runs without WordPress.
 *
 * Usage: php tests/test-options.php
 * Stubs the handful of WordPress functions the class uses.
 *
 * @package WP_SMTP
 */

define( 'ABSPATH', '/tmp/' );

$GLOBALS['test_options'] = array();

function wp_salt( $scheme = 'auth' ) {
	return 'fixed-test-salt-for-smoke-test-only';
}
function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}
function sanitize_email( $value ) {
	return filter_var( trim( (string) $value ), FILTER_SANITIZE_EMAIL );
}
function absint( $value ) {
	return abs( (int) $value );
}
function wp_parse_args( $args, $defaults ) {
	return array_merge( $defaults, (array) $args );
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['test_options'] ) ? $GLOBALS['test_options'][ $key ] : $default;
}
function add_option( $key, $value ) {
	$GLOBALS['test_options'][ $key ] = $value;
}
function update_option( $key, $value ) {
	$GLOBALS['test_options'][ $key ] = $value;
	return true;
}
function delete_option( $key ) {
	unset( $GLOBALS['test_options'][ $key ] );
}

require dirname( __DIR__ ) . '/includes/class-wp-smtp-options.php';

$options = new WP_SMTP_Options();
WP_SMTP_Options::activate();

$options->update(
	array(
		'enabled'    => '1',
		'host'       => 'smtp.example.com',
		'port'       => '465',
		'encryption' => 'ssl',
		'auth'       => '1',
		'username'   => 'user@example.com',
		'password'   => 's3cr3t!',
		'from_email' => 'user@example.com',
		'from_name'  => 'Тест',
		'force_from' => '1',
		'timeout'    => '20',
		'debug'      => '1',
	)
);

$checks = array(
	'password round-trip'          => 's3cr3t!' === $options->get_password(),
	'no plaintext in option'       => false === strpos( $GLOBALS['test_options']['wp_smtp_settings']['password'], 's3cr3t!' ),
	'port and encryption saved'    => 465 === $options->get( 'port' ) && 'ssl' === $options->get( 'encryption' ),
);

$options->update( array( 'host' => 'smtp.example.com', 'password' => '' ) );
$checks['empty password keeps old'] = 's3cr3t!' === $options->get_password();

$options->update( array( 'clear_password' => 1 ) );
$checks['clear password works'] = '' === $options->get_password();

$checks['defaults are sane'] = 587 === $options->get( 'port' )
	&& 'none' === $options->get( 'encryption' )
	&& 15 === $options->get( 'timeout' );

$checks['activate() keeps data'] = is_array( get_option( 'wp_smtp_settings' ) );

WP_SMTP_Options::uninstall();
$checks['uninstall() removes option'] = null === get_option( 'wp_smtp_settings', null );

foreach ( $checks as $name => $ok ) {
	printf( "%s  %s\n", $ok ? 'PASS' : 'FAIL', $name );
}

exit( in_array( false, $checks, true ) ? 1 : 0 );
