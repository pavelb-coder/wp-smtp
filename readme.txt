=== WP SMTP-KV ===
Contributors: pavelb-coder
Tags: smtp, mail, email, phpmailer, wp-mail
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send WordPress mail through an authenticated SMTP server. Simple settings page, encrypted password storage, and a built-in test email.

== Description ==

WP SMTP-KV configures the PHPMailer instance used by `wp_mail()` so that all WordPress mail goes through your SMTP server instead of the local PHP `mail()` function.

Features:

* SMTP host, port, encryption (none / SSL / STARTTLS) and SMTP AUTH.
* Login and password; the password is stored encrypted (AES-256-CBC, key derived from `wp_salt()`), and is never shown back in the form.
* Custom `From` name and address with an option to force them for all messages.
* Connection timeout and an optional SMTP debug log (goes to `error_log`).
* "Send a test email" tool with a human-readable error message from PHPMailer.
* Settings page under Settings -> WP SMTP-KV, protected by `manage_options`, nonces, sanitisation and escaping.
* Own update identity: `Update URI` header, unique folder `wp-smtp-kv` and text domain `wp-smtp-kv`, so WordPress never offers updates for the unrelated `wp-smtp` plugin from the .org directory.
* Clean uninstall: settings are removed when the plugin is deleted.

Requires OpenSSL for password encryption. If OpenSSL is unavailable the password is stored as plain text in the options table — do not use such a server for production mail.

== Installation ==

1. Upload the `wp-smtp-kv` folder to `/wp-content/plugins/`, or install `wp-smtp-kv-<x.y.z>.zip` via Plugins -> Add New. Do not name the folder `wp-smtp`: that slug belongs to a different plugin on WordPress.org.
2. Activate **WP SMTP-KV**.
3. Open Settings -> WP SMTP-KV, fill in the host, port, encryption, login and password.
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

= WordPress offers an update from SolidWP / "Solid Mail". Is that this plugin? =

No. The WordPress.org slug `wp-smtp` belongs to "Solid Mail – SMTP email and logging made by SolidWP" (author SolidWP). WordPress checks updates by slug, so a custom plugin in a `wp-smtp` folder was being matched to it and offered version 3.0.0, which would overwrite your files. Since 1.1.0 this plugin ships in a `wp-smtp-kv` folder, declares `Update URI: https://github.com/pavelb-coder/wp-smtp` (WordPress 5.8+ ignores non-.org update URIs) and filters cached .org update entries for its basenames. If the notice is still visible on an older installation, clear the update cache with `wp transient delete update_plugins --network` (or wait for the next update check) and rename the plugin folder to `wp-smtp-kv`. Settings are stored in `wp_smtp_settings` and survive the folder rename — deactivate/reactivate instead of using "Delete", because deletion runs uninstall.php.

= What version format is used? =

SemVer `x.y.z` (MAJOR.MINOR.PATCH), for example `1.1.0`. The same version is kept in the plugin header, `WP_SMTP_VERSION`, `Stable tag` and the release archive name `wp-smtp-kv-<x.y.z>.zip`. A helper (`tools/version.py`) validates and bumps it.

== Changelog ==

= 1.1.0 =
* Fix: WordPress no longer mistakes the plugin for the WordPress.org `wp-smtp` plugin ("Solid Mail" by SolidWP) and no longer offers its updates.
* Added `Update URI: https://github.com/pavelb-coder/wp-smtp` header (WP 5.8+ ignores non-.org update URIs).
* Release archive now installs into `wp-smtp-kv/` with text domain `wp-smtp-kv` and admin page `?page=wp-smtp-kv`.
* Added `WP_SMTP_Updates`: filters cached WordPress.org update responses for our plugin basenames without touching other plugins or future custom updates.
* Added `tools/version.py` identity checks (Update URI, text domain, unique folder) and `tests/test-updates.php`.
* Settings key `wp_smtp_settings`, classes and hooks are unchanged; existing settings survive the update.

= 1.0.1 =
* Renamed the plugin to WP SMTP-KV (plugin header, admin page, test email, documentation). Internal identifiers, settings key, text domain and plugin folder are unchanged, so existing installations keep working.

= 1.0.0 =
* Initial release: settings page, encrypted password, SMTP AUTH, From override, debug logging, test email, uninstall cleanup.
