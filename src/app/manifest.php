<?php
/**
 * The web app manifest.
 *
 * @package SitePwa
 */

declare(strict_types=1);

namespace SitePwa\App;

use SitePwa\Plugin;
use SitePwa\Settings;

/**
 * Builds the manifest that makes the app installable.
 */
final class Manifest {

	/**
	 * The manifest, as an array for `wp_json_encode()`.
	 *
	 * @return array<string, mixed>
	 */
	public static function data(): array {
		$settings = Settings::get();
		$home     = Routes::url( 'home' );

		return array(
			'id'               => $home,
			'name'             => $settings['name'],
			'short_name'       => $settings['short_name'],
			'start_url'        => $home,
			'scope'            => $home,
			'display'          => 'standalone',
			'background_color' => $settings['background_color'],
			'theme_color'      => Identity::current()['accent'],
			'lang'             => (string) get_bloginfo( 'language' ),
			'dir'              => is_rtl() ? 'rtl' : 'ltr',
			'icons'            => self::icons(),
		);
	}

	/**
	 * The app's icons: the site icon (Settings → General) when there is one,
	 * otherwise the plugin's own, which also ships a maskable version.
	 *
	 * The site icon is not offered as maskable: nothing guarantees its
	 * drawing stays inside the safe zone a launcher may crop to.
	 *
	 * @return list<array{src: string, sizes: string, type: string, purpose: string}>
	 */
	public static function icons(): array {
		if ( has_site_icon() ) {
			$icons = array();
			foreach ( array( 192, 512 ) as $size ) {
				$url     = (string) get_site_icon_url( $size );
				$type    = wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ) );
				$icons[] = array(
					'src'     => $url,
					'sizes'   => $size . 'x' . $size,
					'type'    => is_string( $type['type'] ) ? $type['type'] : 'image/png',
					'purpose' => 'any',
				);
			}
			return $icons;
		}

		$base = Plugin::url() . 'assets/img/brand/';
		return array(
			array(
				'src'     => $base . 'icon-192.png',
				'sizes'   => '192x192',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
			array(
				'src'     => $base . 'icon-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'any',
			),
			array(
				'src'     => $base . 'icon-maskable-512.png',
				'sizes'   => '512x512',
				'type'    => 'image/png',
				'purpose' => 'maskable',
			),
		);
	}

	/**
	 * The icon a home screen on iOS reads (`apple-touch-icon`).
	 */
	public static function touchIcon(): string {
		return self::icons()[0]['src'];
	}
}
