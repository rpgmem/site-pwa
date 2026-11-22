<?php
/**
 * Other service workers that would compete with the app's.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

/**
 * Knows the plugins that register a service worker at the site root.
 *
 * A browser keeps one worker per scope, so two plugins registering at the
 * root replace each other on every visit: the app's would silently disable
 * a push service, or the other way round. While one of these plugins is
 * active the app stays off, unless an administrator confirms the
 * replacement; the settings screen also asks the browser what it already
 * has registered, which catches what this list does not know.
 */
final class Conflicts {

	/**
	 * Plugins known to register a root service worker, main file => name.
	 */
	public const KNOWN = array(
		'onesignal-free-web-push-notifications/onesignal.php' => 'OneSignal',
		'super-progressive-web-apps/superpwa.php' => 'SuperPWA',
		'pwa-for-wp/pwa-for-wp.php'               => 'PWA for WP',
		'pwa/pwa.php'                             => 'PWA',
	);

	/**
	 * The active plugins among the known ones, main file => name.
	 *
	 * @return array<string, string>
	 */
	public static function active(): array {
		$known = (array) apply_filters( 'site_pwa_known_sw_plugins', self::KNOWN );
		$on    = array_merge(
			(array) get_option( 'active_plugins', array() ),
			array_keys( is_multisite() ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array() )
		);

		$found = array();
		foreach ( $known as $file => $name ) {
			if ( in_array( $file, $on, true ) ) {
				$found[ (string) $file ] = (string) $name;
			}
		}
		return $found;
	}

	/**
	 * Scripts another plugin asks the app's worker to load first, kept only
	 * when they are on this site: a push service that needs the root scope
	 * can share it this way instead of replacing the app's worker.
	 *
	 * @return list<string>
	 */
	public static function imports(): array {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$urls = array();
		foreach ( (array) apply_filters( 'site_pwa_sw_imports', array() ) as $url ) {
			$url = esc_url_raw( (string) $url, array( 'https', 'http' ) );
			if ( '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) === $host ) {
				$urls[] = $url;
			}
		}
		return array_values( array_unique( $urls ) );
	}
}
