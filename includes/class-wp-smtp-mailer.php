<?php
/**
 * PHPMailer configuration and test-mail sending.
 *
 * @package WP_SMTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_SMTP_Mailer
 */
class WP_SMTP_Mailer {

	/**
	 * @var WP_SMTP_Options
	 */
	private $options;

	/**
	 * Force SMTP configuration while a test email is being sent.
	 *
	 * @var bool
	 */
	private $testing = false;

	/**
	 * @param WP_SMTP_Options $options Settings object.
	 */
	public function __construct( WP_SMTP_Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ), 999 );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 999 );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 999 );
	}

	/**
	 * Apply SMTP settings to the PHPMailer instance WordPress uses.
	 *
	 * @param object $phpmailer PHPMailer instance (namespaced in modern WordPress).
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer ) {
		if ( ! $this->is_active() || ! is_object( $phpmailer ) || ! method_exists( $phpmailer, 'isSMTP' ) ) {
			return;
		}

		$settings = $this->options->get_all();

		if ( empty( $settings['host'] ) ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['host'];
		$phpmailer->Port       = (int) $settings['port'];
		$phpmailer->SMTPAuth   = (bool) $settings['auth'];
		$phpmailer->Username   = $settings['username'];
		$phpmailer->Password   = $this->options->get_password();
		$phpmailer->SMTPSecure = 'none' === $settings['encryption'] ? '' : $settings['encryption'];
		$phpmailer->Timeout    = (int) $settings['timeout'];
		$phpmailer->SMTPDebug  = $settings['debug'] ? 2 : 0;
		$phpmailer->Debugoutput = 'error_log';
	}

	/**
	 * Override the From address when configured.
	 *
	 * @param string $email Default From address.
	 * @return string
	 */
	public function filter_from_email( $email ) {
		if ( ! $this->is_active() || ! $this->options->get( 'force_from' ) ) {
			return $email;
		}

		$custom = $this->options->get( 'from_email' );
		$valid  = is_email( $custom );

		return $valid ? $valid : $email;
	}

	/**
	 * Override the From name when configured.
	 *
	 * @param string $name Default From name.
	 * @return string
	 */
	public function filter_from_name( $name ) {
		if ( ! $this->is_active() || ! $this->options->get( 'force_from' ) ) {
			return $name;
		}

		$custom = trim( (string) $this->options->get( 'from_name' ) );

		return '' !== $custom ? $custom : $name;
	}

	/**
	 * Send a test email using the saved SMTP settings.
	 *
	 * @param string $to Recipient address.
	 * @return true|WP_Error
	 */
	public function send_test( $to ) {
		$to = sanitize_email( $to );

		if ( ! is_email( $to ) ) {
			return new WP_Error( 'wp_smtp_invalid_email', __( 'Укажите корректный e-mail получателя.', 'wp-smtp' ) );
		}

		$settings = $this->options->get_all();

		if ( empty( $settings['host'] ) ) {
			return new WP_Error( 'wp_smtp_no_host', __( 'Сначала сохраните SMTP-хост в настройках.', 'wp-smtp' ) );
		}

		$this->testing = true;
		$failure       = null;

		$collector = function ( $wp_error ) use ( &$failure ) {
			$failure = $wp_error;
		};

		add_action( 'wp_mail_failed', $collector, 999 );

		$host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$subject = sprintf( '[WP SMTP-KV] Тестовое письмо%s', $host ? ' с ' . $host : '' );
		$body    = sprintf(
			"Это тестовое письмо плагина WP SMTP-KV.\n\nСервер: %s:%d (%s)\nВремя: %s\nСайт: %s\n\nЕсли письмо дошло — SMTP настроен верно.",
			$settings['host'],
			(int) $settings['port'],
			$settings['encryption'],
			wp_date( 'd.m.Y H:i:s' ),
			home_url()
		);

		$sent = wp_mail( $to, $subject, $body );

		remove_action( 'wp_mail_failed', $collector, 999 );
		$this->testing = false;

		if ( $failure instanceof WP_Error ) {
			return $failure;
		}

		if ( ! $sent ) {
			return new WP_Error( 'wp_smtp_send_failed', __( 'wp_mail() вернул false. Включите отладку и проверьте логи PHP.', 'wp-smtp' ) );
		}

		return true;
	}

	/**
	 * Whether SMTP configuration should be applied right now.
	 *
	 * @return bool
	 */
	private function is_active() {
		return $this->testing || (bool) $this->options->get( 'enabled' );
	}
}
