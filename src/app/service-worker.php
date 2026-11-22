<?php
/**
 * The service worker script.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;

/**
 * Serves `assets/js/sw.js` with its configuration in front of it.
 *
 * The worker is registered at the site root but acts only on the app's own
 * pages and the plugin's own static files; every other request goes to the
 * network untouched, and nothing that carries a page's HTML is ever cached.
 */
final class ServiceWorker {

	/**
	 * Prefix of every cache the worker owns, so it can delete old ones and
	 * leave other workers' caches alone.
	 */
	public const CACHE_PREFIX = 'site-pwa-';

	/**
	 * What the worker reads before its code.
	 *
	 * @return array{cachePrefix: string, cache: string, appPath: string, offlineUrl: string, precache: list<string>}
	 */
	public static function config(): array {
		$precache = array( Routes::url( 'offline' ), Pages::stylesheet() );
		return array(
			'cachePrefix' => self::CACHE_PREFIX,
			'cache'       => self::CACHE_PREFIX . substr( md5( implode( '|', array_merge( array( Plugin::VERSION, Plugin::assetVersion( Plugin::asset( 'assets/js/sw.js' ) ) ), $precache ) ) ), 0, 12 ),
			'appPath'     => Routes::scope() . Routes::prefix() . '/',
			'offlineUrl'  => Routes::url( 'offline' ),
			'precache'    => $precache,
		);
	}

	/**
	 * The worker that serves the app.
	 */
	public static function script(): string {
		$imports = Conflicts::imports();
		$prefix  = array() === $imports ? '' : 'importScripts(' . implode( ', ', array_map( static fn( string $url ): string => (string) wp_json_encode( $url, JSON_UNESCAPED_SLASHES ), $imports ) ) . ");\n";
		return $prefix . 'self.SITE_PWA_CONFIG = ' . wp_json_encode( self::config(), JSON_UNESCAPED_SLASHES ) . ";\n" . self::file( 'assets/js/sw.js' );
	}

	/**
	 * The worker served while the app is off: it deletes its caches and
	 * unregisters itself, so turning the app off reaches every browser that
	 * installed it.
	 */
	public static function retirement(): string {
		return 'self.SITE_PWA_CACHE_PREFIX = ' . wp_json_encode( self::CACHE_PREFIX ) . ";\n" . self::file( 'assets/js/sw-retire.js' );
	}

	/**
	 * One of the plugin's scripts, minified unless `SCRIPT_DEBUG` is on.
	 *
	 * @param string $relative Source path under the plugin directory.
	 */
	private static function file( string $relative ): string {
		$contents = file_get_contents( Plugin::dir() . Plugin::asset( $relative ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a file of the plugin's own, read from disk.
		return false === $contents ? '' : $contents;
	}
}
