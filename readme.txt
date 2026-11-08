=== WP SMTP ===
Contributors: pavelb-coder
Tags: smtp, mail, email, phpmailer, wp-mail
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WordPress mail through an authenticated SMTP server. Simple settings page, encrypted password storage, and a built-in test email.

== Description ==

WP SMTP configures the PHPMailer instance used by `wp_mail()` so that all WordPress mail goes through your SMTP server instead of the local PHP `mail()` function.

Features:

* SMTP host, port, encryption (none / SSL / STARTTLS) and SMTP AUTH.
* Login and password; the password is stored encrypted (AES-256-CBC, key derived from `wp_salt()`), and is never shown back in the form.
* Custom `From` name and address with an option to force them for all messages.
* Connection timeout and an optional SMTP debug log (goes to `error_log`).
* "Send a test email" tool with a human-readable error message from PHPMailer.
* Settings page under Settings -> WP SMTP, protected by `manage_options`, nonces, sanitisation and escaping.
* Clean uninstall: settings are removed when the plugin is deleted.

Requires OpenSSL for password encryption. If OpenSSL is unavailable the password is stored as plain text in the options table — do not use such a server for production mail.

== Installation ==

1. Upload the `wp-smtp` folder to `/wp-content/plugins/`, or install the ZIP via Plugins -> Add New.
2. Activate **WP SMTP**.
3. Open Settings -> WP SMTP, fill in the host, port, encryption, login and password.
4. Tick "Use SMTP", click **Save settings**, then send a test email.
5. Only enable debug mode while troubleshooting.

== Frequently Asked Questions ==

= The form says the password is saved, but mail is not sent =

Check that the From address matches the SMTP account, that the port matches the encryption type (465 for SSL, 587 for STARTTLS), and that the hosting provider does not block outbound port 465/587. Enable debug mode and read `error_log`.

= Does it work with Gmail? =

Yes. Enable 2-step verification, create an App Password and use `smtp.gmail.com`, port 587, TLS. Use the app password, not the account password.

= Can I send a test while SMTP is disabled? =

Yes. The test button always uses the saved SMTP settings, so you can verify them before switching all site mail over.

= Where are the credentials stored? =

In the `wp_smtp_settings` option in the WordPress database. The password is encrypted with a key derived from the site salts (`wp_salt('auth')`), so it is not readable as plain text in the database. Anyone who can edit PHP code on the site can still decrypt it; keep `wp-config.php` and admin access secure.

= Will it conflict with other SMTP plugins? =

Yes, potentially: only one plugin should configure PHPMailer's SMTP transport at a time. Deactivate other SMTP plugins.

= What version format is used? =

SemVer `x.y.z` (MAJOR.MINOR.PATCH), for example `1.0.0`. The same version is kept in the plugin header, `WP_SMTP_VERSION`, `Stable tag` and the release archive name `wp-smtp-1.0.0.zip`. A helper (`tools/version.py`) validates and bumps it.

== Changelog ==

= 1.0.0 =
* Initial release: settings page, encrypted password, SMTP AUTH, From override, debug logging, test email, uninstall cleanup.
