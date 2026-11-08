<?php
/**
 * Settings storage, sanitisation and password encryption.
 *
 * @package WP_SMTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_SMTP_Options
 */
class WP_SMTP_Options {

	const OPTION_KEY = 'wp_smtp_settings';

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	private $defaults = array(
		'enabled'    => 0,
		'host'       => '',
		'port'       => 587,
		'encryption' => 'tls',
		'auth'       => 1,
		'username'   => '',
		'password'   => '',
		'from_email' => '',
		'from_name'  => '',
		'force_from' => 1,
		'timeout'    => 15,
		'debug'      => 0,
	);

	/**
	 * Create the option on activation without overwriting existing settings.
	 *
	 * @return void
	 */
	public static function activate() {
		$self    = new self();
		$current = get_option( self::OPTION_KEY, false );

		if ( false === $current ) {
			add_option( self::OPTION_KEY, $self->defaults );
		}
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->defaults );
	}

	/**
	 * Single setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$all = $this->get_all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Decrypted SMTP password.
	 *
	 * @return string
	 */
	public function get_password() {
		return $this->decrypt( (string) $this->get( 'password' ) );
	}

	/**
	 * Whether a password is currently stored.
	 *
	 * @return bool
	 */
	public function has_password() {
		return '' !== $this->get_password();
	}

	/**
	 * Sanitise and persist the submitted settings.
	 *
	 * An empty password field keeps the previously stored password.
	 *
	 * @param array $input Raw (already unslashed) form values.
	 * @return array Saved settings.
	 */
	public function update( array $input ) {
		$current = $this->get_all();

		$encryption = isset( $input['encryption'] ) && in_array( $input['encryption'], array( 'none', 'ssl', 'tls' ), true )
			? $input['encryption']
			: 'none';

		$settings = array(
			'enabled'    => empty( $input['enabled'] ) ? 0 : 1,
			'host'       => isset( $input['host'] ) ? sanitize_text_field( $input['host'] ) : '',
			'port'       => isset( $input['port'] ) ? absint( $input['port'] ) : 587,
			'encryption' => $encryption,
			'auth'       => empty( $input['auth'] ) ? 0 : 1,
			'username'   => isset( $input['username'] ) ? sanitize_text_field( $input['username'] ) : '',
			'password'   => $current['password'],
			'from_email' => isset( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : '',
			'from_name'  => isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : '',
			'force_from' => empty( $input['force_from'] ) ? 0 : 1,
			'timeout'    => isset( $input['timeout'] ) ? max( 5, min( 120, absint( $input['timeout'] ) ) ) : 15,
			'debug'      => empty( $input['debug'] ) ? 0 : 1,
		);

		if ( 0 === $settings['port'] || $settings['port'] > 65535 ) {
			$settings['port'] = 587;
		}

		if ( ! empty( $input['clear_password'] ) ) {
			$settings['password'] = '';
		} elseif ( isset( $input['password'] ) && '' !== trim( (string) $input['password'] ) ) {
			$settings['password'] = $this->encrypt( trim( (string) $input['password'] ) );
		}

		update_option( self::OPTION_KEY, $settings );

		return $settings;
	}

	/**
	 * Remove the settings option on uninstall.
	 *
	 * @return void
	 */
	public static function uninstall() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * AES-256-CBC encryption key derived from the site salts.
	 *
	 * @return string Binary 32-byte key.
	 */
	private function cipher_key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|wp-smtp-v1', true );
	}

	/**
	 * Encrypt a secret. Falls back to plain text only if OpenSSL is missing.
	 *
	 * @param string $plain Plain text secret.
	 * @return string
	 */
	private function encrypt( $plain ) {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}

		$iv     = function_exists( 'random_bytes' ) ? random_bytes( 16 ) : openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', $this->cipher_key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return '';
		}

		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a secret; returns the raw value for legacy/unencrypted data.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	private function decrypt( $value ) {
		if ( '' === $value || ! function_exists( 'openssl_decrypt' ) ) {
			return $value;
		}

		$raw = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return $value;
		}

		$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', $this->cipher_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );

		return false === $plain ? '' : $plain;
	}
}
