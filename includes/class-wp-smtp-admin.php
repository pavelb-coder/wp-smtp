<?php
/**
 * Settings page: Settings → WP SMTP-KV.
 *
 * @package WP_SMTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP_SMTP_Admin
 */
class WP_SMTP_Admin {

	const PAGE_SLUG = 'wp-smtp-kv';

	/**
	 * @var WP_SMTP_Options
	 */
	private $options;

	/**
	 * @var WP_SMTP_Mailer
	 */
	private $mailer;

	/**
	 * @param WP_SMTP_Options $options Settings object.
	 * @param WP_SMTP_Mailer  $mailer  Mailer object.
	 */
	public function __construct( WP_SMTP_Options $options, WP_SMTP_Mailer $mailer ) {
		$this->options = $options;
		$this->mailer  = $mailer;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_requests' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WP_SMTP_PLUGIN_FILE ), array( $this, 'settings_link' ) );
	}

	/**
	 * Add the settings page.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_options_page(
			__( 'WP SMTP-KV — настройки', 'wp-smtp-kv' ),
			'WP SMTP-KV',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Shortcut to the settings page on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function settings_link( $links ) {
		$url   = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		$links = (array) $links;

		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Настройки', 'wp-smtp-kv' ) . '</a>' );

		return $links;
	}

	/**
	 * Handle saving settings and sending the test email.
	 *
	 * @return void
	 */
	public function handle_requests() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['wp_smtp_save'] ) ) {
			check_admin_referer( 'wp_smtp_save_settings' );

			$input = isset( $_POST['wp_smtp'] ) ? wp_unslash( $_POST['wp_smtp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$this->options->update( is_array( $input ) ? $input : array() );

			set_transient(
				$this->notice_key(),
				array(
					'type' => 'success',
					'text' => __( 'Настройки WP SMTP-KV сохранены.', 'wp-smtp-kv' ),
				),
				60
			);

			$this->redirect( 'updated' );
		}

		if ( isset( $_POST['wp_smtp_test'] ) ) {
			check_admin_referer( 'wp_smtp_send_test' );

			$to = isset( $_POST['test_to'] ) ? sanitize_email( wp_unslash( $_POST['test_to'] ) ) : '';

			if ( ! is_email( $to ) ) {
				$to = get_option( 'admin_email' );
			}

			$result = $this->mailer->send_test( $to );

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				$type    = 'error';
			} else {
				$message = sprintf( __( 'Тестовое письмо отправлено на %s.', 'wp-smtp-kv' ), $to );
				$type    = 'success';
			}

			set_transient(
				$this->notice_key(),
				array(
					'type' => $type,
					'text' => $message,
				),
				60
			);

			$this->redirect( 'test' );
		}
	}

	/**
	 * Show admin notices after a redirect (POST/redirect/GET).
	 *
	 * @return void
	 */
	public function notices() {
		$notice = get_transient( $this->notice_key() );

		if ( ! is_array( $notice ) || ! isset( $notice['text'] ) ) {
			return;
		}

		delete_transient( $this->notice_key() );

		$type = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';

		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $notice['text'] )
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав для просмотра этой страницы.', 'wp-smtp-kv' ) );
		}

		$s         = $this->options->get_all();
		$pwd_saved = $this->options->has_password();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( ! $s['enabled'] ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'SMTP сейчас выключен: WordPress отправляет письма стандартным способом. Включите опцию ниже и сохраните настройки.', 'wp-smtp-kv' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'wp_smtp_save_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Использовать SMTP', 'wp-smtp-kv' ); ?></th>
						<td>
							<label for="wp_smtp_enabled">
								<input type="checkbox" id="wp_smtp_enabled" name="wp_smtp[enabled]" value="1" <?php checked( $s['enabled'], 1 ); ?> />
								<?php esc_html_e( 'Отправлять всю почту WordPress через указанный SMTP-сервер', 'wp-smtp-kv' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_host"><?php esc_html_e( 'SMTP-хост', 'wp-smtp-kv' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="wp_smtp_host" name="wp_smtp[host]" value="<?php echo esc_attr( $s['host'] ); ?>" placeholder="smtp.example.com" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Например: smtp.yandex.ru, smtp.gmail.com, smtp.mail.ru.', 'wp-smtp-kv' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_port"><?php esc_html_e( 'Порт', 'wp-smtp-kv' ); ?></label></th>
						<td><input type="number" min="1" max="65535" id="wp_smtp_port" name="wp_smtp[port]" value="<?php echo esc_attr( $s['port'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_encryption"><?php esc_html_e( 'Шифрование', 'wp-smtp-kv' ); ?></label></th>
						<td>
							<select id="wp_smtp_encryption" name="wp_smtp[encryption]">
								<option value="none" <?php selected( $s['encryption'], 'none' ); ?>><?php esc_html_e( 'Нет', 'wp-smtp-kv' ); ?></option>
								<option value="ssl" <?php selected( $s['encryption'], 'ssl' ); ?>>SSL (обычно порт 465)</option>
								<option value="tls" <?php selected( $s['encryption'], 'tls' ); ?>>STARTTLS / TLS (обычно порт 587)</option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Авторизация (SMTP AUTH)', 'wp-smtp-kv' ); ?></th>
						<td>
							<label for="wp_smtp_auth">
								<input type="checkbox" id="wp_smtp_auth" name="wp_smtp[auth]" value="1" <?php checked( $s['auth'], 1 ); ?> />
								<?php esc_html_e( 'Сервер требует логин и пароль', 'wp-smtp-kv' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_username"><?php esc_html_e( 'Логин', 'wp-smtp-kv' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wp_smtp_username" name="wp_smtp[username]" value="<?php echo esc_attr( $s['username'] ); ?>" autocomplete="username" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_password"><?php esc_html_e( 'Пароль / app-password', 'wp-smtp-kv' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="wp_smtp_password" name="wp_smtp[password]" value="" autocomplete="new-password" />
							<p class="description">
								<?php if ( $pwd_saved ) : ?>
									<?php esc_html_e( 'Пароль сохранён. Оставьте поле пустым, чтобы не менять его.', 'wp-smtp-kv' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Пароль ещё не задан. Хранится в базе в зашифрованном виде (AES-256-CBC, ключ из wp_salt).', 'wp-smtp-kv' ); ?>
								<?php endif; ?>
							</p>
							<?php if ( $pwd_saved ) : ?>
								<label><input type="checkbox" name="wp_smtp[clear_password]" value="1" /> <?php esc_html_e( 'Удалить сохранённый пароль', 'wp-smtp-kv' ); ?></label>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_from_email"><?php esc_html_e( 'E-mail отправителя', 'wp-smtp-kv' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="wp_smtp_from_email" name="wp_smtp[from_email]" value="<?php echo esc_attr( $s['from_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" />
							<p class="description"><?php esc_html_e( 'Должен совпадать с доменом/аккаунтом SMTP, иначе сервер отклонит письмо.', 'wp-smtp-kv' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_from_name"><?php esc_html_e( 'Имя отправителя', 'wp-smtp-kv' ); ?></label></th>
						<td><input type="text" class="regular-text" id="wp_smtp_from_name" name="wp_smtp[from_name]" value="<?php echo esc_attr( $s['from_name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Подменять From', 'wp-smtp-kv' ); ?></th>
						<td>
							<label for="wp_smtp_force_from">
								<input type="checkbox" id="wp_smtp_force_from" name="wp_smtp[force_from]" value="1" <?php checked( $s['force_from'], 1 ); ?> />
								<?php esc_html_e( 'Принудительно использовать указанные выше имя и адрес для всех писем', 'wp-smtp-kv' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wp_smtp_timeout"><?php esc_html_e( 'Таймаут, сек.', 'wp-smtp-kv' ); ?></label></th>
						<td><input type="number" min="5" max="120" id="wp_smtp_timeout" name="wp_smtp[timeout]" value="<?php echo esc_attr( $s['timeout'] ); ?>" class="small-text" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Отладка', 'wp-smtp-kv' ); ?></th>
						<td>
							<label for="wp_smtp_debug">
								<input type="checkbox" id="wp_smtp_debug" name="wp_smtp[debug]" value="1" <?php checked( $s['debug'], 1 ); ?> />
								<?php esc_html_e( 'Писать диалог с SMTP-сервером в error_log (не включайте на продакшене)', 'wp-smtp-kv' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Сохранить настройки', 'wp-smtp-kv' ), 'primary', 'wp_smtp_save' ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Проверка отправки', 'wp-smtp-kv' ); ?></h2>
			<p><?php esc_html_e( 'Отправляет письмо через сохранённые настройки, даже если SMTP сейчас выключен. Сначала сохраните настройки.', 'wp-smtp-kv' ); ?></p>
			<form method="post" action="">
				<?php wp_nonce_field( 'wp_smtp_send_test' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wp_smtp_test_to"><?php esc_html_e( 'Кому', 'wp-smtp-kv' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="wp_smtp_test_to" name="test_to" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" required />
							<?php submit_button( __( 'Отправить тест', 'wp-smtp-kv' ), 'secondary', 'wp_smtp_test', false ); ?>
						</td>
					</tr>
				</table>
			</form>

			<h2><?php esc_html_e( 'Частые настройки', 'wp-smtp-kv' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<thead><tr><th><?php esc_html_e( 'Провайдер', 'wp-smtp-kv' ); ?></th><th><?php esc_html_e( 'Хост', 'wp-smtp-kv' ); ?></th><th><?php esc_html_e( 'Порт / шифрование', 'wp-smtp-kv' ); ?></th></tr></thead>
				<tbody>
					<tr><td>Yandex 360 / Почта</td><td>smtp.yandex.ru</td><td>465 / SSL</td></tr>
					<tr><td>Gmail (пароль приложения)</td><td>smtp.gmail.com</td><td>587 / TLS</td></tr>
					<tr><td>Mail.ru</td><td>smtp.mail.ru</td><td>465 / SSL</td></tr>
					<tr><td>Timeweb / Beget / reg.ru</td><td>smtp.&lt;домен хостинга&gt;</td><td>465 / SSL или 587 / TLS</td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Per-user transient name for the flash notice.
	 *
	 * @return string
	 */
	private function notice_key() {
		return 'wp_smtp_notice_' . get_current_user_id();
	}

	/**
	 * POST/redirect/GET helper.
	 *
	 * @param string $status Notice marker.
	 * @return void
	 */
	private function redirect( $status ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => self::PAGE_SLUG,
					'wp_smtp_status'  => $status,
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
