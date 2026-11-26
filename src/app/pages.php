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
 * The offline page and the install page: each its own small document, with
 * no user data and no nonce. The worker keeps a copy of the offline page; the
 * install page is public. Screens are drawn by Frame.
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
	 * Render the offline page or the install page.
	 *
	 * @param string $template `offline` or `install`.
	 */
	public static function render( string $template = 'offline' ): string {
		$template = 'install' === $template ? 'install' : 'offline';
		$settings = Settings::get();

		$vars = array(
			'lang'        => (string) get_bloginfo( 'language' ),
			'name'        => $settings['name'],
			'identity'    => Identity::css(),
			'manifest'    => Routes::url( 'manifest' ),
			'touch_icon'  => Manifest::touchIcon(),
			'stylesheet'  => self::stylesheet(),
			'register'    => self::registerScript(),
			'sw'          => Routes::url( 'sw' ),
			'scope'       => Routes::scope(),
			'home'        => Routes::url( 'home' ),
			'install_url' => Routes::url( 'install' ),
			'short_name'  => $settings['short_name'],
			'pitch'       => Settings::installText(),
			'install_js'  => self::assetUrl( 'assets/js/install.js' ),
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
