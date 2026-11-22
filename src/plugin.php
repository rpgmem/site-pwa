<?php
/**
 * Plugin bootstrap.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa;

/**
 * Owns the plugin's identity (version, floors, paths) and wires its hooks.
 */
final class Plugin {

	/**
	 * Plugin version, defined in the main plugin file beside the `Version:`
	 * header.
	 */
	public const VERSION = \SITE_PWA_VERSION;

	/**
	 * Minimum PHP version, as declared by the `Requires PHP:` header.
	 */
	public const REQUIRES_PHP = '8.3';

	/**
	 * Minimum WordPress version, as declared by the `Requires at least:` header.
	 */
	public const REQUIRES_WP = '6.8';

	/**
	 * Plugin slug: the folder, the main file's basename and the text domain.
	 */
	public const SLUG = 'site-pwa';

	/**
	 * Absolute path of the main plugin file, set by boot().
	 *
	 * @var string
	 */
	private static string $file = '';

	/**
	 * Wire the plugin. Called once from the main plugin file.
	 *
	 * @param string $file Absolute path of the main plugin file.
	 */
	public static function boot( string $file ): void {
		self::$file = $file;

		// `init` is the earliest hook where translations may load without a
		// "loaded too early" notice since WordPress 6.7; default priority.
		add_action( 'init', array( self::class, 'loadTextdomain' ) );

		// The app's routes answer on the front end; the worker script too.
		App\Routes::register();
		App\Frame::register();
		App\Login::register();

		if ( is_admin() ) {
			Admin\SettingsPage::register();
		}

		// Not behind is_admin(): WordPress also scans for updates from cron.
		Update\GithubUpdater::register();
	}

	/**
	 * On deactivation, drop the app's rewrite rules: the stored rules are
	 * deleted so WordPress rebuilds them without ours on the next request,
	 * and the revision is forgotten so a reactivation flushes again.
	 */
	public static function deactivate(): void {
		delete_option( App\Routes::REVISION_OPTION );
		delete_option( 'rewrite_rules' );
	}

	/**
	 * Load the bundled translations from languages/.
	 */
	public static function loadTextdomain(): void {
		load_plugin_textdomain( self::SLUG, false, dirname( plugin_basename( self::$file ) ) . '/languages' );
	}

	/**
	 * Absolute path of the main plugin file.
	 */
	public static function file(): string {
		return self::$file;
	}

	/**
	 * Absolute path of the plugin directory, with a trailing slash.
	 */
	public static function dir(): string {
		return plugin_dir_path( self::$file );
	}

	/**
	 * URL of the plugin directory, with a trailing slash.
	 */
	public static function url(): string {
		return plugin_dir_url( self::$file );
	}

	/**
	 * The path of one of the plugin's own CSS or JS files as it should load:
	 * the `.min` build in production, the readable source when `SCRIPT_DEBUG`
	 * is on. `npm run build` writes the `.min` files, and CI fails when one is
	 * older than its source.
	 *
	 * @param string $relative Source path under the plugin directory, e.g. `assets/js/x.js`.
	 */
	public static function asset( string $relative ): string {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return $relative;
		}
		return (string) preg_replace( '/\.(css|js)$/', '.min.$1', $relative );
	}

	/**
	 * The `?ver=` of one of the plugin's own assets: the plugin version plus
	 * the file's modification time, so a deploy of the same version still
	 * reaches browsers that hold the previous file.
	 *
	 * @param string $relative Path under the plugin directory, e.g. `assets/js/x.js`.
	 */
	public static function assetVersion( string $relative ): string {
		$time = @filemtime( self::dir() . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file falls back to the version.
		return false === $time ? self::VERSION : self::VERSION . '.' . $time;
	}
}
