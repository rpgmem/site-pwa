<?php
/**
 * The app's pages.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;
use SitePwa\Settings;

/**
 * Renders the app's pages from `templates/`.
 *
 * The offline page: its own small document, with no user data and no nonce,
 * because the service worker keeps a copy of it. Screens are drawn by Frame.
 */
final class Pages {

	/**
	 * The app's stylesheet URL, versioned.
	 */
	public static function stylesheet(): string {
		return self::assetUrl( 'assets/css/app.css' );
	}

	/**
	 * The worker registration script URL, versioned.
	 */
	public static function registerScript(): string {
		return self::assetUrl( 'assets/js/register.js' );
	}

	/**
	 * Render the offline page.
	 */
	public static function render(): string {
		$template = 'offline';
		$settings = Settings::get();

		$vars = array(
			'lang'        => (string) get_bloginfo( 'language' ),
			'name'        => $settings['name'],
			'theme_color' => $settings['theme_color'],
			'manifest'    => Routes::url( 'manifest' ),
			'touch_icon'  => Manifest::touchIcon(),
			'stylesheet'  => self::stylesheet(),
			'register'    => self::registerScript(),
			'sw'          => Routes::url( 'sw' ),
			'scope'       => Routes::scope(),
			'home'        => Routes::url( 'home' ),
		);

		ob_start();
		( static function ( string $site_pwa_file, array $site_pwa ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- the included template reads $site_pwa.
			include $site_pwa_file;
		} )( Plugin::dir() . 'templates/' . $template . '.php', $vars );
		return (string) ob_get_clean();
	}

	/**
	 * URL of one of the plugin's assets, minified unless `SCRIPT_DEBUG`.
	 *
	 * @param string $relative Source path under the plugin directory.
	 */
	private static function assetUrl( string $relative ): string {
		$file = Plugin::asset( $relative );
		return add_query_arg( 'ver', Plugin::assetVersion( $file ), Plugin::url() . $file );
	}
}
